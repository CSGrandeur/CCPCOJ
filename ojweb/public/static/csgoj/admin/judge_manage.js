// **************************************************
// 评测机列表
var table = $('#judger_list_table');

// 更新评测机数量显示
function updateJudgerCount() {
    const table = $('#judger_list_table');
    const tableData = table.bootstrapTable('getData');
    const count = tableData.length;
    const maxCount = 99;
    $('#judger_count_display').text(`${count}/${maxCount}`);
    
    // 如果达到上限，禁用添加按钮
    if (count >= maxCount) {
        $('#add_judger_btn').prop('disabled', true).addClass('disabled');
    } else {
        $('#add_judger_btn').prop('disabled', false).removeClass('disabled');
    }
}

// Formatter函数
function FormatterJudgerIndex(value, row, index, field) {
    return `
        <div class="judger-index-container" 
             title="双击复制一个新评测机 / Double click to copy a new judger"
             data-user-id="${row.user_id}">
            <i class="bi bi-copy judger-copy-icon"></i>
            <span class="judger-index-number">${index + 1}</span>
        </div>
    `;
}

function FormatterJudgerId(value, row, index, field) {
    const password = row.password || '';
    return `
        <div class="judger-id-container">
            <a href='javascript:void(0)' 
               onclick='openJudgerConfig(${JSON.stringify(row).replace(/"/g, '&quot;')})' 
               class="judger-id-link"
               data-user-id="${value}"
               title='点击配置评测机 / Click to configure judger'>
                <i class="bi bi-gear-fill me-1"></i>
                <span class="judger-id-text">${value}</span>
            </a>
            <div class="judger-password-display">${password}</div>
        </div>
    `;
}

function FormatterJudgerProList(value, row, index, field) {
    if (!value) {
        return '<span class="badge bg-secondary">无限制<span class="en-text">No Limit</span></span>';
    }

    const proList = value.split(',').filter(item => item.trim() !== '');

    if (proList.length === 0) {
        return '<span class="badge bg-secondary">无限制<span class="en-text">No Limit</span></span>';
    }

    // 根据题目数量决定显示策略
    let problemBadges = '';
    let countBadge = '';

    if (proList.length <= 2) {
        // 1-2个题目：直接显示所有题目
        proList.forEach(proId => {
            const trimmedId = proId.trim();
            if (isValidProblemId(trimmedId)) {
                problemBadges += `<span class="badge bg-primary" title="题目 ${trimmedId}">${trimmedId}</span>`;
            } else {
                problemBadges += `<span class="badge bg-warning" title="题目 ${trimmedId} (格式异常)">${trimmedId}</span>`;
            }
        });
    } else if (proList.length <= 5) {
        // 3-5个题目：显示前2个
        const displayList = proList.slice(0, 2);
        displayList.forEach(proId => {
            const trimmedId = proId.trim();
            if (isValidProblemId(trimmedId)) {
                problemBadges += `<span class="badge bg-primary" title="题目 ${trimmedId}">${trimmedId}</span>`;
            } else {
                problemBadges += `<span class="badge bg-warning" title="题目 ${trimmedId} (格式异常)">${trimmedId}</span>`;
            }
        });
        countBadge = `<span class="badge bg-info pro-count-badge" title="点击查看完整列表 / Click to view full list" onclick="showProblemList('${value}', ${proList.length}, '${row.user_id}')">+${proList.length - 2}</span>`;
    } else {
        // 6个以上题目：显示前1个
        const firstPro = proList[0];
        const trimmedId = firstPro.trim();
        if (/^\d{4}$/.test(trimmedId)) {
            problemBadges += `<span class="badge bg-primary" title="题目 ${trimmedId}">${trimmedId}</span>`;
        } else {
            problemBadges += `<span class="badge bg-warning" title="题目 ${trimmedId} (格式异常)">${trimmedId}</span>`;
        }
        countBadge = `<span class="badge bg-info pro-count-badge" title="点击查看完整列表 / Click to view full list" onclick="showProblemList('${value}', ${proList.length}, '${row.user_id}')">+${proList.length - 1}</span>`;
    }

    return `
        <div class="pro-list-compact">
            <div class="pro-badges">${problemBadges}</div>
            ${countBadge ? `<div class="pro-count">${countBadge}</div>` : ''}
        </div>
    `;
}

function FormatterJudgerFlgWhite(value, row, index, field) {
    if (value == 0) {
        return '<i class="bi bi-shield-check text-success" title="白名单模式 - 只允许指定题目 / Whitelist Mode - Only specified problems allowed"></i>';
    } else {
        return '<i class="bi bi-shield-exclamation text-warning" title="黑名单模式 - 禁止指定题目 / Blacklist Mode - Specified problems forbidden"></i>';
    }
}

function FormatterJudgerLanguage(value, row, index, field) {
    if (!value || !Array.isArray(value) || value.length === 0) {
        return '<span class="badge bg-secondary">无限制<span class="en-text">No Limit</span></span>';
    }

    // 获取语言配置
    const languageConfig = JSON.parse($('#oj_language_data').val());
    const languageKeys = Object.keys(languageConfig);

    // 如果只有一个语言，直接显示
    if (value.length === 1) {
        const lang = value[0];
        let badgeClass = 'bg-secondary';
        let displayName = lang.substring(0, 2).toUpperCase();

        if (lang === 'UNKNOWN') {
            badgeClass = 'bg-danger';
            displayName = '未知';
        } else {
            const isKnownLanguage = languageKeys.some(key => languageConfig[key] === lang);
            if (isKnownLanguage) {
                const langIndex = languageKeys.findIndex(key => languageConfig[key] === lang);
                const colorClasses = ['bg-primary', 'bg-info', 'bg-warning', 'bg-success', 'bg-danger', 'bg-dark'];
                badgeClass = colorClasses[langIndex % colorClasses.length];
            }
        }

        return `<span class="badge ${badgeClass} language-badge" 
                     title="${lang}" 
                     onclick="showLanguageList('${value.join(',')}', ${value.length})">${displayName}</span>`;
    }

    // 多个语言时，每行最多显示2个语言徽章
    let displayHtml = '';
    const maxDisplay = 4; // 最多显示4个语言（2行 × 2个）

    // 将语言分组，每行2个
    const displayLanguages = value.slice(0, maxDisplay);
    const rows = [];
    for (let i = 0; i < displayLanguages.length; i += 2) {
        rows.push(displayLanguages.slice(i, i + 2));
    }

    // 生成每行的HTML
    rows.forEach((rowLanguages, rowIndex) => {
        displayHtml += '<div class="language-row">';
        rowLanguages.forEach(lang => {
            let badgeClass = 'bg-secondary';
            let displayName = lang.substring(0, 2).toUpperCase();

            if (lang === 'UNKNOWN') {
                badgeClass = 'bg-danger';
                displayName = '未知';
            } else {
                const isKnownLanguage = languageKeys.some(key => languageConfig[key] === lang);
                if (isKnownLanguage) {
                    const langIndex = languageKeys.findIndex(key => languageConfig[key] === lang);
                    const colorClasses = ['bg-primary', 'bg-info', 'bg-warning', 'bg-success', 'bg-danger', 'bg-dark'];
                    badgeClass = colorClasses[langIndex % colorClasses.length];
                }
            }

            displayHtml += `<span class="badge ${badgeClass} language-badge-compact" 
                                 title="${lang}">${displayName}</span>`;
        });
        displayHtml += '</div>';
    });

    // 如果语言数量超过显示限制，添加省略号
    if (value.length > maxDisplay) {
        displayHtml += `<div class="language-row">
            <span class="badge bg-info language-badge-compact" 
                  title="点击查看完整语言列表 / Click to view full language list"
                  onclick="showLanguageList('${value.join(',')}', ${value.length})">+${value.length - maxDisplay}</span>
        </div>`;
    } else if (value.length > 1) {
        // 添加点击查看完整列表的提示
        displayHtml += `<div class="language-row">
            <span class="badge bg-light text-dark language-badge-compact" 
                  title="点击查看完整语言列表 / Click to view full language list"
                  onclick="showLanguageList('${value.join(',')}', ${value.length})">⋯</span>
        </div>`;
    }

    return displayHtml;
}

function FormatterJudgerAccessTime(value, row, index, field) {
    let statusIcon = '';
    let statusTitle = '';

    if (!value) {
        statusIcon = '<i class="bi bi-clock text-muted"></i>';
        statusTitle = '待连接 - 评测机未连接 / Pending Connection - Judger not connected';
    } else {
        // 解析时间
        let fixedValue = value.replace(/ /, 'T').replace(/^(\d{4}-\d{2}-\d{2})[ -](\d{2})-(\d{2})-(\d{2})$/, '$1T$2:$3:$4');
        let lastTime = new Date(fixedValue);
        let now = new Date();
        let diffMs = now - lastTime;
        let diffMin = diffMs / 1000 / 60;

        if (diffMin <= 5) {
            statusIcon = '<i class="bi bi-wifi text-success"></i>';
            statusTitle = `活跃 - 最后连接：${value} / Active - Last connected: ${value}`;
        } else if (diffMin <= 30) {
            statusIcon = '<i class="bi bi-wifi-off text-warning"></i>';
            statusTitle = `警告 - 最后连接：${value} (${Math.round(diffMin)}分钟前) / Warning - Last connected: ${value} (${Math.round(diffMin)} minutes ago)`;
        } else {
            statusIcon = '<i class="bi bi-wifi-off text-danger"></i>';
            statusTitle = `断连 - 最后连接：${value} (${Math.round(diffMin)}分钟前) / Disconnected - Last connected: ${value} (${Math.round(diffMin)} minutes ago)`;
        }
    }

    // 构建完整的title，包含连接状态和操作提示
    const actionHint = '\n\n单击：查看登录日志 | 双击：生成部署脚本 | 三击：复制JSON配置\nClick: View log | Double: Generate script | Triple: Copy JSON';
    const fullTitle = (statusTitle || '从未连接 / Never connected') + actionHint;
    
    // 只保留状态图标，title显示连接状态和操作提示
    return `
        <div class="connection-container" title="${fullTitle}">
            <span class="connection-status-icon">${statusIcon}</span>
        </div>
    `;
}

function FormatterJudgerDefunct(value, row, index, field) {
    // value: 0=启用, 1=禁用
    const isEnabled = value == 0;
    const statusText = isEnabled ? '已启用 / On' : '已停用 / Off';
    const iconClass = isEnabled ? 'bi-toggle-on text-success' : 'bi-toggle-off text-muted';

    return `
        <div class="status-icon-container" 
             title="${statusText}"
             onclick="toggleJudgerStatus('${row.user_id}', ${!isEnabled})"
             data-user-id="${row.user_id}"
             data-current-status="${isEnabled}">
            <i class="bi ${iconClass} status-icon"></i>
        </div>
    `;
}

function FormatterJudgerDelete(value, row, index, field) {
    if (row.can_delete) {
        return `<div class="delete-icon-container" title="双击删除 / Double Click to Delete">
                    <i class="bi bi-trash delete-icon"></i>
                </div>`;
    }
    return '-';
}

// 初始化语言筛选选项
function initLanguageFilter() {
    const languageConfig = JSON.parse($('#oj_language_data').val());
    const languageFilter = $('#judger_language_filter');

    // 清空现有选项（保留"全部"选项）
    languageFilter.find('option:not(:first)').remove();

    // 添加编程语言选项
    Object.keys(languageConfig).forEach(langKey => {
        const langName = languageConfig[langKey];
        languageFilter.append(`<option value="${langName}">${langName}</option>`);
    });
}

// 初始化语言筛选
initLanguageFilter();


// 表格加载完成后更新数量显示
table.on('load-success.bs.table', function() {
    // 调用父页面的更新函数
    if (typeof updateJudgerCount === 'function') {
        updateJudgerCount();
    }
});

initBootstrapTableClientToolbar({
    tableId: 'judger_list_table',
    prefix: 'judger',
    filterSelectors: ['flg_white', 'defunct', 'langlist'],
    searchInputId: 'judger_search_input',
    searchFields: {
        user_id: 'user_id',
        pro_list: 'pro_list'
    }
});


// 检查评测机是否在半小时内活跃
function isJudgerActive(row) {
    if (!row.accesstime) {
        return false;
    }

    // 解析时间
    let fixedValue = row.accesstime.replace(/ /, 'T').replace(/^(\d{4}-\d{2}-\d{2})[ -](\d{2})-(\d{2})-(\d{2})$/, '$1T$2:$3:$4');
    let lastTime = new Date(fixedValue);
    let now = new Date();
    let diffMs = now - lastTime;
    let diffMin = diffMs / 1000 / 60;

    return diffMin <= 30; // 30分钟内为活跃
}

