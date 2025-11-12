/**
 * 比赛认证表单验证和提交处理
 * 使用自定义表单验证系统
 * 包括正常登录表单和收集模式表单
 */

// 防止重复初始化
if (window.contestAuthInitialized) {
    console.warn('contest_auth.js already initialized, skipping...');
} else {
    window.contestAuthInitialized = true;
    
    // 等待DOM加载完成
    document.addEventListener('DOMContentLoaded', function() {
        initContestAuthForm();
        initContestCollectForm();
        initPasswordlessLogin();
    });
}

/**
 * 初始化比赛认证表单
 */
function initContestAuthForm() {
    const contestAuthForm = document.getElementById('contest_auth_form');
    if (!contestAuthForm) {
        return; // 如果没有比赛认证表单，直接返回
    }

    // 字段配置 - 使用简化的配置，依赖默认消息
    const fieldConfigs = {
        team_id: {
            rules: {
                required: true,
                minlength: 3,
                maxlength: 20
            }
            // 不设置messages，使用默认的双语消息
        },
        password: {
            rules: {
                required: true,
                minlength: 3,
                maxlength: 32
            }
            // 不设置messages，使用默认的双语消息
        }
    };

    // 提交处理函数
    function handleContestAuthSubmit(form) {
        const submitButton = document.getElementById('submit_contest_logon_button');
        if (!submitButton) return false;

        // 禁用提交按钮防止重复提交
        submitButton.disabled = true;

        // 获取表单数据
        const team_id = form.team_id.value.trim();
        const password = form.password.value.trim();
        const url = `${form.action}?cid=${window.CONTEST_AUTH_CONFIG.cid}`;

        $.post(url, { team_id, password }, function(ret) {
            if (ret && typeof ret === 'string') {
                try { ret = JSON.parse(ret); } catch(e) { ret = { code: -1, msg: '未知错误' }; }
            }
            if (ret && ret.code == 1) {
                alerty.success('认证成功', 'Authentication successful');
                setTimeout(function() {
                    if (ret.data && ret.data.redirect_url) {
                        location.href = ret.data.redirect_url;
                    } else {
                        location.reload();
                    }
                }, 500);
            } else {
                alerty.alert({
                    message: (ret && ret.msg) || '认证失败',
                    message_en: 'Authentication failed',
                    title: '认证失败',
                    callback: function() {
                        submitButton.disabled = false;
                    }
                });
            }
        }, 'json').fail(function() {
            alerty.alert({
                message: '网络错误，请重试',
                message_en: 'Network error, please try again',
                title: '网络错误',
                callback: function() {
                    submitButton.disabled = false;
                }
            });
        });
        
        // 返回false确保不会触发默认表单提交
        return false;
    }

    // 初始化表单验证
    if (window.FormValidationTip && window.FormValidationTip.initFormValidation) {
        window.FormValidationTip.initFormValidation('#contest_auth_form', fieldConfigs, handleContestAuthSubmit);
    } else {
        console.warn('FormValidationTip not available, falling back to basic validation');
        // 回退机制：手动添加submit事件监听器
        contestAuthForm.addEventListener('submit', function(e) {
            e.preventDefault();
            e.stopPropagation();
            handleContestAuthSubmit(contestAuthForm);
        });
    }
}

/**
 * 初始化收集模式表单
 */
