/**
 * 比赛认证表单验证和提交处理
 * 使用自定义表单验证系统
 */

// 防止重复初始化
if (window.contestAuthInitialized) {
    console.warn('contest_auth.js already initialized, skipping...');
} else {
    window.contestAuthInitialized = true;

    // 等待DOM加载完成
    document.addEventListener('DOMContentLoaded', function() {
        initContestAuthForm();
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
        const submitButton = document.getElementById('submit_button');
        if (!submitButton) return;

        // 禁用提交按钮防止重复提交

        var submitButtonText = $(submitButton).text();
        var submitButtonEnText = $(submitButton).find('.en-text').text();
        // 最终提交时交由 button_delay 处理
        button_delay($(submitButton), 5, submitButtonText, null, submitButtonEnText);

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
                        button_delay($(submitButton), 1, submitButtonText, null, submitButtonEnText);
                    }
                });
            }
        }).fail(function() {
            alerty.alert({
                message: '网络错误，请重试',
                message_en: 'Network error, please try again',
                title: '网络错误',
                callback: function() {
                    button_delay($(submitButton), 1, submitButtonText, null, submitButtonEnText);
                }
            });
        });
    }

    // 初始化表单验证
    window.FormValidationTip.initFormValidation('#contest_auth_form', fieldConfigs, handleContestAuthSubmit);
}