// 删除评测机权限
function deleteJudgerPrivilege(user_id) {
    $.post(
        '/admin/judger/judger_delete_ajax', {
            'user_id': user_id
        },
        function(ret) {
            if (ret.code == 1) {
                alerty.success(`评测机 ${user_id} 删除成功`, `Judger ${user_id} deleted successfully`);
                // 从表格中移除该行数据
                table.bootstrapTable('remove', {
                    field: 'user_id',
                    values: [user_id]
                });
                // 更新序号列
                if (typeof updateSerialNumbers === 'function') {
                    updateSerialNumbers();
                }
                // 更新数量显示
                if (typeof updateJudgerCount === 'function') {
                    updateJudgerCount();
                }
            } else {
                alerty.error(ret.msg);
            }
            return false;
        }
    );
}


// 显示题目列表弹窗
function showProblemList(proListStr, totalCount, user_id) {
    if (!proListStr) {
        alerty.alert('无题目限制', 'No problem restrictions');
        return;
    }

    const proList = proListStr.split(',').filter(item => item.trim() !== '');

    if (proList.length === 0) {
        alerty.alert('无题目限制', 'No problem restrictions');
        return;
    }

    // 通过 AJAX 获取题目标题信息
    $.ajax({
        url: '/admin/judger/get_judger_pro_list_ajax',
        type: 'POST',
        data: {
            user_id: user_id
        },
        dataType: 'json',
        success: function(response) {
            if (response.code === 1) {
                const proList = response.data.pro_list || [];
                const problemTitles = response.data.problem_titles || {};
                const count = response.data.count || 0;
                
                const problemListHtml = generateProblemListHtml(proList, problemTitles, count, user_id);
                
                alerty.alert({
                    message: problemListHtml,
                    message_en: problemListHtml
                });
            } else {
                alerty.error('获取题目列表失败', 'Failed to get problem list');
            }
        },
        error: function(xhr, status, error) {
            handleAjaxError(xhr, status, error, '获取题目列表');
        }
    });
}

// 切换评测机启用状态
function toggleJudgerStatus(user_id, isEnabled) {
    const newStatus = isEnabled ? 0 : 1; // 0=启用, 1=停用

    $.ajax({
        url: '/admin/judger/judger_config_ajax',
        type: 'POST',
        data: {
            user_id: user_id,
            defunct: newStatus
        },
        dataType: 'json',
        success: function(response) {
            if (response.code === 1) {
                const statusText = isEnabled ? '启用' : '停用';
                const statusTextEn = isEnabled ? 'enabled' : 'disabled';
                alerty.success(`评测机 ${user_id} 已${statusText}`, `Judger ${user_id} ${statusTextEn}`);

                // 更新按钮状态
                updateStatusButton(user_id, isEnabled);

                // 更新表格数据
                const tableData = table.bootstrapTable('getData');
                const targetRow = tableData.find(row => row.user_id === user_id);
                if (targetRow) {
                    targetRow.defunct = newStatus;
                    table.bootstrapTable('refresh');
                }
            } else {
                alerty.error(response.msg || '状态更新失败', 'Failed to update status');
            }
        },
        error: function(xhr, status, error) {
            alerty.error('状态更新失败：' + error, 'Failed to update status: ' + error);
        }
    });
}

// 更新状态图标的视觉状态
function updateStatusButton(user_id, isEnabled) {
    const container = document.querySelector(`.status-icon-container[data-user-id="${user_id}"]`);
    if (!container) return;

    const statusText = isEnabled ? '已启用 / Enabled' : '已禁用 / Disabled';
    const iconClass = isEnabled ? 'bi-toggle-on text-success' : 'bi-toggle-off text-muted';

    // 更新容器属性
    container.setAttribute('title', statusText);
    container.setAttribute('data-current-status', isEnabled);

    // 更新图标
    const icon = container.querySelector('.status-icon');
    if (icon) {
        icon.className = `bi ${iconClass} status-icon`;
    }
}

// 连接列点击计数器
var connectionClickCount = 0;
var connectionClickTimer = null;
var connectionClickRow = null;

// 表格单击事件
table.on('click-cell.bs.table', function(e, field, value, row, $element) {
    if (field == 'accesstime') {
        // 重置计数器
        if (connectionClickRow !== row.user_id) {
            connectionClickCount = 0;
            connectionClickRow = row.user_id;
        }
        
        connectionClickCount++;
        
        // 清除之前的定时器
        if (connectionClickTimer) {
            clearTimeout(connectionClickTimer);
        }
        
        // 设置延迟执行，等待可能的双击或三击
        connectionClickTimer = setTimeout(() => {
            if (connectionClickCount === 1) {
                // 单击：查看登录日志
                showJudgerLoginLog(row);
            } else if (connectionClickCount === 2) {
                // 双击：生成部署脚本
                generateDeployScript(row);
            } else if (connectionClickCount >= 3) {
                // 三击或更多：复制配置JSON
                copyJudgerConfig(row);
            }
            
            // 重置计数器
            connectionClickCount = 0;
            connectionClickRow = null;
        }, 300); // 300ms延迟，足够检测双击和三击
    }
});

table.on('dbl-click-cell.bs.table', function(e, field, value, row, $element) {
    if (field == 'delete') {
        // 双击序号列，基于该评测机配置创建新评测机
        if (row.can_delete) {
            // 检查是否活跃
            const isActive = isJudgerActive(row);

            if (isActive) {
                // 活跃状态需要额外确认
                alerty.confirm({
                    message: '该评测机在半小时内活跃，确认删除？',
                    message_en: 'This judger was active within 30 minutes, confirm deletion?',
                    callback: function() {
                        deleteJudgerPrivilege(row.user_id);
                    },
                    callbackCancel: function() {
                        alerty.message('操作已取消', 'Operation cancelled');
                    }
                });
            } else {
                // 非活跃状态直接删除
                deleteJudgerPrivilege(row.user_id);
            }
        }
    } else if (field == 'serial') {
        // 双击序号列，基于该评测机配置创建新评测机
        createJudgerFromConfig(row);
    }
});
// 通用添加评测机函数
function addJudgerWithConfig(configData, successMessage, successMessageEn) {
    // 获取当前表格数据
    const table = $('#judger_list_table');
    const tableData = table.bootstrapTable('getData');
    const existingUserIds = tableData.map(r => r.user_id);

    // 获取前缀输入框的值
    const prefix = $('#judger_prefix_input').val().trim();
    const finalPrefix = prefix || 'j'; // 默认前缀为 'j'

    // 生成新的评测机ID（基于前缀输入框）
    let newUserId = '';
    for (let i = 1; i <= 99; i++) {
        const candidateId = finalPrefix + String(i).padStart(2, '0');
        if (!existingUserIds.includes(candidateId)) {
            newUserId = candidateId;
            break;
        }
    }

    if (!newUserId) {
        alerty.error('无法生成可用的评测机ID，请尝试其他前缀', 'Cannot generate available judger ID, please try another prefix');
        return;
    }

    // 验证用户ID长度
    if (newUserId.length > 4) {
        alerty.error('生成的评测机ID长度超过4位，请使用更短的前缀', 'Generated judger ID exceeds 4 characters, please use a shorter prefix');
        return;
    }

    // 检查总数限制
    if (existingUserIds.length >= 99) {
        alerty.error('评测机总数已达到上限（99个），无法继续添加', 'Maximum number of judgers reached (99), cannot add more');
        return;
    }

    // 转换语言列表为语言掩码
    let languageMask = 0;
    if (configData.language && Array.isArray(configData.language)) {
        const languageConfig = JSON.parse($('#oj_language_data').val());
        configData.language.forEach(langName => {
            // 根据语言名称找到对应的key
            Object.keys(languageConfig).forEach(langKey => {
                if (languageConfig[langKey] === langName) {
                    languageMask |= (1 << parseInt(langKey));
                }
            });
        });
    }

        // 提交创建新评测机
        $.ajax({
            url: '/admin/judger/judger_add_ajax',
            type: 'POST',
            data: {
                user_id: newUserId,
                pro_list: configData.pro_list || '',
                flg_white: configData.flg_white || 0,
                defunct: configData.defunct || 0,
                language: languageMask,
                custom_password: configData.custom_password || '' // 添加自定义密码
            },
        dataType: 'json',
        success: function(response) {
            if (response.code === 1) {
                const userData = response.data;

                // 将新用户数据添加到表格中
                if (userData) {
                    // 后端已经返回了前端友好的字段名，直接使用
                    const newRow = {
                        user_id: userData.user_id,
                        pro_list: userData.pro_list || '',
                        flg_white: userData.flg_white || 0,
                        defunct: userData.defunct || 0,
                        langlist: userData.langlist || [],
                        accesstime: userData.accesstime || null,
                        ip: userData.ip || '',
                        password: userData.password || '',
                        can_delete: userData.can_delete || true
                    };

                    // 添加到表格数据中
                    table.bootstrapTable('prepend', newRow);
                }

                // 显示成功消息
                alerty.success(successMessage, successMessageEn);

                // 更新数量显示
                if (typeof updateJudgerCount === 'function') {
                    updateJudgerCount();
                }
            } else {
                alerty.error(response.info || '创建失败', 'Failed to create judger');
            }
        },
        error: function(xhr, status, error) {
            alerty.error('创建评测机失败：' + error, 'Failed to create judger: ' + error);
        }
    });
}

// 基于现有评测机配置创建新评测机
function createJudgerFromConfig(row) {
    // 获取密码输入框的值
    let customPassword = $('#judger_password_input').val().trim();
    
    // 如果密码输入框为空，使用该行judger的密码
    if (!customPassword && row.password) {
        customPassword = row.password;
        // 将密码填入密码输入框
        $('#judger_password_input').val(customPassword);
    }
    
    // 准备配置数据
    const configData = {
        pro_list: row.pro_list || '',
        flg_white: row.flg_white || 0,
        defunct: row.defunct || 0,
        language: row.langlist || [],
        custom_password: customPassword // 添加自定义密码
    };

    // 调用通用函数
    addJudgerWithConfig(
        configData,
        `基于评测机 ${row.user_id} 的配置创建了评测机`,
        `Created judger based on configuration from ${row.user_id}`
    );
}

// 填充添加评测机表单
function fillAddJudgerForm(configData) {
    // 填充题目限制
    if (configData.pro_list) {
        $('#judger_prefix_input').val(configData.pro_list);
    }

    // 填充黑白名单设置
    if (configData.flg_white !== undefined) {
        // 这里需要根据实际的表单元素来填充
        // 由于黑白名单设置可能在模态框中，这里先存储到全局变量
        window.copiedFlgWhite = configData.flg_white;
    }

    // 填充语言设置
    if (configData.language && Array.isArray(configData.language)) {
        window.copiedLanguage = configData.language;
    }

    // 填充启用状态
    if (configData.defunct !== undefined) {
        window.copiedDefunct = configData.defunct;
    }
}

// 语言掩码转换为语言列表（前端版本）
function convertLangMaskToList(langMask) {
    if (langMask < 0) return [];

    const languageConfig = JSON.parse($('#oj_language_data').val());
    const langList = [];

    // 遍历所有可能的语言位
    for (let i = 0; i < 32; i++) {
        if (langMask & (1 << i)) {
            if (languageConfig[i]) {
                langList.push(languageConfig[i]);
            } else {
                langList.push('UNKNOWN');
            }
        }
    }

    return langList;
}

// 生成部署脚本
function generateDeployScript(row) {
    const user_id = row.user_id;
    const password = row.password;

    if (!user_id || !password) {
        alerty.error('评测机信息不完整，无法生成脚本', 'Incomplete judger information, cannot generate script');
        return;
    }

    // 获取当前页面URL
    const currentUrl = window.location;
    const protocol = currentUrl.protocol; // http: 或 https:
    const hostname = currentUrl.hostname; // 域名或IP
    // 获取端口号，如果URL中没有端口则使用默认端口80（HTTP）或443（HTTPS）
    let port = currentUrl.port;
    if (!port) {
        port = protocol === 'https:' ? '443' : '80'; 
    }
    
    // 构建服务器地址（始终包含端口）
    const serverUrl = `${protocol}//${hostname}:${port}`;

    // 生成部署脚本
    const deployScript = `bash csgoj_deploy.sh judge \\
    --CSGOJ_SERVER_BASE_URL=${serverUrl} \\
    --CSGOJ_SERVER_USERNAME=${user_id} \\
    --CSGOJ_SERVER_PASSWORD=${password} \\
    --JUDGE_POD_COUNT=1`;

    // 复制到剪贴板
    ClipboardWrite(deployScript).then(success => {
        if (success) {
            alerty.success(
                '部署脚本已复制到剪贴板\n\n⚠️ 请自行确认 OJ服务器IP或域名是否能被评测机访问\n不要使用 localhost 或 127.0.0.1',
                'Deploy script copied to clipboard\n\n⚠️ Please confirm that the OJ server IP or domain can be accessed by the judger\nDo not use localhost or 127.0.0.1'
            );
        } else {
            alerty.error('复制失败，请手动复制', 'Copy failed, please copy manually');
        }
    });
}

