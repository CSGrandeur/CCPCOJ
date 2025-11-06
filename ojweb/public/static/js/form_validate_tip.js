/**
 * Bootstrap 5 原生表单验证工具
 * 纯原生实现，不依赖任何其他框架
 */

// 使用 IIFE 避免全局污染和重复声明
(function() {
    'use strict';
    
    // 防止重复引入
    if (typeof window.FormValidationTip !== 'undefined') {
        // console.warn('form_validate_tip.js already loaded');
        return;
    }

/**
 * 验证规则定义
 */
const FormValidationRules = {
    required: (value) => value.trim() !== '',
    maxlength: (value, max) => value.length <= max,
    minlength: (value, min) => value.length >= min,
    email: (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value),
    number: (value) => !isNaN(value) && !isNaN(parseFloat(value)),
    digits: (value) => /^\d+$/.test(value),
    url: (value) => {
        try {
            new URL(value);
            return true;
        } catch {
            return false;
        }
    },
    pattern: (value, regex) => regex.test(value),
    range: (value, min, max) => {
        const num = parseFloat(value);
        return num >= min && num <= max;
    }
};

/**
 * 创建双语验证消息
 * @param {string} chineseMsg - 中文消息
 * @param {string} englishMsg - 英文消息
 * @returns {string} HTML格式的双语消息
 */
function createBilingualMessage(chineseMsg, englishMsg) {
    return `${chineseMsg}<span class="en-text"> ${englishMsg}</span>`;
}

/**
 * 生成默认的双语验证消息
 * @param {string} rule - 验证规则名称
 * @param {Array} params - 规则参数
 * @param {string} fieldName - 字段名称（可选）
 * @returns {string} HTML格式的双语消息
 */
function generateDefaultMessage(rule, params = [], fieldName = '') {
    const fieldLabel = fieldName || '此字段';
    
    const defaultMessages = {
        required: {
            zh: `${fieldLabel}不能为空`,
            en: `Required`
        },
        minlength: {
            zh: `${fieldLabel}不能少于${params[0]}个字符`,
            en: `Must be at least ${params[0]} characters`
        },
        maxlength: {
            zh: `${fieldLabel}不能超过${params[0]}个字符`,
            en: `Cannot exceed ${params[0]} characters`
        },
        min: {
            zh: `${fieldLabel}不能小于${params[0]}`,
            en: `Must be ≥ ${params[0]}`
        },
        max: {
            zh: `${fieldLabel}不能大于${params[0]}`,
            en: `Must be ≤ ${params[0]}`
        },
        range: {
            zh: `${fieldLabel}必须在${params[0]}到${params[1]}之间`,
            en: `Must be between ${params[0]} and ${params[1]}`
        },
        email: {
            zh: `请输入有效的邮箱地址`,
            en: `Invalid email`
        },
        number: {
            zh: `请输入有效的数字`,
            en: `Invalid number`
        },
        digits: {
            zh: `请输入数字`,
            en: `Digits only`
        },
        url: {
            zh: `请输入有效的网址`,
            en: `Invalid URL`
        },
        pattern: {
            zh: `${fieldLabel}格式不正确`,
            en: `Invalid format`
        }
    };
    
    const message = defaultMessages[rule];
    if (message) {
        return createBilingualMessage(message.zh, message.en);
    }
    
    // 如果没有找到默认消息，返回通用消息
    return createBilingualMessage(`${fieldLabel}验证失败`, `${fieldLabelEn} validation failed`);
}

/**
 * 验证规则模板
 */
const ValidationTemplates = {
    required: (chineseLabel, englishLabel) => ({
        rule: 'required',
        message: createBilingualMessage(
            `${chineseLabel}不能为空`,
            `${englishLabel} is required`
        )
    }),
    maxLength: (maxLength, chineseLabel, englishLabel) => ({
        rule: 'maxlength',
        params: [maxLength],
        message: createBilingualMessage(
            `${chineseLabel}不能超过${maxLength}个字符`,
            `${englishLabel} cannot exceed ${maxLength} characters`
        )
    }),
    minLength: (minLength, chineseLabel, englishLabel) => ({
        rule: 'minlength',
        params: [minLength],
        message: createBilingualMessage(
            `${chineseLabel}不能少于${minLength}个字符`,
            `${englishLabel} must be at least ${minLength} characters`
        )
    }),
    email: (chineseLabel, englishLabel) => ({
        rule: 'email',
        message: createBilingualMessage(
            `请输入有效的${chineseLabel}`,
            `Please enter a valid ${englishLabel}`
        )
    }),
    number: (chineseLabel, englishLabel) => ({
        rule: 'number',
        message: createBilingualMessage(
            `请输入有效的${chineseLabel}`,
            `Please enter a valid ${englishLabel}`
        )
    }),
    digits: (chineseLabel, englishLabel) => ({
        rule: 'digits',
        message: createBilingualMessage(
            `请输入数字`,
            `Please enter digits only`
        )
    }),
    url: (chineseLabel, englishLabel) => ({
        rule: 'url',
        message: createBilingualMessage(
            `请输入有效的${chineseLabel}`,
            `Please enter a valid ${englishLabel}`
        )
    }),
    range: (min, max, chineseLabel, englishLabel) => ({
        rule: 'range',
        params: [min, max],
        message: createBilingualMessage(
            `${chineseLabel}必须在${min}到${max}之间`,
            `${englishLabel} must be between ${min} and ${max}`
        )
    }),
    pattern: (regex, chineseLabel, englishLabel) => ({
        rule: 'pattern',
        params: [regex],
        message: createBilingualMessage(
            `${chineseLabel}格式不正确`,
            `Invalid ${englishLabel} format`
        )
    })
};

/**
 * 验证单个字段
 * @param {HTMLElement} element - 表单元素
 * @param {Array} rules - 验证规则数组
 * @returns {Object} 验证结果 {valid: boolean, message: string}
 */
function validateField(element, rules) {
    // 跳过隐藏和禁用的字段
    if (element.disabled || element.hidden || element.style.display === 'none' || element.style.visibility === 'hidden') {
        return { valid: true, message: '' };
    }
    
    const value = element.value || '';
    
    for (const ruleConfig of rules) {
        const { rule, params = [], message } = ruleConfig;
        
        // 支持自定义验证函数
        if (rule === 'custom' && typeof params[0] === 'function') {
            const customValidator = params[0];
            const isValid = customValidator(value, element);
            if (!isValid) {
                return { valid: false, message };
            }
            continue;
        }
        
        if (FormValidationRules[rule]) {
            // 对于非必填字段，如果值为空则跳过验证
            if (rule !== 'required' && value.trim() === '') {
                continue;
            }
            
            const isValid = FormValidationRules[rule](value, ...params);
            if (!isValid) {
                return { valid: false, message };
            }
        }
    }
    
    return { valid: true, message: '' };
}

/**
 * 显示字段错误
 * @param {HTMLElement} element - 表单元素
 * @param {string} message - 错误消息
 */
function showFieldError(element, message) {
    // 移除现有样式
    element.classList.remove('is-valid');
    element.classList.add('is-invalid');
    
    // 清除现有的错误提示
    clearFieldError(element);
    
    // 创建内联错误消息
    const errorId = `error-${element.name || element.id || 'field'}`;
    
    // 查找或创建错误消息容器
    let errorContainer = document.getElementById(errorId);
    if (!errorContainer) {
        errorContainer = document.createElement('div');
        errorContainer.id = errorId;
        errorContainer.className = 'invalid-feedback d-block';
        errorContainer.style.fontSize = '0.875em';
        errorContainer.style.color = '#dc3545';
        errorContainer.style.marginTop = '0.25rem';
        
        // 将错误消息插入到字段后面
        element.parentNode.insertBefore(errorContainer, element.nextSibling);
    }
    
    // 设置错误消息内容
    errorContainer.innerHTML = message;
    errorContainer.style.display = 'block';
    
    // 尝试使用 Bootstrap Tooltip 作为备选方案
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        try {
            // 销毁现有tooltip
            const existingTooltip = bootstrap.Tooltip.getInstance(element);
            if (existingTooltip) {
                existingTooltip.dispose();
            }
            
            // 创建新tooltip（作为悬停提示）
            const tooltip = new bootstrap.Tooltip(element, {
                trigger: 'hover focus',
                placement: 'bottom',
                html: true,
                title: message,
                container: 'body'
            });
        } catch (error) {
            console.warn('Bootstrap Tooltip failed:', error);
        }
    }
}

/**
 * 清除字段错误
 * @param {HTMLElement} element - 表单元素
 */
function clearFieldError(element) {
    // 移除样式
    element.classList.remove('is-invalid');
    element.classList.add('is-valid');
    
    // 清除内联错误消息
    const errorId = `error-${element.name || element.id || 'field'}`;
    const errorContainer = document.getElementById(errorId);
    if (errorContainer) {
        errorContainer.style.display = 'none';
        errorContainer.innerHTML = '';
    }
    
    // 销毁tooltip
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        try {
            const tooltip = bootstrap.Tooltip.getInstance(element);
            if (tooltip) {
                tooltip.dispose();
            }
        } catch (error) {
            console.warn('Error disposing tooltip:', error);
        }
    }
}

