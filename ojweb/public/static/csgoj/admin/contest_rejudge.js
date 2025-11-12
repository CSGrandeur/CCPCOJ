/**
 * 比赛重判管理 JavaScript
 * Contest Rejudge Management JavaScript
 */

/**
 * 初始化重判表单
 */
function InitContestRejudgeForm() {
    const form = document.getElementById('problem_rejudge_form');
    const submitButton = form.querySelector('button[type="submit"]');
    
    if (!form || !submitButton) {
        console.warn('Contest rejudge form elements not found');
        return;
    }

    // 字段配置
    const fieldConfigs = {
        solution_id: { 
            rules: { maxlength: 256 },
            messages: {
                maxlength: '提交号长度不能超过256个字符 (Solution ID cannot exceed 256 characters)'
            }
        },
        problem_id: { 
            rules: { maxlength: 256 },
            messages: {
                maxlength: '题号长度不能超过256个字符 (Problem ID cannot exceed 256 characters)'
            }
        }
    };

    // 提交处理函数
    function handleRejudgeSubmit(form) {
        const solutionId = form.querySelector('#solution_id').value.trim();
        const problemId = form.querySelector('#problem_id').value.trim();
        const acCheckbox = form.querySelector('#rejudge_res_check_ac');
        
        let acAlertMessage = '';
        if (acCheckbox && acCheckbox.checked) {
            acAlertMessage = '<strong class="text-danger">请慎重重判AC的提交，确认？</strong><br/><span class="en-text">Please confirm rejudging AC submissions carefully?</span>';
        }

        // 验证输入
        if (solutionId.length > 0 && problemId.length > 0) {
            alerty.alert({
                message: '在提交号与题号中只选择其中一项填写',
                message_en: 'Please clear one input either solution_id or problem_id'
            });
            return false;
        } else if (solutionId.length > 0) {
            if (!/^[0-9,]+$/.test(solutionId)) {
                alerty.alert({
                    message: '提交号格式不正确',
                    message_en: 'Solution ID format is not valid'
                });
                return false;
            }
            
            if (acAlertMessage) {
                alerty.confirm({
                    message: acAlertMessage,
                    callback: () => submitRejudgeForm(form)
                });
            } else {
                submitRejudgeForm(form);
            }
        } else if (problemId.length > 0) {
            if (!/^[A-Za-z,]+$/.test(problemId)) {
                alerty.alert({
                    message: '比赛中请使用字母题号',
                    message_en: 'Problem ID in contest should be in alphabet type'
                });
                return false;
            }
            
            const confirmMessage = acAlertMessage + 
                '<br/><strong class="text-warning">基于题号评测时间较久，确认？</strong><br/><span class="en-text">Rejudge by problem_id may take a long time, sure to rejudge?</span>';
            
            alerty.confirm({
                message: confirmMessage,
                callback: () => submitRejudgeForm(form),
            });
        } else {
            alerty.error({
                message: '请填写提交号或题目号',
                message_en: 'Please provide solution_id or problem_id'
            });
            return false;
        }
        
        return false;
    }

    // 提交重判表单
    function submitRejudgeForm(form) {
        submitButton.disabled = true;
        const originalText = submitButton.innerHTML;
        submitButton.innerHTML = '<span class="cn-text"><i class="bi bi-hourglass-split me-1"></i>重判中</span><span class="en-text">Rejudging</span>...';

        const formData = new FormData(form);
        
        fetch(window.contestRejudgeConfig.submit_url, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.code === 1) {
                alerty.success({
                    message: data.msg || '重判已开始',
                    message_en: 'Rejudge started successfully'
                });
                
                // 如果有跳转URL，则跳转
                if (data.data && data.data.jumpurl) {
                    setTimeout(() => {
                        window.location.href = data.data.jumpurl;
                    }, 2000);
                }
            } else {
                alerty.error({
                    message: data.msg || '重判失败',
                    message_en: 'Rejudge failed'
                });
            }
        })
        .catch(error => {
            console.error('Rejudge error:', error);
            alerty.error({
                message: '网络错误，请重试',
                message_en: 'Network error, please try again'
            });
        })
        .finally(() => {
            setTimeout(() => {
                submitButton.disabled = false;
                submitButton.innerHTML = originalText;
            }, 3000);
        });
    }

    // 初始化表单验证
    if (window.FormValidationTip && window.FormValidationTip.initFormValidation) {
        window.FormValidationTip.initFormValidation('#problem_rejudge_form', fieldConfigs, handleRejudgeSubmit);
    } else {
        console.warn('FormValidationTip not available, falling back to basic validation');
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            handleRejudgeSubmit(form);
        });
    }

    // 键盘快捷键 (Ctrl+S)
    document.addEventListener('keydown', function(e) {
        if (e.keyCode === 83 && e.ctrlKey) {
            e.preventDefault();
            submitButton.click();
        }
    });
}

// 页面加载完成后初始化
document.addEventListener('DOMContentLoaded', function() {
    if (window.contestRejudgeConfig) {
        InitContestRejudgeForm();
    }
});