// 复制评测机配置JSON
function copyJudgerConfig(row) {
    const user_id = row.user_id;
    const password = row.password;

    if (!user_id || !password) {
        alerty.error('评测机信息不完整，无法复制配置', 'Incomplete judger information, cannot copy configuration');
        return;
    }

    // 获取当前页面URL的base_url
    const currentUrl = window.location.origin;

    // 构建配置JSON
    const config = {
        server: {
            base_url: currentUrl,
            api_path: "/ojtool/judge2",
            user_id: user_id,
            password: password
        }
    };

    // 复制到剪贴板
    const configJson = JSON.stringify(config, null, 2);
    ClipboardWrite(configJson).then(success => {
        if (success) {
            alerty.success('评测机启动设置已复制到剪贴板', 'Judger configuration copied to clipboard');
        } else {
            alerty.error('复制失败，请手动复制', 'Copy failed, please copy manually');
        }
    });
}

// 复制Docker环境变量
function copyJudgeDockerDeployScript(row) {
    const user_id = row.user_id;
    const password = row.password;

    if (!user_id || !password) {
        alerty.error('评测机信息不完整，无法复制配置', 'Incomplete judger information, cannot copy configuration');
        return;
    }

    // 获取当前页面URL的base_url
    const currentUrl = window.location.origin;

    // 构建Docker环境变量
    const dockerEnv = [
        `-e CSGOJ_SERVER_BASE_URL=${currentUrl}`,
        `-e CSGOJ_SERVER_API_PATH=/ojtool/judge2`,
        `-e CSGOJ_SERVER_USERNAME=${user_id}`,
        `-e CSGOJ_SERVER_PASSWORD=${password}`
    ].join('\n');

    // 复制到剪贴板
    ClipboardWrite(dockerEnv).then(success => {
        if (success) {
            alerty.success('评测机启动环境变量已复制到剪贴板', 'Docker environment variables copied to clipboard');
        } else {
            alerty.error('复制失败，请手动复制', 'Copy failed, please copy manually');
        }
    });
}

// 显示语言列表
function showLanguageList(languageString, totalCount) {
    if (!languageString) {
        alerty.alert('无语言限制', 'No language restrictions');
        return;
    }

    const languages = languageString.split(',');

    if (languages.length === 0) {
        alerty.alert('无语言限制', 'No language restrictions');
        return;
    }

    // 获取语言配置
    const languageConfig = JSON.parse($('#oj_language_data').val());
    const languageKeys = Object.keys(languageConfig);

    // 格式化语言列表显示
    let languageListHtml = '<div class="language-list-detail">';
    languageListHtml += `<div class="mb-2"><strong>支持的语言 (共 ${totalCount} 个)：</strong></div>`;
    languageListHtml += '<div class="d-flex flex-wrap gap-2">';

    languages.forEach((lang, index) => {
        const trimmedLang = lang.trim();
        let badgeClass = 'bg-primary';
        let displayText = trimmedLang;

        // 检查是否为已知语言
        const isKnownLanguage = languageKeys.some(key => languageConfig[key] === trimmedLang);

        if (trimmedLang === 'UNKNOWN') {
            badgeClass = 'bg-danger';
            displayText = '未知语言';
        } else if (isKnownLanguage) {
            // 根据语言在配置中的位置分配颜色
            const langIndex = languageKeys.findIndex(key => languageConfig[key] === trimmedLang);
            const colorClasses = ['bg-primary', 'bg-info', 'bg-warning', 'bg-success', 'bg-danger', 'bg-dark'];
            badgeClass = colorClasses[langIndex % colorClasses.length];
        } else {
            badgeClass = 'bg-secondary';
        }

        languageListHtml += `<span class="badge ${badgeClass}">${displayText}</span>`;
    });

    languageListHtml += '</div>';
    languageListHtml += '</div>';

    alerty.alert({
        message: languageListHtml,
        message_en: languageListHtml
    });
}

// 显示评测机登录日志
function showJudgerLoginLog(row) {
    const user_id = row.user_id;
    
    if (!user_id) {
        alerty.error('评测机ID无效', 'Invalid judger ID');
        return;
    }

    // 显示加载状态
    alerty.info('加载登录日志...', 'Loading login logs...');

    // 请求登录日志数据
    $.ajax({
        url: '/admin/judger/judger_login_log_ajax',
        type: 'GET',
        data: {
            user_id: user_id
        },
        dataType: 'json',
        success: function(response) {
            if (response.code === 1) {
                displayLoginLogModal(user_id, response.data);
            } else {
                alerty.error(response.msg || '获取登录日志失败', 'Failed to get login logs');
            }
        },
        error: function(xhr, status, error) {
            alerty.error('获取登录日志失败：' + error, 'Failed to get login logs: ' + error);
        }
    });
}

// 显示登录日志模态框
function displayLoginLogModal(user_id, logData) {
    let logHtml = '<div class="login-log-detail">';
    logHtml += `<div class="d-flex justify-content-between align-items-center mb-3">`;
    logHtml += `<h5 class="mb-0">评测机 ${user_id} 的登录日志 (最近20次)</h5>`;
    logHtml += `<div class="d-flex gap-2">`;
    logHtml += `<button type="button" class="btn btn-outline-primary btn-sm" onclick="copyJudgerConfigFromModal('${user_id}')" title="复制配置JSON / Copy Config JSON">`;
    logHtml += `<i class="bi bi-file-earmark-code me-1"></i>JSON`;
    logHtml += `</button>`;
    logHtml += `<button type="button" class="btn btn-outline-info btn-sm" onclick="copyDockerEnvFromModal('${user_id}')" title="复制部署脚本 / Copy Deploy Script">`;
    logHtml += `<i class="bi bi-terminal me-1"></i>Deploy Script`;
    logHtml += `</button>`;
    logHtml += `</div>`;
    logHtml += `</div>`;
    
    if (logData && logData.length > 0) {
        logHtml += '<div class="table-responsive">';
        logHtml += '<table class="table table-sm table-hover">';
        logHtml += '<thead class="table-light">';
        logHtml += '<tr>';
        logHtml += '<th>序号</th>';
        logHtml += '<th>登录时间</th>';
        logHtml += '<th>IP地址</th>';
        logHtml += '<th>状态</th>';
        logHtml += '</tr>';
        logHtml += '</thead>';
        logHtml += '<tbody>';

        logData.forEach((log, index) => {
            const statusClass = log.success == 1 ? 'text-success' : 'text-danger';
            const statusText = log.success == 1 ? '成功' : '失败';
            const statusIcon = log.success == 1 ? 'bi-check-circle' : 'bi-x-circle';
            
            logHtml += '<tr>';
            logHtml += `<td>${index + 1}</td>`;
            logHtml += `<td>${log.time || '-'}</td>`;
            logHtml += `<td>${log.ip || '-'}</td>`;
            logHtml += `<td><i class="bi ${statusIcon} ${statusClass}"></i> ${statusText}</td>`;
            logHtml += '</tr>';
        });

        logHtml += '</tbody>';
        logHtml += '</table>';
        logHtml += '</div>';
    } else {
        logHtml += '<div class="text-center text-muted py-4">';
        logHtml += '<i class="bi bi-inbox fs-1"></i>';
        logHtml += '<p class="mt-2">暂无登录日志</p>';
        logHtml += '</div>';
    }

    logHtml += '</div>';
    alertify.alert(logHtml);    // TODO: 修复alerty后改回alerty
}

// 从模态框复制评测机配置JSON（复用现有函数）
function copyJudgerConfigFromModal(user_id) {
    // 从表格数据中查找对应的行
    const tableData = table.bootstrapTable('getData');
    const targetRow = tableData.find(row => row.user_id === user_id);
    
    if (targetRow) {
        copyJudgerConfig(targetRow);
    } else {
        alerty.error('未找到评测机数据', 'Judger data not found');
    }
}

// 从模态框复制Docker环境变量（复用现有函数）
function copyDockerEnvFromModal(user_id) {
    // 从表格数据中查找对应的行
    const tableData = table.bootstrapTable('getData');
    const targetRow = tableData.find(row => row.user_id === user_id);
    
    if (targetRow) {
        copyJudgeDockerDeployScript(targetRow);
    } else {
        alerty.error('未找到评测机数据', 'Judger data not found');
    }
}

// **************************************************
// 评测机参数配置

