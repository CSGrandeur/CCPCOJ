/**
 * 登录表单验证和提交处理
 * 使用自定义表单验证系统
 */

// 防止重复初始化
if (window.loginOutInitialized) {
    console.warn('login_out.js already initialized, skipping...');
} else {
    window.loginOutInitialized = true;

    // 等待DOM加载完成
    document.addEventListener('DOMContentLoaded', function() {
        // 初始化登出功能
        initLogoutFunctionality();
        
        // 初始化登录表单验证
        initLoginFormValidation();
    });
}

/**
 * 初始化登出功能
 */
function initLogoutFunctionality() {
    const logoutButton = document.getElementById('logout_button');
    if (!logoutButton) {
        return; // 如果没有登出按钮，直接返回
    }

    // 绑定登出按钮点击事件
    logoutButton.addEventListener('click', handleLogoutClick);
    
    function handleLogoutClick(e) {
        e.preventDefault();
        
        // 禁用按钮防止重复点击
        logoutButton.disabled = true;
        const originalText = logoutButton.innerHTML;
        logoutButton.innerHTML = '<span class="cn-text"><i class="bi bi-hourglass-split"></i> 登出</span><span class="en-text">Logging out</span>...';

        // 获取登出配置
        const logoutConfig = window.logoutConfig || {};
        const logoutUrl = logoutConfig.logoutUrl || '/csgoj/User/logout_ajax';
        const redirectUrl = logoutConfig.redirectUrl || '/csgoj';

        // 使用jQuery的post方法
        $.post(logoutUrl, function(ret) {
            if (ret && ret.code == 1) {
                // 登出成功
                alerty.success( '登出成功', 'Logout successful');
                setTimeout(function() {
                    location.reload();
                }, 500);
            } else {
                // 登出失败
                alerty.alert({
                    message:  '登出失败',
                    message_en: ret.msg,
                    title: '登出失败',
                    callback: function() {
                        location.href = redirectUrl;
                    }
                });
            }
        }).fail(function() {
            // 请求失败
            alerty.alert({
                message: '网络错误，请重试',
                message_en: 'Network error, please try again',
                title: '网络错误',
                callback: function() {
                    // 恢复按钮状态
                    logoutButton.disabled = false;
                    logoutButton.innerHTML = originalText;
                }
            });
        });
    }
}

/**
 * 初始化登录表单验证
 */