/**
 * 初始化表单验证
 * @param {string} formSelector - 表单选择器
 * @param {Object} fieldConfigs - 字段配置对象
 * @param {Function} submitHandler - 提交处理函数
 * @param {Object} options - 额外选项
 */
function initFormValidation(formSelector, fieldConfigs = {}, submitHandler = null, options = {}) {
    const form = document.querySelector(formSelector);
    if (!form) {
        console.warn('Form not found:', formSelector);
        return;
    }

    const config = {
        tooltipPlacement: 'bottom',
        ...options
    };

    // 存储字段验证规则
    const fieldRules = new Map();
    
    // 初始化字段规则
    Object.keys(fieldConfigs).forEach(fieldName => {
        const fieldConfig = fieldConfigs[fieldName];
        const rules = [];
        
        // 处理规则配置
        if (fieldConfig.rules) {
            Object.keys(fieldConfig.rules).forEach(ruleName => {
                const ruleValue = fieldConfig.rules[ruleName];
                const params = Array.isArray(ruleValue) ? ruleValue : [ruleValue];
                
                // 优先使用自定义消息，否则生成默认消息
                let message;
                if (fieldConfig.messages && fieldConfig.messages[ruleName]) {
                    message = fieldConfig.messages[ruleName];
                } else {
                    // 尝试从字段的placeholder或name获取字段标签
                    const element = form.querySelector(`[name="${fieldName}"]`);
                    let fieldLabel = '';
                    if (element) {
                        const placeholder = element.getAttribute('placeholder');
                        if (placeholder) {
                            // 从placeholder中提取中文标签（假设中文在前）
                            const match = placeholder.match(/^([^<]+)/);
                            if (match) {
                                fieldLabel = match[1].trim();
                            }
                        }
                    }
                    message = generateDefaultMessage(ruleName, params, fieldLabel);
                }
                
                rules.push({
                    rule: ruleName,
                    params: params,
                    message: message
                });
            });
        }
        
        fieldRules.set(fieldName, rules);
    });

    // 为每个字段添加事件监听器
    Object.keys(fieldConfigs).forEach(fieldName => {
        const element = form.querySelector(`[name="${fieldName}"]`);
        if (!element) return;

        // 实时验证
        element.addEventListener('blur', () => {
            const rules = fieldRules.get(fieldName) || [];
            const result = validateField(element, rules);
            
            if (result.valid) {
                clearFieldError(element);
            } else {
                showFieldError(element, result.message);
            }
        });

        // 输入时清除错误状态
        element.addEventListener('input', () => {
            if (element.classList.contains('is-invalid')) {
                clearFieldError(element);
            }
        });
    });

    // 表单提交验证
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        
        let isFormValid = true;
        const firstInvalidField = null;

        // 验证所有字段
        Object.keys(fieldConfigs).forEach(fieldName => {
            const element = form.querySelector(`[name="${fieldName}"]`);
            if (!element) return;

            const rules = fieldRules.get(fieldName) || [];
            const result = validateField(element, rules);
            
            if (!result.valid) {
                isFormValid = false;
                showFieldError(element, result.message);
                
                // 聚焦到第一个无效字段
                if (!firstInvalidField) {
                    element.focus();
                }
            } else {
                clearFieldError(element);
            }
        });

        // 如果表单有效且提供了提交处理函数，则执行
        if (isFormValid && submitHandler && typeof submitHandler === 'function') {
            submitHandler(form);
        }
    });

    return form;
}