$(document).ready(function() {
    // 获取配置数据
    const configData = JSON.parse($('#judger_config_data').val());
    
    // 修改状态跟踪
    let initialConfig = null; // 保存初始配置，所有修改都与此比较
    let modifiedFields = new Set();
    
    // 动态生成配置表单
    function generateConfigForm() {
        const container = $('#config_form_container');
        let html = '';
        
        // 遍历所有配置定义
        Object.keys(configData.definitions).forEach(sectionKey => {
            const section = configData.definitions[sectionKey];
            const sectionConfig = configData.config[sectionKey];
            
            // 生成分组标题
            html += `
                <div class="mb-3">
                    <h5 class="text-primary mb-2 bilingual-inline">${section.title.cn}<span class="en-text">${section.title.en}</span></h5>
                    <div class="row g-2">
            `;
            
            // 生成字段
            Object.keys(section.fields).forEach(fieldKey => {
                const field = section.fields[fieldKey];
                const fieldValue = sectionConfig[fieldKey];
                const fieldId = `${sectionKey}_${fieldKey}`;
                const fieldName = `${sectionKey}[${fieldKey}]`;
                
                let fieldHtml = '';
                
                        if (field.type === 'switch') {
                            // 开关类型
                            // 确保fieldValue是单个值，如果是数组则取第一个值
                            const switchValue = Array.isArray(fieldValue) ? fieldValue[0] : fieldValue;
                            const titleText = `${field.description.cn} / ${field.description.en}`;
                            fieldHtml = `
                                <div class="col-6">
                                    <div class="csg-switch-container">
                                        <div class="csg-switch-setting" title="${titleText}">
                                            <div class="d-flex align-items-center">
                                                <div class="csg-switch-setting-content">
                                                    <div class="csg-switch-setting-title">${field.label.cn} <span class="en-text text-muted">${field.label.en}</span></div>
                                                </div>
                                                <div class="csg-switch-setting-control">
                                                    <input type="checkbox" class="csg-switch-input" 
                                                           data-csg-text-on="启用" data-csg-text-on-en="Enabled"
                                                           data-csg-text-off="禁用" data-csg-text-off-en="Disabled"
                                                           name="${fieldName}" id="${fieldId}" ${switchValue ? 'checked' : ''}>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modified-indicator" style="display: none;">
                                            <i class="bi bi-star-fill"></i>
                                        </div>
                                    </div>
                                </div>
                            `;
                } else if (field.type === 'select') {
                    // 选择类型
                    let optionsHtml = '';
                    // 确保fieldValue是单个值，如果是数组则取第一个值
                    const selectValue = Array.isArray(fieldValue) ? fieldValue[0] : fieldValue;
                    const titleText = `${field.description.cn} / ${field.description.en}`;
                    field.options.forEach(option => {
                        optionsHtml += `<option value="${option.value}" ${option.value === selectValue ? 'selected' : ''}>${option.label.cn}</option>`;
                    });
                    
                    fieldHtml = `
                        <div class="col-6">
                            <label for="${fieldId}" class="form-label small" title="${titleText}">${field.label.cn} <span class="en-text text-muted">${field.label.en}</span></label>
                            <div class="csg-select-container">
                                <select class="csg-select-input" name="${fieldName}" id="${fieldId}" data-csg-searchable="true" title="${titleText}">
                                    ${optionsHtml}
                                </select>
                                <div class="modified-indicator" style="display: none;">
                                    <i class="bi bi-star-fill"></i>
                                </div>
                            </div>
                        </div>
                    `;
                } else if (field.type === 'number') {
                    // 数字类型
                    const unitCn = field.unit ? `(${field.unit.cn})` : '';
                    const unitEn = field.unit ? `(${field.unit.en})` : '';
                    // 确保fieldValue是单个值，如果是数组则取第一个值
                    const numericValue = Array.isArray(fieldValue) ? fieldValue[0] : fieldValue;
                    const titleText = `${field.description.cn} / ${field.description.en}`;
                    fieldHtml = `
                        <div class="col-${getColumnClass(sectionKey, fieldKey)}">
                            <label for="${fieldId}" class="form-label small" title="${titleText}">${field.label.cn}${unitCn} <span class="en-text text-muted">${field.label.en}${unitEn}</span></label>
                            <div class="form-control-container">
                                <input type="number" class="form-control form-control-sm number-input" 
                                       name="${fieldName}" id="${fieldId}" 
                                       value="${numericValue}" 
                                       title="${titleText}"
                                       data-field-min="${field.min || ''}" 
                                       data-field-max="${field.max || ''}" 
                                       data-field-step="${field.step || ''}"
                                       data-field-unit="${field.unit ? field.unit.cn : ''}">
                                <div class="modified-indicator" style="display: none;">
                                    <i class="bi bi-star-fill"></i>
                                </div>
                                <div class="number-validation-tooltip" style="display: none;"></div>
                            </div>
                        </div>
                    `;
                }
                
                html += fieldHtml;
            });
            
            html += `
                    </div>
                </div>
            `;
        });
        
        container.html(html);
    }
    
    // 获取列宽类名
    function getColumnClass(sectionKey, fieldKey) {
        // 根据字段数量动态调整列宽
        const section = configData.definitions[sectionKey];
        const fieldCount = Object.keys(section.fields).length;
        
        if (fieldCount <= 2) return '6';
        if (fieldCount <= 4) return '4';
        if (fieldCount <= 6) return '3';
        return '2';
    }
    
    // 初始化表单
    initConfigForm();
    
    // 初始化数字输入框验证（使用统一的验证逻辑）
    function initNumberInputValidation() {
        $('.number-input').each(function() {
            const $input = $(this);
            const $tooltip = $input.siblings('.number-validation-tooltip');
            let validationTimeout;
            
            // 获取字段信息
            const name = $input.attr('name');
            if (!name) return;
            
            const keys = name.split('[');
            const sectionKey = keys[0];
            const fieldKey = keys[1].replace(']', '');
            
            // 验证数字输入
            function validateNumberInput() {
                const value = parseFloat($input.val());
                
                // 如果输入为空，清除原生验证并返回
                if ($input.val() === '' || isNaN(value)) {
                    $input[0].setCustomValidity('');
                    return;
                }
                
                // 使用封装的验证函数
                applyValidationToInput($input, sectionKey, fieldKey, value, configData.definitions);
            }
            
            // 输入时验证（延迟执行）
            $input.on('input', function() {
                clearTimeout(validationTimeout);
                validationTimeout = setTimeout(validateNumberInput, 500);
            });
            
            // 失去焦点时立即验证
            $input.on('blur', function() {
                clearTimeout(validationTimeout);
                validateNumberInput();
                
                // 不触发原生验证气泡，允许用户离开输入框
                // 只在重新聚焦时显示原生提示
            });
            
            // 获得焦点时清除验证状态
            $input.on('focus', function() {
                $input.removeClass('is-valid is-invalid');
                // $tooltip.hide().removeClass('text-success text-danger'); // 注释掉自定义提示的清除
                
                // 重新聚焦时，如果有验证错误，显示原生提示
                setTimeout(() => {
                    if ($input[0].checkValidity && !$input[0].validity.valid) {
                        $input[0].reportValidity();
                    }
                }, 100); // 稍微延迟，确保焦点已设置
            });
        });
    }
    
    // 在表单生成后初始化数字输入框验证
    function initConfigForm() {
        // 生成表单
        generateConfigForm();
        
        // 初始化组件
        if (window.csgSwitch) {
            window.csgSwitch.autoInit();
        }
        if (window.CSGSelect) {
            window.CSGSelect.autoInit();
        }
        
        // 初始化数字输入框验证
        initNumberInputValidation();
        
        // 设置初始配置
        updateInitialConfig();
    }
    
    // 检查修改状态 - 始终与initialConfig比较
    function checkModifications() {
        modifiedFields.clear();
        let hasModifications = false;
        
        // 清除所有修改状态效果
        $('#judger_config_form input, #judger_config_form select').removeClass('modified');
        $('#judger_config_form .csg-switch-setting').removeClass('modified');
        $('.modified-indicator').hide();
        
        $('#judger_config_form input, #judger_config_form select').each(function() {
            const name = $(this).attr('name');
            if (name) {
                // 使用统一的数据清洗函数获取当前值
                const currentValue = cleanFieldValue($(this));
                
                // 始终与initialConfig比较
                const [section, field] = name.replace(/\]$/, '').split('[');
                if (initialConfig && initialConfig[section] && initialConfig[section].hasOwnProperty(field)) {
                    const initialValue = initialConfig[section][field];
                    
                    // 直接比较清洗后的值
                    if (currentValue !== initialValue) {
                        modifiedFields.add(name);
                        hasModifications = true;
                        
                        // 应用修改状态效果（淡蓝色阴影 + 小黄星）
                        if ($(this).hasClass('csg-switch-input')) {
                            // CSG Switch 组件
                            $(this).closest('.csg-switch-setting').addClass('modified');
                            $(this).closest('.csg-switch-container').find('.modified-indicator').show();
                        } else if ($(this).hasClass('csg-select-input')) {
                            // CSG Select 组件
                            $(this).addClass('modified');
                            $(this).closest('.csg-select-container').find('.modified-indicator').show();
                        } else {
                            // 普通 input 组件
                            $(this).addClass('modified');
                            $(this).closest('.form-control-container').find('.modified-indicator').show();
                        }
                    }
                }
            }
        });
        
        // 更新修改状态显示
        if (hasModifications) {
            $('#modification_count').text(modifiedFields.size);
            $('#modification_status').show();
            $('#header_modification_icon').show();
        } else {
            $('#modification_status').hide();
            $('#header_modification_icon').hide();
        }
    }
    
    // 重置修改状态 - 提交成功后调用
    function resetModificationStatus() {
        modifiedFields.clear();
        $('#modification_status').hide();
        $('#header_modification_icon').hide();
        
        // 清除所有修改状态效果
        $('#judger_config_form input, #judger_config_form select').removeClass('modified');
        $('#judger_config_form .csg-switch-setting').removeClass('modified');
        $('.modified-indicator').hide();
        
        // 更新initialConfig为当前配置
        updateInitialConfig();
    }
    
    // 统一的数据清洗函数
    function cleanFieldValue($element) {
        if ($element.is(':checkbox')) {
            // 布尔类型：直接返回布尔值
            return $element.is(':checked');
        } else {
            const value = $element.val();
            const fieldType = $element.attr('type');
            
            if (fieldType === 'number') {
                // 数字类型：确保是数字
                if (value === '' || value === null || value === undefined) {
                    return 0;
                } else {
                    const numValue = parseFloat(value);
                    return isNaN(numValue) ? 0 : numValue;
                }
            } else {
                // 字符串类型：确保是字符串
                return String(value || '').trim();
            }
        }
    }
    
    // 下载配置功能
    function exportConfig() {
        if (!initialConfig) {
            alerty.error('没有可下载的配置数据', 'No configuration data to export');
            return;
        }
        
        // 创建下载数据，包含元信息
        const exportData = {
            version: '1.0',
            timestamp: new Date().toISOString(),
            description: 'CSGOJ Judger Configuration Export',
            config: initialConfig
        };
        
        // 生成JSON字符串
        const jsonString = JSON.stringify(exportData, null, 2);
        
        // 创建下载链接
        const blob = new Blob([jsonString], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        
        // 创建下载链接并触发下载
        const a = document.createElement('a');
        a.href = url;
        a.download = `judger_config_${new Date().toISOString().slice(0, 10)}.json`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        
        // 清理URL对象
        URL.revokeObjectURL(url);
        
        alerty.success('配置下载成功', 'Configuration exported successfully');
    }
    
    // 上传配置功能
    function importConfig(file) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            try {
                const importData = JSON.parse(e.target.result);
                
                // 检查上传数据格式
                if (!importData.config || typeof importData.config !== 'object') {
                    alerty.error('无效的配置文件格式', 'Invalid configuration file format');
                    return;
                }
                
                // 处理上传的配置
                processImportedConfig(importData.config);
                
            } catch (error) {
                alerty.error('配置文件解析失败：' + error.message, 'Failed to parse configuration file: ' + error.message);
            }
        };
        
        reader.onerror = function() {
            alerty.error('文件读取失败', 'Failed to read file');
        };
        
        reader.readAsText(file);
    }
    
    // 处理上传的配置数据
    function processImportedConfig(importedConfig) {
        const currentConfig = {};
        const unknownFields = [];
        const invalidFields = [];
        
        // 获取当前配置的所有字段
        $('#judger_config_form input, #judger_config_form select').each(function() {
            const name = $(this).attr('name');
            if (name) {
                const [section, field] = name.replace(/\]$/, '').split('[');
                if (!currentConfig[section]) {
                    currentConfig[section] = {};
                }
                currentConfig[section][field] = true; // 标记字段存在
            }
        });
        
        // 处理上传的配置
        Object.keys(importedConfig).forEach(section => {
            if (currentConfig[section]) {
                Object.keys(importedConfig[section]).forEach(field => {
                    if (currentConfig[section][field]) {
                        // 字段存在，验证并设置值
                        const value = importedConfig[section][field];
                        const $element = $(`input[name="${section}[${field}]"], select[name="${section}[${field}]"]`);
                        
                        if ($element.length > 0) {
                            // 验证数据类型
                            if (validateFieldValue($element, value)) {
                                setFieldValue($element, value);
                            } else {
                                invalidFields.push(`${section}.${field}`);
                            }
                        }
                    } else {
                        // 字段不存在于当前配置中
                        unknownFields.push(`${section}.${field}`);
                    }
                });
            } else {
                // 整个分组不存在
                Object.keys(importedConfig[section]).forEach(field => {
                    unknownFields.push(`${section}.${field}`);
                });
            }
        });
        
        // 检查修改状态
        checkModifications();
        
        // 显示警告信息
        if (unknownFields.length > 0) {
            alerty.warn(
                `上传的配置包含 ${unknownFields.length} 个未知字段，已忽略：\n${unknownFields.slice(0, 5).join('\n')}${unknownFields.length > 5 ? '\n...' : ''}`,
                `Imported configuration contains ${unknownFields.length} unknown fields, ignored:\n${unknownFields.slice(0, 5).join('\n')}${unknownFields.length > 5 ? '\n...' : ''}`
            );
        }
        
        if (invalidFields.length > 0) {
            alerty.warn(
                `上传的配置包含 ${invalidFields.length} 个无效字段，请检查：\n${invalidFields.join('\n')}`,
                `Imported configuration contains ${invalidFields.length} invalid fields, please check:\n${invalidFields.join('\n')}`
            );
        }
        
        if (unknownFields.length === 0 && invalidFields.length === 0) {
            alerty.success('配置上传成功', 'Configuration imported successfully');
        }
    }
    
    // 验证字段值
    function validateFieldValue($element, value) {
        if ($element.is(':checkbox')) {
            return typeof value === 'boolean';
        } else if ($element.attr('type') === 'number') {
            return typeof value === 'number' && !isNaN(value);
        } else {
            return typeof value === 'string';
        }
    }
    
    // 设置字段值
    function setFieldValue($element, value) {
        if ($element.is(':checkbox')) {
            $element.prop('checked', value);
        } else if ($element.attr('type') === 'number') {
            $element.val(value);
        } else {
            $element.val(value);
        }
        
        // 触发change事件以更新组件状态
        $element.trigger('change');
    }
    
    // 更新初始配置
    function updateInitialConfig() {
        const currentConfig = {};
        $('#judger_config_form input, #judger_config_form select').each(function() {
            const name = $(this).attr('name');
            if (name) {
                const [section, field] = name.replace(/\]$/, '').split('[');
                if (!currentConfig[section]) {
                    currentConfig[section] = {};
                }
                
                // 使用统一的数据清洗函数
                currentConfig[section][field] = cleanFieldValue($(this));
            }
        });
        
        // 更新initialConfig
        initialConfig = currentConfig;
    }
    
    // 获取必需的分组列表（基于后端配置）
    function getRequiredSections(definitions) {
        return Object.keys(definitions);
    }
    
    // 验证单个字段值（核心验证逻辑）
    function validateFieldValue(value, field, sectionKey, fieldKey) {
        const errors = [];
        
        if (field.type === 'number') {
            // 数字类型验证
            if (typeof value !== 'number' || isNaN(value)) {
                errors.push(`字段 ${sectionKey}.${fieldKey} 必须是数字，当前值：${value} (类型：${typeof value})`);
            } else {
                // 检查数值范围
                if (field.min !== undefined && value < field.min) {
                    errors.push(`字段 ${sectionKey}.${fieldKey} 不能小于 ${field.min}，当前值：${value}`);
                }
                if (field.max !== undefined && value > field.max) {
                    errors.push(`字段 ${sectionKey}.${fieldKey} 不能大于 ${field.max}，当前值：${value}`);
                }
                
                // 检查步长
                if (field.step !== undefined && field.step > 0) {
                    const min = field.min || 0;
                    const remainder = (value - min) % field.step;
                    if (Math.abs(remainder) > 0.0001 && Math.abs(remainder - field.step) > 0.0001) {
                        errors.push(`字段 ${sectionKey}.${fieldKey} 必须是 ${field.step} 的倍数，当前值：${value}`);
                    }
                }
            }
        } else if (field.type === 'select') {
            // 选择类型验证
            const validValues = field.options.map(opt => opt.value);
            if (!validValues.includes(value)) {
                errors.push(`字段 ${sectionKey}.${fieldKey} 的值不在允许的选项中，当前值：${value}`);
            }
        } else if (field.type === 'switch') {
            // 开关类型验证
            if (typeof value !== 'boolean') {
                errors.push(`字段 ${sectionKey}.${fieldKey} 必须是布尔值，当前值：${value} (类型：${typeof value})`);
            }
        }
        
        return errors;
    }
    
    // 验证单个字段（用于实时验证）
    function validateSingleField(sectionKey, fieldKey, value, definitions) {
        if (!definitions[sectionKey] || !definitions[sectionKey].fields[fieldKey]) {
            return [];
        }
        
        const field = definitions[sectionKey].fields[fieldKey];
        return validateFieldValue(value, field, sectionKey, fieldKey);
    }
    
    // 应用验证到输入框（封装验证逻辑）
    function applyValidationToInput($input, sectionKey, fieldKey, value, definitions) {
        const errors = validateSingleField(sectionKey, fieldKey, value, definitions);
        
        if (errors.length === 0) {
            // 验证通过
            $input.addClass('is-valid').removeClass('is-invalid');
            $input[0].setCustomValidity('');
        } else {
            // 验证失败
            $input.addClass('is-invalid').removeClass('is-valid');
            let errorMessage = errors[0].replace(`字段 ${sectionKey}.${fieldKey} `, '').replace('，当前值：' + value, '');
            
            // 如果是步长错误，提供更友好的提示
            if (errorMessage.includes('必须是') && errorMessage.includes('的倍数')) {
                const step = $input.data('field-step');
                const min = $input.data('field-min') || 0;
                const unit = $input.data('field-unit') || '';
                const nearestValid1 = Math.floor((value - min) / step) * step + min;
                const nearestValid2 = nearestValid1 + step;
                errorMessage = `值必须是 ${step}${unit} 的倍数，建议使用 ${nearestValid1}${unit} 或 ${nearestValid2}${unit}`;
            }
            
            $input[0].setCustomValidity(errorMessage);
        }
    }
    
    // 前端配置数据验证函数（用于提交前的完整验证）
    function validateConfigData(data, definitions) {
        const errors = [];
        
        // 获取必需的分组列表（基于后端配置）
        const requiredSections = getRequiredSections(definitions);
        requiredSections.forEach(section => {
            if (!data[section]) {
                errors.push(`缺少必需的配置分组：${section}`);
            }
        });
        
        // 验证每个分组的字段
        Object.keys(definitions).forEach(sectionKey => {
            if (!data[sectionKey]) {
                return; // 跳过不存在的分组
            }
            
            const sectionData = data[sectionKey];
            const sectionDef = definitions[sectionKey];
            
            Object.keys(sectionDef.fields).forEach(fieldKey => {
                if (!sectionData.hasOwnProperty(fieldKey)) {
                    errors.push(`配置分组 ${sectionKey} 缺少必需字段：${fieldKey}`);
                    return;
                }
                
                const value = sectionData[fieldKey];
                const field = sectionDef.fields[fieldKey];
                
                // 使用统一的字段验证逻辑
                const fieldErrors = validateFieldValue(value, field, sectionKey, fieldKey);
                errors.push(...fieldErrors);
            });
        });
        
        return errors;
    }
    
    // 绑定修改检测事件
    $(document).on('change input', '#judger_config_form input, #judger_config_form select', function() {
        checkModifications();
    });
    
    // 获取默认配置
    $('#get_default_config').on('click', function() {
        const btn = $(this);
        const originalHtml = btn.html();
        
        // 显示加载状态
        btn.prop('disabled', true).html('<i class="bi bi-hourglass-split"></i>');
        
        $.ajax({
            url: '/admin/judger/get_default_config_ajax',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.code === 1) {
                    // 更新配置数据
                    configData.config = response.data;
                    
                    // 重新生成表单
                    generateConfigForm();
                    
                    // 重新初始化组件
                    if (window.csgSwitch) {
                        window.csgSwitch.autoInit();
                    }
                    if (window.CSGSelect) {
                        window.CSGSelect.autoInit();
                    }
                    
                    // 重新初始化数字输入框验证
                    initNumberInputValidation();
                    
                    // 触发一遍我们自己的验证逻辑，显示红框状态
                    setTimeout(() => {
                        $('.number-input').each(function() {
                            const $input = $(this);
                            const name = $input.attr('name');
                            if (name) {
                                const keys = name.split('[');
                                const sectionKey = keys[0];
                                const fieldKey = keys[1].replace(']', '');
                                
                                // 获取当前值并验证
                                const value = parseFloat($input.val());
                                if (!isNaN(value)) {
                                    applyValidationToInput($input, sectionKey, fieldKey, value, configData.definitions);
                                }
                            }
                        });
                    }, 200); // 延迟确保组件完全初始化
                    
                    // 检查修改状态
                    checkModifications();
                    
                    alerty.success('已加载默认配置', 'Default configuration loaded');
                } else {
                    alerty.error(response.msg, 'Failed to load default configuration');
                }
            },
            error: function(xhr, status, error) {
                alerty.error('获取默认配置失败：' + error, 'Failed to get default configuration: ' + error);
            },
            complete: function() {
                // 恢复按钮状态
                btn.prop('disabled', false).html(originalHtml);
            }
        });
    });
    
    // 下载配置按钮
    $('#export_config').on('click', function() {
        exportConfig();
    });
    
    // 上传配置按钮
    $('#import_config').on('click', function() {
        $('#import_file_input').click();
    });
    
    // 文件选择事件
    $('#import_file_input').on('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            importConfig(file);
        }
    });
    
    // 提交配置按钮
    $('#submit_config').on('click', function() {
        $('#judger_config_form').trigger('submit');
    });
    
    // 添加Ctrl+S快捷键保存
    $(document).on('keydown', function(e) {
        // 检查是否按下了Ctrl+S，并且不在输入框中
        if (e.ctrlKey && e.key === 's' && !$(e.target).is('input, textarea, select')) {
            e.preventDefault(); // 阻止浏览器默认的保存行为
            
            // 触发表单提交
            $('#judger_config_form').trigger('submit');
        }
    });
    
    // ========== 评测机列表相关功能 ==========
    
    // 初始化密码输入框
    function initPasswordInput() {
        const passwordInput = $('#judger_password_input');
        let tooltip = null;
        
        // 从缓存恢复密码
        const cachedPassword = csg.store('judger_password_input');
        if (cachedPassword) {
            passwordInput.val(cachedPassword);
        }
        
        // 创建提示气泡
        function showTooltip(message) {
            hideTooltip();
            tooltip = $('<div class="prefix-tooltip">' + message + '</div>');
            $('body').append(tooltip);
            
            const inputOffset = passwordInput.offset();
            tooltip.css({
                position: 'absolute',
                top: inputOffset.top - 35,
                left: inputOffset.left,
                background: '#dc3545',
                color: 'white',
                padding: '5px 10px',
                borderRadius: '4px',
                fontSize: '12px',
                zIndex: 9999,
                whiteSpace: 'nowrap'
            });
        }
        
        function hideTooltip() {
            if (tooltip) {
                tooltip.remove();
                tooltip = null;
            }
        }
        
        // 输入验证
        passwordInput.on('input', function() {
            let value = $(this).val();
            const originalValue = value;
            
            // 只保留数字和字母
            value = value.replace(/[^a-zA-Z0-9]/g, '');
            
            // 如果输入了非法字符，显示提示
            if (originalValue !== value) {
                showTooltip('只能输入数字和字母');
                setTimeout(hideTooltip, 2000);
            }
            
            // 限制最大长度
            if (value.length > 30) {
                value = value.substring(0, 30);
                showTooltip('最多输入30个字符');
                setTimeout(hideTooltip, 2000);
            }
            
            $(this).val(value);
        });
        
        // 键盘事件处理
        passwordInput.on('keydown', function(e) {
            const currentValue = $(this).val();
            const selection = this.selectionStart !== this.selectionEnd;
            
            // 只阻止普通字符输入（当已有30个字符且无选中时）
            if (currentValue.length >= 30 && 
                !selection &&
                e.key.length === 1 && // 只阻止单个字符输入
                !e.ctrlKey && // 不阻止控制键组合
                !e.metaKey && // 不阻止Meta键组合
                !['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Tab', 'Enter', 'Escape'].includes(e.key)) {
                e.preventDefault();
                showTooltip('最多输入30个字符');
                setTimeout(hideTooltip, 2000);
            }
        });
        
        // 失去焦点时验证长度
        passwordInput.on('blur', function() {
            const value = $(this).val();
            if (value && value.length < 6) {
                showTooltip('密码至少需要6个字符');
                setTimeout(hideTooltip, 2000);
            }
            setTimeout(hideTooltip, 100);
        });
        
        // 获得焦点时清除提示
        passwordInput.on('focus', function() {
            hideTooltip();
        });
        
        // 监听输入变化，保存到缓存
        passwordInput.on('input change', function() {
            const value = $(this).val();
            if (value) {
                csg.store('judger_password_input', value, 24 * 60 * 60 * 1000); // 24小时过期
            } else {
                csg.store('judger_password_input', ''); // 清空缓存
            }
        });
        
        // 切换密码显示/隐藏
        $('#toggle_password_btn').on('click', function() {
            const input = passwordInput[0];
            const icon = $('#password_icon');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.removeClass('bi-eye-slash').addClass('bi-eye');
                $(this).attr('title', '显示密码 (Show Password)');
            } else {
                input.type = 'password';
                icon.removeClass('bi-eye').addClass('bi-eye-slash');
                $(this).attr('title', '隐藏密码 (Hide Password)');
            }
        });
        
        
        // 初始化配置面板密码输入框
        initConfigPasswordInput();
    }
    
    // 初始化配置面板密码输入框
    function initConfigPasswordInput() {
        const configPasswordInput = $('#config_password');
        let tooltip = null;
        
        // 创建提示气泡
        function showTooltip(message) {
            hideTooltip();
            tooltip = $('<div class="prefix-tooltip">' + message + '</div>');
            $('body').append(tooltip);
            
            const inputOffset = configPasswordInput.offset();
            tooltip.css({
                position: 'absolute',
                top: inputOffset.top - 35,
                left: inputOffset.left,
                background: '#333',
                color: '#fff',
                padding: '5px 10px',
                borderRadius: '4px',
                fontSize: '12px',
                zIndex: 9999,
                whiteSpace: 'nowrap'
            });
        }
        
        function hideTooltip() {
            if (tooltip) {
                tooltip.remove();
                tooltip = null;
            }
        }
        
        // 输入验证
        configPasswordInput.on('input', function() {
            let value = $(this).val();
            const originalValue = value;
            value = value.replace(/[^a-zA-Z0-9]/g, ''); // 只允许数字和字母
            if (originalValue !== value) {
                showTooltip('只能输入数字和字母');
                setTimeout(hideTooltip, 2000);
            }
            if (value.length > 30) {
                value = value.substring(0, 30);
                showTooltip('最多输入30个字符');
                setTimeout(hideTooltip, 2000);
            }
            $(this).val(value);
        });
        
        // 键盘输入限制
        configPasswordInput.on('keydown', function(e) {
            const currentValue = $(this).val();
            const selection = this.selectionStart !== this.selectionEnd;
            if (currentValue.length >= 30 && 
                !selection &&
                e.key.length === 1 && !e.ctrlKey && !e.metaKey && 
                !['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Tab', 'Enter', 'Escape'].includes(e.key)) {
                e.preventDefault();
                showTooltip('最多输入30个字符');
                setTimeout(hideTooltip, 2000);
            }
        });
        
        // 失去焦点时验证最小长度
        configPasswordInput.on('blur', function() {
            const value = $(this).val();
            if (value && value.length < 6) {
                showTooltip('密码至少需要6个字符');
                setTimeout(hideTooltip, 2000);
            }
            setTimeout(hideTooltip, 100);
        });
        
        // 获得焦点时清除提示
        configPasswordInput.on('focus', function() {
            hideTooltip();
        });
        
        // 配置面板密码输入框现在是明文输入，无需切换功能
    }
    
    // 初始化前缀输入框验证
    function initPrefixInput() {
        const prefixInput = $('#judger_prefix_input');
        let tooltip = null;
        
        // 从缓存恢复前缀
        const cachedPrefix = csg.store('judger_prefix_input');
        if (cachedPrefix) {
            prefixInput.val(cachedPrefix);
        }
        
        // 创建提示气泡
        function showTooltip(message) {
            hideTooltip();
            tooltip = $('<div class="prefix-tooltip">' + message + '</div>');
            $('body').append(tooltip);
            
            const inputOffset = prefixInput.offset();
            tooltip.css({
                position: 'absolute',
                top: inputOffset.top - 35,
                left: inputOffset.left,
                background: '#dc3545',
                color: 'white',
                padding: '5px 10px',
                borderRadius: '4px',
                fontSize: '12px',
                zIndex: 9999,
                whiteSpace: 'nowrap'
            });
        }
        
        function hideTooltip() {
            if (tooltip) {
                tooltip.remove();
                tooltip = null;
            }
        }
        
        // 输入验证
        prefixInput.on('input', function() {
            let value = $(this).val();
            const originalValue = value;
            
            // 只保留小写字母
            value = value.replace(/[^a-z]/g, '');
            
            // 如果输入了非法字符，显示提示
            if (originalValue !== value) {
                showTooltip('只能输入小写字母');
                setTimeout(hideTooltip, 2000);
            }
            
            // 限制最大长度
            if (value.length > 2) {
                value = value.substring(0, 2);
                showTooltip('最多输入2个字符');
                setTimeout(hideTooltip, 2000);
            }
            
            $(this).val(value);
        });
        
        // 键盘事件处理
        prefixInput.on('keydown', function(e) {
            const currentValue = $(this).val();
            const selection = this.selectionStart !== this.selectionEnd;
            
            // 只阻止普通字符输入（当已有2个字符且无选中时）
            if (currentValue.length >= 2 && 
                !selection &&
                e.key.length === 1 && // 只阻止单个字符输入
                !e.ctrlKey && // 不阻止控制键组合
                !e.metaKey && // 不阻止Meta键组合
                !['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Tab', 'Enter', 'Escape'].includes(e.key)) {
                e.preventDefault();
                showTooltip('最多输入2个字符');
                setTimeout(hideTooltip, 2000);
            }
        });
        
        // 失去焦点时隐藏提示
        prefixInput.on('blur', function() {
            setTimeout(hideTooltip, 100);
        });
        
        // 获得焦点时清除提示
        prefixInput.on('focus', function() {
            hideTooltip();
        });
        
        // 监听输入变化，保存到缓存
        prefixInput.on('input change', function() {
            const value = $(this).val();
            if (value) {
                csg.store('judger_prefix_input', value, 24 * 60 * 60 * 1000); // 24小时过期
            } else {
                csg.store('judger_prefix_input', ''); // 清空缓存
            }
        });
    }
    
    // 添加评测机功能
    function addJudger() {
        // 获取密码输入框的值
        const customPassword = $('#judger_password_input').val().trim();
        
        // 使用默认配置创建评测机
        const defaultConfig = {
            pro_list: '',
            flg_white: 0,
            defunct: 0,
            language: [],
            custom_password: customPassword // 添加自定义密码
        };
        
        // 调用通用函数
        if (typeof addJudgerWithConfig === 'function') {
            addJudgerWithConfig(
                defaultConfig,
                '评测机添加成功', 
                'Judger added successfully'
            );
        } else {
            // 如果通用函数不可用，使用原来的逻辑
            addJudgerDefault();
        }
    }
    
    // 默认添加评测机函数（备用）
    function addJudgerDefault() {
        const prefix = $('#judger_prefix_input').val().trim();
        const finalPrefix = prefix || 'j'; // 默认前缀为 'j'
        
        // 获取当前表格数据
        const table = $('#judger_list_table');
        const tableData = table.bootstrapTable('getData');
        const existingUserIds = tableData.map(row => row.user_id);
        
        // 查找可用的用户ID（最多99个评测机）
        let newUserId = '';
        for (let i = 1; i <= 99; i++) {
            const candidateId = finalPrefix + String(i).padStart(2, '0');
            if (!existingUserIds.includes(candidateId)) {
                newUserId = candidateId;
                break;
            }
        }
        
        if (!newUserId) {
            alerty.error('无法生成可用的评测机ID，请尝试其他前缀', 'Cannot generate available judger ID, please try another prefix');
            return;
        }
        
        // 验证用户ID长度
        if (newUserId.length > 4) {
            alerty.error('生成的评测机ID长度超过4位，请使用更短的前缀', 'Generated judger ID exceeds 4 characters, please use a shorter prefix');
            return;
        }
        
        // 检查总数限制（最多99个评测机）
        if (existingUserIds.length >= 99) {
            alerty.error('评测机总数已达到上限（99个），无法继续添加', 'Maximum number of judgers reached (99), cannot add more');
            return;
        }
        
        // 直接添加，无需确认
        $.ajax({
            url: '/admin/judger/judger_add_ajax',
            type: 'POST',
            data: {
                user_id: newUserId
            },
            dataType: 'json',
            success: function(response) {
                if (response.code === 1) {
                    const userData = response.data;
                    const password = userData && userData.password ? userData.password : '';
                    
                    // 将新用户数据添加到表格中
                    if (userData) {
                        // 转换语言掩码为语言列表
                        const langMask = parseInt(userData.language) || 0;
                        const langList = convertLangMaskToList(langMask);
                        
                        // 准备新行数据
                        const newRow = {
                            user_id: userData.user_id,
                            pro_list: userData.email || '',
                            flg_white: userData.volume || 0,
                            langlist: langList,
                            can_delete: true
                        };
                        
                        // 添加到表格数据中
                        table.bootstrapTable('prepend', newRow);
                    }
                    
                    alerty.success(`评测机 ${newUserId} 添加成功`, `Judger ${newUserId} added successfully`);
                    // 更新数量显示
                    updateJudgerCount();
                } else {
                    alerty.error(response.info || '添加失败', 'Failed to add judger');
                }
            },
            error: function(xhr, status, error) {
                alerty.error('添加评测机失败：' + error, 'Failed to add judger: ' + error);
            }
        });
    }
    
    // 语言掩码转换为语言列表（前端版本）
    function convertLangMaskToList(langMask) {
        if (langMask < 0) return [];
        
        const languageConfig = JSON.parse($('#oj_language_data').val());
        const langList = [];
        
        // 遍历所有可能的语言位
        for (let i = 0; i < 32; i++) {
            if (langMask & (1 << i)) {
                if (languageConfig[i]) {
                    langList.push(languageConfig[i]);
                } else {
                    langList.push('UNKNOWN');
                }
            }
        }
        
        return langList;
    }
    
    
    
    // 批量重置密码功能
    function batchResetPassword() {
        // 获取密码输入框的值
        const password = $('#judger_password_input').val().trim();
        
        // 验证密码格式
        if (!password) {
            alerty.warning('请输入密码才能使用批量重置功能', 'Please enter a password to use batch reset function');
            return;
        }
        
        if (password.length < 6 || password.length > 30) {
            alerty.warning('密码长度必须在6-30位之间', 'Password length must be between 6-30 characters');
            return;
        }
        
        if (!/^[a-zA-Z0-9]+$/.test(password)) {
            alerty.warning('密码只能包含数字和字母', 'Password can only contain numbers and letters');
            return;
        }
        
        // 获取当前筛选结果的评测机列表
        const table = $('#judger_list_table');
        const filteredData = table.bootstrapTable('getData');
        
        if (filteredData.length === 0) {
            alerty.warning('没有找到可重置的评测机', 'No judgers found to reset');
            return;
        }
        
        // 显示确认对话框
        alerty.confirm({
            message: `确定要批量重置 ${filteredData.length} 个评测机的密码吗？\n\n此操作将：\n• 将所有筛选出的评测机密码重置为：${password}\n• 此操作不可撤销\n• 请确保密码安全可靠`,
            message_en: `Are you sure you want to batch reset passwords for ${filteredData.length} judgers?\n\nThis operation will:\n• Reset all filtered judgers' passwords to: ${password}\n• This operation cannot be undone\n• Please ensure the password is secure`,
            callback: function() {
                executeBatchPasswordReset(filteredData, password);
            },
            callbackCancel: function() {
                alerty.message('操作已取消', 'Operation cancelled');
            }
        });
    }
    
    // 执行批量密码重置
    function executeBatchPasswordReset(judgerList, password) {
        const results = {
            success: [],
            failed: []
        };
        
        let completed = 0;
        const total = judgerList.length;
        
        // 显示进度
        alerty.info(`正在重置密码... (0/${total})`, `Resetting passwords... (0/${total})`);
        
        // 逐个重置密码
        judgerList.forEach((judger, index) => {
            $.ajax({
                url: '/admin/judger/judger_config_ajax',
                type: 'POST',
                data: {
                    user_id: judger.user_id,
                    password: password
                },
                dataType: 'json',
                success: function(response) {
                    if (response.code === 1) {
                        results.success.push({
                            user_id: judger.user_id,
                            password: password
                        });
                    } else {
                        results.failed.push({
                            user_id: judger.user_id,
                            error: response.msg || '未知错误'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    results.failed.push({
                        user_id: judger.user_id,
                        error: error || '网络错误'
                    });
                },
                complete: function() {
                    completed++;
                    
                    // 更新进度
                    if (completed < total) {
                        alerty.info(`正在重置密码... (${completed}/${total})`, `Resetting passwords... (${completed}/${total})`);
                    } else {
                        // 所有操作完成，显示结果
                        showBatchResetResults(results, password);
                    }
                }
            });
        });
    }
    
    // 显示批量重置结果
    function showBatchResetResults(results, password) {
        const successCount = results.success.length;
        const failedCount = results.failed.length;
        const totalCount = successCount + failedCount;
        
        let message = `批量重置密码完成！\n\n`;
        message += `总计：${totalCount} 个评测机\n`;
        message += `成功：${successCount} 个\n`;
        message += `失败：${failedCount} 个\n\n`;
        message += `新密码：${password}\n\n`;
        
        if (successCount > 0) {
            message += `成功重置的评测机：\n`;
            results.success.forEach(item => {
                message += `• ${item.user_id}\n`;
            });
        }
        
        if (failedCount > 0) {
            message += `\n重置失败的评测机：\n`;
            results.failed.forEach(item => {
                message += `• ${item.user_id}: ${item.error}\n`;
            });
        }
        
        let messageEn = `Batch password reset completed!\n\n`;
        messageEn += `Total: ${totalCount} judgers\n`;
        messageEn += `Success: ${successCount}\n`;
        messageEn += `Failed: ${failedCount}\n\n`;
        messageEn += `New password: ${password}\n\n`;
        
        if (successCount > 0) {
            messageEn += `Successfully reset judgers:\n`;
            results.success.forEach(item => {
                messageEn += `• ${item.user_id}\n`;
            });
        }
        
        if (failedCount > 0) {
            messageEn += `\nFailed to reset judgers:\n`;
            results.failed.forEach(item => {
                messageEn += `• ${item.user_id}: ${item.error}\n`;
            });
        }
        
        // 根据结果选择不同的模态框类型和标题
        let modalType = 'alert';
        let title = '批量重置密码结果';
        let titleEn = 'Batch Password Reset Results';
        
        if (failedCount === 0) {
            title = '✅ 批量重置密码成功';
            titleEn = '✅ Batch Password Reset Success';
        } else if (successCount === 0) {
            title = '❌ 批量重置密码失败';
            titleEn = '❌ Batch Password Reset Failed';
        } else {
            title = '⚠️ 批量重置密码部分成功';
            titleEn = '⚠️ Batch Password Reset Partially Successful';
        }
        
        // 使用模态框显示结果
        alerty.alert({
            title: title,
            title_en: titleEn,
            message: message,
            message_en: messageEn,
            okText: '确定',
            okText_en: 'OK',
            callback: function() {
                // 刷新表格以显示最新数据
                $('#judger_list_table').bootstrapTable('refresh');
            }
        });
    }
    
    // 初始化前缀输入框
    initPrefixInput();
    
    // 初始化密码输入框
    initPasswordInput();
    
    // 绑定添加按钮事件
    $('#add_judger_btn').on('click', addJudger);
    
    // 绑定批量重置密码按钮事件
    $('#batch_reset_password_btn').on('click', batchResetPassword);
    
    // 表格加载完成后更新数量显示
    $('#judger_list_table').on('load-success.bs.table', function() {
        updateJudgerCount();
    });
    
    // 表单提交处理
    $('#judger_config_form').on('submit', function(e) {
        e.preventDefault();
        
        // 收集表单数据
        const formData = {};
        const validationErrors = [];
        
        $(this).find('input, select').each(function() {
            const name = $(this).attr('name');
            if (name) {
                const keys = name.split('[');
                const section = keys[0];
                const field = keys[1].replace(']', '');
                
                if (!formData[section]) {
                    formData[section] = {};
                }
                
                if ($(this).is(':checkbox')) {
                    // 布尔类型：直接获取checked状态
                    formData[section][field] = $(this).is(':checked');
                } else {
                    const value = $(this).val();
                    const fieldType = $(this).attr('type');
                    
                    if (fieldType === 'number') {
                        // 数字类型：验证并转换
                        if (value === '' || value === null || value === undefined) {
                            formData[section][field] = 0;
                        } else {
                            const numValue = parseFloat(value);
                            if (isNaN(numValue)) {
                                validationErrors.push(`${section}.${field}: 无效的数字值 "${value}"`);
                                formData[section][field] = 0;
                            } else {
                                formData[section][field] = numValue;
                            }
                        }
                    } else {
                        // 字符串类型：清理和验证
                        const cleanValue = String(value || '').trim();
                        formData[section][field] = cleanValue;
                    }
                }
            }
        });
        
        // 进行完整的数据验证（使用统一的验证逻辑）
        const detailedValidationErrors = validateConfigData(formData, configData.definitions);
        validationErrors.push(...detailedValidationErrors);
        
        // 检查验证错误
        if (validationErrors.length > 0) {
            console.warn('数据验证错误:', validationErrors);
            alerty.error('数据验证失败：\n' + validationErrors.join('\n'), 'Data validation failed');
            return;
        }
        
        // 将数据转换为JSON字符串
        const jsonData = JSON.stringify(formData);
        
        // 显示加载状态
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.html();
        submitBtn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i>保存中...');
        
        // 提交配置数据
        $.ajax({
            url: '/admin/judger/update_judger_config_ajax',
            type: 'POST',
            data: {
                judger_config: jsonData
            },
            dataType: 'json',
            success: function(response) {
                if (response.code === 1) {
                    // 保存成功
                    alerty.success(response.msg, 'Configuration saved successfully!');
                    
                    // 重置修改状态
                    resetModificationStatus();
                } else {
                    // 保存失败
                    alerty.error(response.msg, 'Configuration save failed!');
                }
            },
            error: function(xhr, status, error) {
                // 请求失败
                alerty.error('保存配置时发生错误：' + error, 'Error occurred while saving configuration: ' + error);
            },
            complete: function() {
                // 恢复按钮状态
                submitBtn.prop('disabled', false).html(originalText);
            }
        });
    });
});


// **************************************************
// 评测机配置panel
// 评测机限制配置相关JavaScript
const MAX_PRO_SELECT = 25;
let currentJudgerData = null;

// 初始化语言选项
function initLanguageOptions() {
    const languageConfig = JSON.parse($('#oj_language_data').val());
    const languageList = $('#config_language_list');
    
    languageList.empty();
    
    Object.keys(languageConfig).forEach(langKey => {
        const langName = languageConfig[langKey];
        languageList.append(`
            <div class="col-md-3">
                <div class="form-check">
                    <input class="form-check-input language-check" type="checkbox" value="${langKey}" id="lang_${langKey}">
                    <label class="form-check-label" for="lang_${langKey}">
                        ${langName}
                    </label>
                </div>
            </div>
        `);
    });
}

// 打开配置模态框
function openJudgerConfig(row) {
    currentJudgerData = row;
    
    // 填充表单数据
    $('#config_user_id').val(row.user_id);
    $('#config_display_id').text(row.user_id);
    $('#config_password').val(''); // 清空密码输入框
    
    // 设置题目列表
    const problemInputComponent = window.ProblemInputComponents?.get('judger_problem_input');
    if (problemInputComponent) {
        const proList = row.pro_list || '';
        problemInputComponent.setValue(proList);
    }
    
    // 设置黑白名单开关 (0=白名单，1=黑名单)
    const flgWhite = row.flg_white == 0 ? true : false;
    $('#config_flg_white').prop('checked', flgWhite);
    
    // 设置启用状态开关 (0=启用，1=关闭)
    const defunct = row.defunct == 0 ? true : false;
    $('#config_defunct').prop('checked', defunct);
    
    // 同步开关状态
    if (window.csgSwitch) {
        window.csgSwitch.syncSwitchState(document.getElementById('config_flg_white'));
        window.csgSwitch.syncSwitchState(document.getElementById('config_defunct'));
    }
    
    // 设置语言限制
    $('.language-check').prop('checked', false);
    if (row.langlist && Array.isArray(row.langlist)) {
        const languageConfig = JSON.parse($('#oj_language_data').val());
        row.langlist.forEach(langName => {
            // 根据语言名称找到对应的key
            Object.keys(languageConfig).forEach(langKey => {
                if (languageConfig[langKey] === langName) {
                    $(`#lang_${langKey}`).prop('checked', true);
                }
            });
        });
    }
    
    // 显示模态框
    $('#judgerConfigModal').modal('show');
    
    // 等待模态框完全显示后，强制重新初始化开关
    $('#judgerConfigModal').on('shown.bs.modal', function() {
        if (window.csgSwitch) {
            // 强制重新初始化开关
            window.csgSwitch.forceReinit(document.getElementById('config_flg_white'));
            window.csgSwitch.forceReinit(document.getElementById('config_defunct'));
        }
    });
}

// 保存配置
function saveJudgerConfig() {
    const user_id = $('#config_user_id').val();
    
    const problemInputComponent = window.ProblemInputComponents?.get('judger_problem_input');
    if (!problemInputComponent) {
        alerty.error('题目输入组件未找到', 'Problem input component not found');
        return;
    }
    
    let proList = '';
    const problems = problemInputComponent.getArray();
    if (problems.length > MAX_PRO_SELECT) {
        alerty.warning({
            message: `题目数量超过限制（最多 ${MAX_PRO_SELECT} 个），已自动截取前 ${MAX_PRO_SELECT} 个`,
            message_en: `Problem count exceeds limit (max ${MAX_PRO_SELECT}), automatically limited to first ${MAX_PRO_SELECT}`
        });
        const limitedProblems = problems.slice(0, MAX_PRO_SELECT);
        problemInputComponent.setValue(limitedProblems.join(','));
        proList = limitedProblems.join(',');
    } else {
        proList = problems.join(',');
    }
    
    const flgWhite = $('#config_flg_white').is(':checked') ? 0 : 1; // 0=白名单，1=黑名单
    const defunct = $('#config_defunct').is(':checked') ? 0 : 1; // 0=启用，1=关闭
    
    // 收集选中的语言
    const selectedLanguages = [];
    $('.language-check:checked').each(function() {
        selectedLanguages.push(parseInt($(this).val()));
    });
    
    // 将语言列表转换为掩码
    let languageMask = 0;
    selectedLanguages.forEach(langKey => {
        languageMask |= (1 << langKey);
    });
    
    // 获取密码
    const password = $('#config_password').val().trim();
    
    // 提交数据
    $.ajax({
        url: '/admin/judger/judger_config_ajax',
        type: 'POST',
        data: {
            user_id: user_id,
            pro_list: proList,
            flg_white: flgWhite,
            defunct: defunct,
            language: languageMask,
            password: password
        },
        dataType: 'json',
        success: function(response) {
            if (response.code === 1) {
                alerty.success('配置保存成功', 'Configuration saved successfully');
                $('#judgerConfigModal').modal('hide');
                // 刷新表格
                $('#judger_list_table').bootstrapTable('refresh');
            } else {
                alerty.error(response.msg);
            }
        },
        error: function(xhr, status, error) {
            alerty.error('保存配置失败：' + error, 'Failed to save configuration: ' + error);
        }
    });
}

// 打开题目选择器
function openProblemSelector() {
    // 显示题目选择器模态框
    if (window.showProblemSelection) {
        window.showProblemSelection();
    } else {
        console.error('Problem selection function not available');
    }
}

// 题目选择器回调函数
window.onProblemSelectionConfirm = function(selectedProblemIds) {
    const problemInputComponent = window.ProblemInputComponents?.get('judger_problem_input');
    if (!problemInputComponent) return;
    
    const existingProblems = problemInputComponent.getArray();
    
    // 合并新选择的题目，去重
    const allProblems = [...new Set([...existingProblems, ...selectedProblemIds.map(id => id.toString())])];
    
    // 检查题目数量限制（最多 MAX_PRO_SELECT 个）
    if (allProblems.length > MAX_PRO_SELECT) {
        alerty.warning({
            message: `题目数量超过限制，已自动截取前 ${MAX_PRO_SELECT} 个题目`,
            message_en: `Problem count exceeds limit, automatically limited to first ${MAX_PRO_SELECT} problems`
        });
        allProblems.splice(MAX_PRO_SELECT);
    }
    
    // 更新题目列表
    problemInputComponent.setValue(allProblems.join(','));
    
    // 更新数量显示
    updateProblemCount();
    
    // 计算实际新增的题目数量（基于最终结果）
    const finalNewProblems = allProblems.filter(id => !existingProblems.includes(id));
    
    if (finalNewProblems.length > 0) {
        alerty.success({
            message: `已添加 ${finalNewProblems.length} 个题目到列表（共 ${allProblems.length} 个）`,
            message_en: `Added ${finalNewProblems.length} problems to the list (total ${allProblems.length})`
        });
    } else {
        alerty.info({
            message: `没有新增题目，当前共 ${allProblems.length} 个题目`,
            message_en: `No new problems added, current total ${allProblems.length} problems`
        });
    }
};

// 更新题目数量显示
function updateProblemCount() {
    const problemInputComponent = window.ProblemInputComponents?.get('judger_problem_input');
    if (!problemInputComponent) return;
    
    const problems = problemInputComponent.getArray();
    const count = problems.length;
    
    const badge = $('#problem_count_badge');
    if (badge.length > 0) {
        badge.text(`${count}/${MAX_PRO_SELECT}`);
        
        // 根据数量设置不同的颜色
        badge.removeClass('bg-secondary bg-primary bg-warning bg-danger');
        if (count === 0) {
            badge.addClass('bg-secondary');
        } else if (count <= MAX_PRO_SELECT * 0.8) {
            badge.addClass('bg-primary');
        } else if (count < MAX_PRO_SELECT) {
            badge.addClass('bg-warning');
        } else {
            badge.addClass('bg-danger');
        }
    }
}

// 验证题目列表输入
function validateProblemListInput() {
    const problemInputComponent = window.ProblemInputComponents?.get('judger_problem_input');
    if (!problemInputComponent) return;
    
    const problems = problemInputComponent.getArray();
    if (problems.length > MAX_PRO_SELECT) {
        alerty.warning({
            message: `题目数量超过限制（最多 ${MAX_PRO_SELECT} 个），已自动截取前 ${MAX_PRO_SELECT} 个`,
            message_en: `Problem count exceeds limit (max ${MAX_PRO_SELECT}), automatically limited to first ${MAX_PRO_SELECT}`
        });
        const limitedProblems = problems.slice(0, MAX_PRO_SELECT);
        problemInputComponent.setValue(limitedProblems.join(','));
    }
    
    // 更新数量显示
    updateProblemCount();
}

// 下载评测机限制配置
function exportJudgerConfig() {
    if (!currentJudgerData) {
        alerty.error('没有可下载的配置数据', 'No configuration data to export');
        return;
    }
    
    const problemInputComponent = window.ProblemInputComponents?.get('judger_problem_input');
    if (!problemInputComponent) {
        alerty.error('题目输入组件未找到', 'Problem input component not found');
        return;
    }
    
    // 收集当前表单数据
    const proList = problemInputComponent.getArray();
    
    const configData = {
        user_id: $('#config_user_id').val(),
        pro_list: proList,  // 改为数组格式
        flg_white: $('#config_flg_white').is(':checked') ? 0 : 1,
        defunct: $('#config_defunct').is(':checked') ? 0 : 1,
        language: []
    };
    
    // 收集选中的语言
    $('.language-check:checked').each(function() {
        configData.language.push(parseInt($(this).val()));
    });
    
    // 创建下载数据，包含元信息
    const exportData = {
        version: '1.0',
        timestamp: new Date().toISOString(),
        description: 'CSGOJ Judger Configuration Export',
        config: configData
    };
    
    // 生成JSON字符串
    const jsonString = JSON.stringify(exportData, null, 2);
    
    // 创建下载链接
    const blob = new Blob([jsonString], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    
    // 创建下载链接并触发下载
    const a = document.createElement('a');
    a.href = url;
    
    // 生成14位时间戳（年月日时分秒）
    const now = new Date();
    const timestamp = now.getFullYear().toString() +
                     (now.getMonth() + 1).toString().padStart(2, '0') +
                     now.getDate().toString().padStart(2, '0') +
                     now.getHours().toString().padStart(2, '0') +
                     now.getMinutes().toString().padStart(2, '0') +
                     now.getSeconds().toString().padStart(2, '0');
    
    a.download = `judger_${currentJudgerData.user_id}_config_${timestamp}.json`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    
    // 清理URL对象
    URL.revokeObjectURL(url);
    
    alerty.success('配置下载成功', 'Configuration exported successfully');
}

// 上传评测机限制配置
function importJudgerConfig(file) {
    const reader = new FileReader();
    
    reader.onload = function(e) {
        try {
            const importData = JSON.parse(e.target.result);
            
            // 检查上传数据格式
            if (!importData.config || typeof importData.config !== 'object') {
                alerty.error('无效的配置文件格式', 'Invalid configuration file format');
                return;
            }
            
            // 处理上传的配置
            processImportedJudgerConfig(importData.config);
            
        } catch (error) {
            alerty.error('配置文件解析失败：' + error.message, 'Failed to parse configuration file: ' + error.message);
        }
    };
    
    reader.onerror = function() {
        alerty.error('文件读取失败', 'Failed to read file');
    };
    
    reader.readAsText(file);
}

// 处理上传的评测机限制配置数据
function processImportedJudgerConfig(importedConfig) {
    const validFields = ['user_id', 'pro_list', 'flg_white', 'defunct', 'language'];
    const unknownFields = [];
    const invalidFields = [];
    
    // 检查未知字段
    Object.keys(importedConfig).forEach(field => {
        if (!validFields.includes(field)) {
            unknownFields.push(field);
        }
    });
    
    // 验证和更新表单数据
    if (importedConfig.pro_list !== undefined) {
        let proListValue = '';
        if (Array.isArray(importedConfig.pro_list)) {
            // 数组格式：直接使用
            proListValue = importedConfig.pro_list.join(',');
        } else if (typeof importedConfig.pro_list === 'string') {
            // 字符串格式：按逗号分割并清理
            proListValue = importedConfig.pro_list.split(',').map(id => id.trim()).filter(id => id).join(',');
        } else {
            invalidFields.push('pro_list: 必须是数组或字符串');
        }
        
        if (proListValue) {
            // 验证题目ID格式（4位数字）
            const problemIds = proListValue.split(',');
            const invalidProblemIds = problemIds.filter(id => !isValidProblemId(id));
            if (invalidProblemIds.length > 0) {
                invalidFields.push(`pro_list: 包含无效的题目ID格式: ${invalidProblemIds.join(', ')}`);
            }
        }
        
        // 设置题目列表
        const problemInputComponent = window.ProblemInputComponents?.get('judger_problem_input');
        if (problemInputComponent) {
            problemInputComponent.setValue(proListValue);
        }
    }
    
    if (importedConfig.flg_white !== undefined) {
        if (typeof importedConfig.flg_white === 'number' && (importedConfig.flg_white === 0 || importedConfig.flg_white === 1)) {
            $('#config_flg_white').prop('checked', importedConfig.flg_white === 0);
            // 同步开关状态
            if (window.csgSwitch) {
                window.csgSwitch.syncSwitchState(document.getElementById('config_flg_white'));
            }
        } else {
            invalidFields.push('flg_white: 必须是0或1');
        }
    }
    
    if (importedConfig.defunct !== undefined) {
        if (typeof importedConfig.defunct === 'number' && (importedConfig.defunct === 0 || importedConfig.defunct === 1)) {
            $('#config_defunct').prop('checked', importedConfig.defunct === 0);
            // 同步开关状态
            if (window.csgSwitch) {
                window.csgSwitch.syncSwitchState(document.getElementById('config_defunct'));
            }
        } else {
            invalidFields.push('defunct: 必须是0或1');
        }
    }
    
    if (importedConfig.language !== undefined) {
        if (Array.isArray(importedConfig.language)) {
            // 验证语言ID是否有效
            const validLanguageKeys = [];
            const languageConfig = JSON.parse($('#oj_language_data').val());
            const maxLangKey = Math.max(...Object.keys(languageConfig).map(k => parseInt(k)));
            
            importedConfig.language.forEach(langKey => {
                if (typeof langKey === 'number' && langKey >= 0 && langKey <= maxLangKey) {
                    validLanguageKeys.push(langKey);
                } else {
                    invalidFields.push(`language: 无效的语言ID ${langKey}`);
                }
            });
            
            // 清除所有语言选择
            $('.language-check').prop('checked', false);
            
            // 设置选中的语言
            validLanguageKeys.forEach(langKey => {
                $(`#lang_${langKey}`).prop('checked', true);
            });
        } else {
            invalidFields.push('language: 必须是数组');
        }
    }
    
    // 更新题目数量显示
    updateProblemCount();
    
    // 重新初始化 CSG Switch 组件
    if (window.csgSwitch) {
        window.csgSwitch.autoInit();
    }
    
    // 显示警告信息
    if (unknownFields.length > 0) {
        alerty.warn(
            `上传的配置包含 ${unknownFields.length} 个未知字段，已忽略：\n${unknownFields.slice(0, 5).join('\n')}${unknownFields.length > 5 ? '\n...' : ''}`,
            `Imported configuration contains ${unknownFields.length} unknown fields, ignored:\n${unknownFields.slice(0, 5).join('\n')}${unknownFields.length > 5 ? '\n...' : ''}`
        );
    }
    
    if (invalidFields.length > 0) {
        alerty.warn(
            `上传的配置包含 ${invalidFields.length} 个无效字段，请检查：\n${invalidFields.join('\n')}`,
            `Imported configuration contains ${invalidFields.length} invalid fields, please check:\n${invalidFields.join('\n')}`
        );
    }
    
    if (unknownFields.length === 0 && invalidFields.length === 0) {
        alerty.success('配置上传成功', 'Configuration imported successfully');
    } else {
        alerty.info('配置已上传，但存在警告信息', 'Configuration imported with warnings');
    }
}

// 页面加载完成后初始化
$(document).ready(function() {
    // 设置题目数量限制显示
    $('#max_pro_limit').text(MAX_PRO_SELECT);
    $('#max_pro_limit_en').text(MAX_PRO_SELECT);
    
    // 初始化语言选项
    initLanguageOptions();
    
    // 绑定保存按钮事件
    $('#saveJudgerConfig').on('click', saveJudgerConfig);
    
    // 绑定题目选择器按钮事件
    $('#config_problem_selector_btn').on('click', openProblemSelector);
    
    // 题目输入组件的事件绑定由组件自身处理
    
    // 绑定下载上传按钮事件
    $('#export_judger_config').on('click', exportJudgerConfig);
    $('#import_judger_config').on('click', function() {
        $('#import_judger_file_input').click();
    });
    
    // 文件选择事件
    $('#import_judger_file_input').on('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            importJudgerConfig(file);
        }
    });
    
    // 初始化题目数量显示
    updateProblemCount();
});