function initLoginFormValidation() {
    // 检查是否存在登录表单
    const loginForm = document.getElementById('login_form');
    if (!loginForm) {
        return; // 如果没有登录表单，直接返回
    }


    // 定义用户ID验证规则（自定义验证函数）
    function validateUserId(value) {
        // 用户名只能包含字母、数字、下划线，且不能以数字开头
        const userIdRegex = /^[a-zA-Z_][a-zA-Z0-9_]*$/;
        return userIdRegex.test(value);
    }

    // 扩展验证规则
    const customValidationRules = {
        ...window.FormValidationTip.FormValidationRules,
        user_id_validate: validateUserId
    };

    // 创建双语验证消息
    const createBilingualMessage = window.FormValidationTip.createBilingualMessage;

    // 字段配置
    const fieldConfigs = {
        user_id: {
            rules: {
                required: true,
                minlength: 3,
                maxlength: 30,
                user_id_validate: true
            },
            messages: {
                required: createBilingualMessage('用户名不能为空', 'User ID is required'),
                minlength: createBilingualMessage('用户名不能少于3个字符', 'User ID must be at least 3 characters'),
                maxlength: createBilingualMessage('用户名不能超过30个字符', 'User ID cannot exceed 30 characters'),
                user_id_validate: createBilingualMessage('用户名格式不正确，只能包含字母、数字、下划线，且不能以数字开头', 'Invalid User ID format, only letters, numbers, and underscores allowed, cannot start with a number')
            }
        },
        password: {
            rules: {
                required: true,
                minlength: 6,
                maxlength: 64
            },
            messages: {
                required: createBilingualMessage('密码不能为空', 'Password is required'),
                minlength: createBilingualMessage('密码不能少于6个字符', 'Password must be at least 6 characters'),
                maxlength: createBilingualMessage('密码不能超过64个字符', 'Password cannot exceed 64 characters')
            }
        }
    };

    // 提交处理函数
    function handleLoginSubmit(form) {
        const submitButton = document.getElementById('login_submit_button');
        if (!submitButton) return;

        // 禁用提交按钮防止重复提交
        submitButton.disabled = true;
        submitButton.innerHTML = '<span class="cn-text"><i class="bi bi-hourglass-split"></i> 登录中</span><span class="en-text">Logging in</span>...';

        // 使用jQuery的ajaxSubmit方法
        $(form).ajaxSubmit(function(ret) {
            if (ret && ret.code == 1) {
                // 登录成功
                alerty.success( '登录成功', 'Login successful');
                setTimeout(function() {
                    location.reload();
                }, 500);
            } else {
                // 登录失败 - 立即恢复按钮状态
                submitButton.disabled = false;
                submitButton.innerHTML = '<span class="cn-text"><i class="bi bi-box-arrow-in-right"></i> 登录</span><span class="en-text">Login</span>';
                
                alerty.alert({
                    message: '登录失败',
                    message_en: ret.msg,
                    title: '登录失败'
                });
            }
        });
    }

    // 初始化表单验证
    if (window.FormValidationTip && window.FormValidationTip.initFormValidation) {
        // 临时扩展验证规则
        const originalRules = window.FormValidationTip.FormValidationRules;
        window.FormValidationTip.FormValidationRules = customValidationRules;

        // 初始化验证 - FormValidationTip会处理表单提交事件
        window.FormValidationTip.initFormValidation('#login_form', fieldConfigs, handleLoginSubmit);

        // 恢复原始规则
        window.FormValidationTip.FormValidationRules = originalRules;
    } else {
        console.warn('FormValidationTip not available, falling back to basic validation');
        
        // 简单的回退验证 - 只添加一个submit事件监听器
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const user_id = document.getElementById('user_id');
            const password = document.getElementById('login_password');
            
            let isValid = true;
            
            // 基本验证
            if (!user_id.value.trim()) {
                alerty.alert({
                    message: '请输入用户名',
                    message_en: 'Please enter username',
                    title: '验证失败'
                });
                user_id.focus();
                isValid = false;
            } else if (user_id.value.length < 3) {
                alerty.alert({
                    message: '用户名不能少于3个字符',
                    message_en: 'Username must be at least 3 characters',
                    title: '验证失败'
                });
                user_id.focus();
                isValid = false;
            } else if (user_id.value.length > 30) {
                alerty.alert({
                    message: '用户名不能超过30个字符',
                    message_en: 'Username cannot exceed 30 characters',
                    title: '验证失败'
                });
                user_id.focus();
                isValid = false;
            } else if (!validateUserId(user_id.value)) {
                alerty.alert({
                    message: '用户名格式不正确',
                    message_en: 'Invalid username format',
                    title: '验证失败'
                });
                user_id.focus();
                isValid = false;
            } else if (!password.value.trim()) {
                alerty.alert({
                    message: '请输入密码',
                    message_en: 'Please enter password',
                    title: '验证失败'
                });
                password.focus();
                isValid = false;
            } else if (password.value.length < 6) {
                alerty.alert({
                    message: '密码不能少于6个字符',
                    message_en: 'Password must be at least 6 characters',
                    title: '验证失败'
                });
                password.focus();
                isValid = false;
            } else if (password.value.length > 64) {
                alerty.alert({
                    message: '密码不能超过64个字符',
                    message_en: 'Password cannot exceed 64 characters',
                    title: '验证失败'
                });
                password.focus();
                isValid = false;
            }
            
            if (isValid) {
                handleLoginSubmit(loginForm);
            }
        });
    }
}