function initContestCollectForm() {
    const collectForm = document.getElementById('contest_collect_form');
    if (!collectForm) {
        return; // 如果没有收集表单，直接返回
    }

    // 字段配置 - team_id验证规则（参考teamgen的验证逻辑）
    // 使用 createBilingualMessage 创建双语消息
    function createBilingualMessage(zh, en) {
        return `${zh}<span class="en-text"> ${en}</span>`;
    }
    
    const fieldConfigs = {
        team_id: {
            rules: {
                required: true,
                minlength: 3,
                maxlength: 30,
                pattern: /^[a-zA-Z0-9]+$/
            },
            messages: {
                required: createBilingualMessage('队伍ID不能为空', 'Team ID is required'),
                minlength: createBilingualMessage('队伍ID不能少于3个字符', 'Team ID must be at least 3 characters'),
                maxlength: createBilingualMessage('队伍ID不能超过30个字符', 'Team ID cannot exceed 30 characters'),
                pattern: createBilingualMessage('队伍ID只能包含字母和数字', 'Team ID can only contain letters and numbers')
            }
        }
    };

    // 提交处理函数
    function handleCollectSubmit(form) {
        const submitButton = document.getElementById('collect_submit_button');
        if (!submitButton) return false;

        // 禁用提交按钮防止重复提交
        submitButton.disabled = true;

        // 获取表单数据
        const team_id = form.team_id.value.trim();
        // 从表单的隐藏字段或action URL中获取cid
        const cidInput = form.querySelector('input[name="cid"]');
        const cid = cidInput ? cidInput.value : (window.CONTEST_AUTH_CONFIG && window.CONTEST_AUTH_CONFIG.cid ? window.CONTEST_AUTH_CONFIG.cid : '');
        const url = `${form.action}?cid=${cid}`;

        $.post(url, { team_id }, function(ret) {
            if (ret && typeof ret === 'string') {
                try { ret = JSON.parse(ret); } catch(e) { ret = { code: -1, msg: '未知错误' }; }
            }
            if (ret && ret.code == 1) {
                // 检查是否有警告信息
                if (ret.data && (ret.data.team_warning_cn || ret.data.team_warning_en)) {
                    // 先显示成功消息
                    alerty.success('账号收集成功', 'Account collected successfully');
                    // 然后显示警告消息（使用alert确保用户必须确认）
                    setTimeout(function() {
                        alerty.alert({
                            message: ret.data.team_warning_cn || '警告',
                            message_en: ret.data.team_warning_en || 'Warning',
                            title: '警告',
                            callback: function() {
                                // 用户确认后刷新页面
                                location.reload();
                            }
                        });
                    }, 500);
                } else {
                    // 没有警告信息，正常处理
                    alerty.success('账号收集成功', 'Account collected successfully');
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                }
            } else {
                alerty.alert({
                    message: (ret && ret.msg) || '收集失败',
                    message_en: 'Collection failed',
                    title: '收集失败',
                    callback: function() {
                        submitButton.disabled = false;
                    }
                });
            }
        }, 'json').fail(function() {
            alerty.alert({
                message: '网络错误，请重试',
                message_en: 'Network error, please try again',
                title: '网络错误',
                callback: function() {
                    submitButton.disabled = false;
                }
            });
        });
        
        // 返回false确保不会触发默认表单提交
        return false;
    }
    
    // 初始化表单验证
    if (window.FormValidationTip && window.FormValidationTip.initFormValidation) {
        window.FormValidationTip.initFormValidation('#contest_collect_form', fieldConfigs, handleCollectSubmit);
    } else {
        console.warn('FormValidationTip not available, falling back to basic validation');
        // 回退机制：手动添加submit事件监听器
        collectForm.addEventListener('submit', function(e) {
            e.preventDefault();
            e.stopPropagation();
            handleCollectSubmit(collectForm);
        });
    }
}

/**
 * 初始化免密登录功能
 */
function initPasswordlessLogin() {
    const passwordlessBtn = document.getElementById('passwordless_login_btn');
    if (!passwordlessBtn) {
        return; // 如果没有免密登录按钮，直接返回
    }

    passwordlessBtn.addEventListener('click', function() {
        const btn = this;
        btn.disabled = true;
        
        $.ajax({
            url: window.CONTEST_AUTH_CONFIG.passwordlessUrl,
            type: 'POST',
            data: { cid: window.CONTEST_AUTH_CONFIG.cid },
            dataType: 'json',
            success: function(ret) {
                if (ret && ret.code == 1) {
                    alerty.success('登录成功', 'Login successful');
                    setTimeout(function() {
                        if (ret.data && ret.data.redirect_url) {
                            location.href = ret.data.redirect_url;
                        } else {
                            location.reload();
                        }
                    }, 500);
                } else {
                    alerty.error(ret?.msg || '登录失败', ret?.msg || 'Login failed');
                    btn.disabled = false;
                }
            },
            error: function() {
                alerty.error('登录失败，请重试', 'Login failed, please try again');
                btn.disabled = false;
            }
        });
    });
}