// 删除评测机题目列表中的特定题号
function deleteProblemFromJudger(user_id, problemId, event) {
    // 从事件对象获取题目标题
    let problemTitle = '';
    if (event && event.target) {
        const problemItem = event.target.closest('.problem-item-valid, .problem-item-invalid');
        if (problemItem) {
            problemTitle = problemItem.getAttribute('title-confirm') || '';
        }
    }
    
    // 构建确认消息
    let confirmMessage = `确定要从评测机 ${user_id} 的题目列表中删除题号 ${problemId} 吗？`;
    let confirmMessageEn = `Are you sure you want to remove problem ${problemId} from judger ${user_id}'s problem list?`;
    
    if (problemTitle) {
        confirmMessage += `\n\n题目：${problemTitle}`;
        confirmMessageEn += `\n\nProblem: ${problemTitle}`;
    }
    
    confirmMessage += '\n\n此操作不可撤销。';
    confirmMessageEn += '\n\nThis operation cannot be undone.';
    
    // 显示确认对话框
    alerty.confirm({
        message: confirmMessage,
        message_en: confirmMessageEn,
        callback: function() {
            executeDeleteProblem(user_id, problemId, event);
        },
        callbackCancel: function() {
            alerty.message('操作已取消', 'Operation cancelled');
        }
    });
}