/**
 * 快速初始化常用表单验证
 * @param {string} formSelector - 表单选择器
 * @param {Object} fieldConfigs - 字段配置对象
 * @param {Function} submitHandler - 提交处理函数
 * @param {Object} options - 额外选项
 */
function initCommonFormValidation(formSelector, fieldConfigs = {}, submitHandler = null, options = {}) {
    return initFormValidation(formSelector, fieldConfigs, submitHandler, options);
}

/**
 * 手动验证表单
 * @param {string} formSelector - 表单选择器
 * @returns {boolean} 表单是否有效
 */
function validateForm(formSelector) {
    const form = document.querySelector(formSelector);
    if (!form) return false;

    let isFormValid = true;
    const invalidFields = [];

    // 查找所有有验证规则的字段
    const inputs = form.querySelectorAll('input, textarea, select');
    
    inputs.forEach(input => {
        const fieldName = input.name;
        if (!fieldName) return;

        // 这里需要从全局配置中获取规则，简化处理
        const rules = window.formValidationRules && window.formValidationRules.get(fieldName);
        if (!rules) return;

        const result = validateField(input, rules);
        
        if (!result.valid) {
            isFormValid = false;
            invalidFields.push(input);
            showFieldError(input, result.message);
        } else {
            clearFieldError(input);
        }
    });

    // 聚焦到第一个无效字段
    if (invalidFields.length > 0) {
        invalidFields[0].focus();
    }

    return isFormValid;
}

/**
 * 清除表单验证状态
 * @param {string} formSelector - 表单选择器
 */
function clearFormValidation(formSelector) {
    const form = document.querySelector(formSelector);
    if (!form) return;

    const inputs = form.querySelectorAll('input, textarea, select');
    
    inputs.forEach(input => {
        clearFieldError(input);
    });
}


    // 导出到全局对象，防止重复引入
    window.FormValidationTip = {
        initFormValidation,
        initCommonFormValidation,
        createBilingualMessage,
        generateDefaultMessage,
        ValidationTemplates,
        validateForm,
        clearFormValidation,
        FormValidationRules,
        showFieldError,
        clearFieldError,
        validateField
    };

    // 导出函数（如果使用模块系统）
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = window.FormValidationTip;
    }

})(); // IIFE 结束