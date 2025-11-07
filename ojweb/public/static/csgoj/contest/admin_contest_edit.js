/**
 * 比赛编辑通用表单校验（前台/后台均可调用）
 * - 根据页面存在的字段动态生成校验规则
 * - 允许调用方（后台）追加/覆盖规则
 * - 默认附带通用的提交处理与快捷键
 */

const LENGTH_LIMITS = {
    YEAR_MIN: 2000,
    YEAR_MAX: 2100,
    MONTH_MIN: 1,
    MONTH_MAX: 12,
    DAY_MIN: 1,
    DAY_MAX: 31,
    HOUR_MIN: 0,
    HOUR_MAX: 23,
    MINUTE_MIN: 0,
    MINUTE_MAX: 59,
    RATIO_MIN: 0,
    RATIO_MAX: 100,
    FROZEN_MIN: -1,
    FROZEN_MAX: 2592000,
    TOPTEAM_MIN: 1,
    TOPTEAM_MAX: 20,
    DESCRIPTION_MAX: 16384
};

function exists(sel) { return document.querySelector(sel) !== null; }

function buildDynamicRules() {
    const rules = {};
    // 标题（仅后台通常存在）
    if (exists('#title')) {
        rules.title = { rules: { required: true, maxlength: 200 } };
    }
    // 描述
    if (exists('#contest_description')) {
        rules.description = { rules: { maxlength: LENGTH_LIMITS.DESCRIPTION_MAX } };
    }
    // 奖项比例
    if (exists('#ratio_gold'))  rules.ratio_gold  = { rules: { number: true, min: LENGTH_LIMITS.RATIO_MIN,  max: LENGTH_LIMITS.RATIO_MAX } };
    if (exists('#ratio_silver'))rules.ratio_silver= { rules: { number: true, min: LENGTH_LIMITS.RATIO_MIN,  max: LENGTH_LIMITS.RATIO_MAX } };
    if (exists('#ratio_bronze'))rules.ratio_bronze= { rules: { number: true, min: LENGTH_LIMITS.RATIO_MIN,  max: LENGTH_LIMITS.RATIO_MAX } };
    // 冻结时间
    if (exists('#frozen_minute'))rules.frozen_minute= { rules: { number: true, min: LENGTH_LIMITS.FROZEN_MIN, max: LENGTH_LIMITS.FROZEN_MAX } };
    if (exists('#frozen_after')) rules.frozen_after = { rules: { number: true, min: LENGTH_LIMITS.FROZEN_MIN, max: LENGTH_LIMITS.FROZEN_MAX } };
    // Top N
    if (exists('#topteam'))     rules.topteam      = { rules: { number: true, min: LENGTH_LIMITS.TOPTEAM_MIN, max: LENGTH_LIMITS.TOPTEAM_MAX } };
    // 语言
    if (exists('select[name="language[]"]')) rules['language[]'] = { rules: { required: true } };
    // 时间（存在就校验基本范围）
    const timeFields = [
        ['#start_year',   { min: LENGTH_LIMITS.YEAR_MIN,   max: LENGTH_LIMITS.YEAR_MAX }],
        ['#start_month',  { min: LENGTH_LIMITS.MONTH_MIN,  max: LENGTH_LIMITS.MONTH_MAX }],
        ['#start_day',    { min: LENGTH_LIMITS.DAY_MIN,    max: LENGTH_LIMITS.DAY_MAX }],
        ['#start_hour',   { min: LENGTH_LIMITS.HOUR_MIN,   max: LENGTH_LIMITS.HOUR_MAX }],
        ['#start_minute', { min: LENGTH_LIMITS.MINUTE_MIN, max: LENGTH_LIMITS.MINUTE_MAX }],
        ['#end_year',     { min: LENGTH_LIMITS.YEAR_MIN,   max: LENGTH_LIMITS.YEAR_MAX }],
        ['#end_month',    { min: LENGTH_LIMITS.MONTH_MIN,  max: LENGTH_LIMITS.MONTH_MAX }],
        ['#end_day',      { min: LENGTH_LIMITS.DAY_MIN,    max: LENGTH_LIMITS.DAY_MAX }],
        ['#end_hour',     { min: LENGTH_LIMITS.HOUR_MIN,   max: LENGTH_LIMITS.HOUR_MAX }],
        ['#end_minute',   { min: LENGTH_LIMITS.MINUTE_MIN, max: LENGTH_LIMITS.MINUTE_MAX }]
    ];
    timeFields.forEach(([sel, range]) => {
        const el = document.querySelector(sel);
        if (el) {
            const name = el.name;
            rules[name] = { rules: { required: true, number: true, min: range.min, max: range.max } };
        }
    });
    // 密码（仅加密模式显示）
    if (exists('#password')) {
        rules.password = { rules: { minlength: 6, maxlength: 15 } };
    }
    return rules;
}