// 执行删除题目操作
function executeDeleteProblem(user_id, problemId, event) {
    $.ajax({
        url: '/admin/judger/del_judger_pro_ajax',
        type: 'POST',
        data: {
            user_id: user_id,
            problem_id: problemId
        },
        dataType: 'json',
        success: function(response) {
            if (response.code === 1) {
                const remainingCount = response.data.remaining_count;
                
                if (remainingCount === 0) {
                    alerty.success(`题目 ${problemId} 删除成功，评测机 ${user_id} 现在无题目限制`, `Problem ${problemId} deleted successfully, judger ${user_id} now has no problem restrictions`);
                } else {
                    alerty.success(`题目 ${problemId} 删除成功，评测机 ${user_id} 剩余 ${remainingCount} 个题目`, `Problem ${problemId} deleted successfully, judger ${user_id} has ${remainingCount} problems remaining`);
                }
                
                // 刷新表格以显示最新数据
                $('#judger_list_table').bootstrapTable('refresh');
                
                // 直接在模态框中删除对应的题目元素
                removeProblemFromModal(problemId, remainingCount, event);
            } else {
                alerty.error(response.msg || '删除题目失败', 'Failed to delete problem');
            }
        },
        error: function(xhr, status, error) {
            handleAjaxError(xhr, status, error, '删除题目');
        }
    });
}

// 通用AJAX错误处理函数
function handleAjaxError(xhr, status, error, operation) {
    const errorMsg = error || '未知错误';
    const operationCn = operation || '操作';
    const operationEn = operation || 'operation';
    
    alerty.error(`${operationCn}失败：${errorMsg}`, `Failed to ${operationEn}: ${errorMsg}`);
}

// 验证题号格式（4位数字）
function isValidProblemId(problemId) {
    return /^\d{4}$/.test(problemId.trim());
}

// 生成题号列表HTML的通用函数
function generateProblemListHtml(proList, problemTitles, count, user_id) {
    let problemListHtml = '<div class="problem-list-detail">';
    problemListHtml += `<div class="mb-2"><strong>题目列表 (共 <span id="judger-prolist-pro-cnt">${count}</span> 个)：</strong></div>`;
    problemListHtml += '<div class="problem-grid">';
    
    // 对题号进行排序
    const sortedProList = [...proList].sort((a, b) => {
        const aNum = parseInt(a.trim());
        const bNum = parseInt(b.trim());
        if (isNaN(aNum) && isNaN(bNum)) return a.trim().localeCompare(b.trim());
        if (isNaN(aNum)) return 1;
        if (isNaN(bNum)) return -1;
        return aNum - bNum;
    });
    
    sortedProList.forEach(problemId => {
        const trimmedId = problemId.trim();
        const title = problemTitles[trimmedId] || '';
        let problemLink;
        
        // 检查是否为有效的4位数字题号
        if (!isValidProblemId(trimmedId)) {
            // 无效题目ID，不添加超链接
            problemLink = `<div class="problem-item-invalid" title="${title || '无效题号格式'}">${trimmedId}<button type="button" class="problem-delete-btn" onclick="deleteProblemFromJudger('${user_id}', '${trimmedId}', event)" title="删除题目 / Delete Problem">×</button></div>`;
        } else {
            // 有效题目ID，添加超链接和标题悬停
            const titleAttr = title ? ` title="${title}" title-confirm="${title}"` : '';
            problemLink = `<div class="problem-item-valid"${titleAttr}><a href="/csgoj/problemset/problem?pid=${trimmedId}" target="_blank" style="color: inherit; text-decoration: none; display: block; padding: 4px 20px 4px 4px;">${trimmedId}</a><button type="button" class="problem-delete-btn" onclick="deleteProblemFromJudger('${user_id}', '${trimmedId}', event)" title="删除题目 / Delete Problem">×</button></div>`;
        }
        
        problemListHtml += problemLink;
    });
    
    problemListHtml += '</div>';
    problemListHtml += '</div>';
    
    return problemListHtml;
}