function defaultSubmit(form) {
    const submitButton = document.getElementById('submit_button');
    if (!submitButton) return;
    const $btn = $('#submit_button');
    // 读取原始中英文文本
    const bt = (window.Bilingual && typeof window.Bilingual.getBilingualText === 'function')
        ? window.Bilingual.getBilingualText($btn)
        : { chinese: $btn.text(), english: '' };
    // 提交中视觉反馈
    submitButton.disabled = true;
    submitButton.innerHTML = '<span class="cn-text"><i class="bi bi-hourglass-split me-1"></i> 提交中</span><span class="en-text">Submitting</span>';

    const onSuccess = function(ret) {
        if (ret && ret.code == 1) {
            if (typeof alerty !== 'undefined') {
                if (ret.data && ret.data.alert === true) {
                    alerty.alert({ message: ret.msg, title: '提示' });
                } else {
                    alerty.success(ret.msg, 'Contest successfully modified');
                }
            } else if (typeof alertify !== 'undefined') {
                if (ret.data && ret.data.alert === true) {
                    alertify.alert(ret.msg);
                } else {
                    alertify.success(ret.msg);
                }
            } else {
                alert(ret.msg);
            }
            button_delay($btn, 3, bt.chinese, null, bt.english);

            // 非编辑模式（添加成功）则按返回的 id 跳转到编辑页
            try {
                const cfg = window.CONTEST_EDIT_CONFIG || {};
                if (!cfg.edit_mode && ret.data && ret.data.id) {
                    setTimeout(function(){
                        window.location.href = 'contest_edit?id=' + encodeURIComponent(ret.data.id);
                    }, 500);
                }
            } catch (e) {}
        } else {
            if (typeof alerty !== 'undefined') {
                alerty.alert({ message: (ret && ret.msg) || '修改失败', title: '错误' });
            } else if (typeof alertify !== 'undefined') {
                alertify.alert((ret && ret.msg) || '修改失败');
            } else {
                alert((ret && ret.msg) || '修改失败');
            }
            button_delay($btn, 3, bt.chinese, null, bt.english);
        }
    };
    $(form).ajaxSubmit(onSuccess);
    return false;
}

function InitContestFormValidation(formSelector, extendRules) {
    let rules = buildDynamicRules();
    if (typeof extendRules === 'function') {
        rules = extendRules(rules) || rules;
    }
    window.FormValidationTip.initCommonFormValidation(formSelector, rules, function(form) {
        return defaultSubmit(form);
    });
};


$(document).ready(function() {
    // ========================================
    // 页面元素引用
    // ========================================
    const page_info = window.CONTEST_EDIT_CONFIG;
    const edit_mode = page_info.edit_mode;
    const submit_button = $('#submit_button');
    const submit_button_texts = window.Bilingual.getBilingualText(submit_button);
    const submit_button_text = submit_button_texts.chinese;
    const submit_button_en_text = submit_button_texts.english;
    const problems_input = $('input[name="problems"]'); // 适配新的隐藏输入框
    // ========================================
    // 表单验证配置（复用通用校验）
    // ========================================
    const fieldConfigs = {
        title: { rules: { required: true, maxlength: 200 } },
        description: { rules: { maxlength: LENGTH_LIMITS.DESCRIPTION_MAX } },
        password: { rules: { minlength: 6, maxlength: 15 } },
        ratio_gold: { rules: { number: true, range: [LENGTH_LIMITS.RATIO_MIN, LENGTH_LIMITS.RATIO_MAX] } },
        ratio_silver: { rules: { number: true, range: [LENGTH_LIMITS.RATIO_MIN, LENGTH_LIMITS.RATIO_MAX] } },
        ratio_bronze: { rules: { number: true, range: [LENGTH_LIMITS.RATIO_MIN, LENGTH_LIMITS.RATIO_MAX] } },
        frozen_minute: { rules: { number: true, range: [LENGTH_LIMITS.FROZEN_MIN, LENGTH_LIMITS.FROZEN_MAX] } },
        frozen_after: { rules: { number: true, range: [LENGTH_LIMITS.FROZEN_MIN, LENGTH_LIMITS.FROZEN_MAX] } },
        'language[]': { rules: { required: true } }
    };
    InitContestFormValidation('#contest_edit_form', function(rules) {
        return Object.assign({}, rules, fieldConfigs);
    });
    // ========================================
    // 键盘快捷键
    // ========================================
    // 键盘快捷键：Ctrl+S 保存表单
    $(window).keydown(function(e) {
        if (e.keyCode == 83 && e.ctrlKey) {
            e.preventDefault();
            const clickEvent = new MouseEvent('click', { bubbles: true, cancelable: true });
            $('#submit_button')[0].dispatchEvent(clickEvent);
        }
    });
});