// 直接在题目列表中删除题目元素
function removeProblemFromModal(problemId, remainingCount, event) {
    if (!event || !event.target) return;
    
    // 从点击的按钮开始，找到包含题号的容器
    const problemItem = event.target.closest('.problem-item-valid, .problem-item-invalid');
    if (!problemItem) return;
    
    // 查找题目计数元素并更新
    const countSpan = problemItem.closest('.problem-list-detail')?.querySelector('#judger-prolist-pro-cnt');
    // 删除找到的题目元素
    problemItem.remove();
    if (countSpan) {
        countSpan.textContent = remainingCount;
    }
    
    // 如果所有题目都删完了，显示空状态
    if (remainingCount === 0) {
        const problemGrid = problemItem.closest('.problem-grid');
        if (problemGrid) {
            problemGrid.innerHTML = '<div class="text-muted text-center py-3">暂无题目</div>';
        }
    }
}

// 更新题号列表弹窗内容
function updateProblemListModal(user_id) {
    // 获取最新的题号列表数据
    $.ajax({
        url: '/admin/judger/get_judger_pro_list_ajax',
        type: 'POST',
        data: {
            user_id: user_id
        },
        dataType: 'json',
        success: function(response) {
            if (response.code === 1) {
                const proList = response.data.pro_list || [];
                const problemTitles = response.data.problem_titles || {};
                const count = response.data.count || 0;
                
                const problemListHtml = generateProblemListHtml(proList, problemTitles, count, user_id);
                
                // 更新当前弹窗的内容
                const currentModal = document.querySelector('.alerty-modal.show');
                if (currentModal) {
                    const modalBody = currentModal.querySelector('.modal-body');
                    if (modalBody) {
                        modalBody.innerHTML = problemListHtml;
                    }
                }
            } else {
                console.error('获取题号列表失败:', response.msg);
            }
        },
        error: function(xhr, status, error) {
            handleAjaxError(xhr, status, error, '获取题号列表');
        }
    });
}