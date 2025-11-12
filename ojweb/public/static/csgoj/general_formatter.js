// ========================================
// 通用格式化函数
// ========================================

// 通用日期时间格式化函数
// 日期时间格式化器配置
if(typeof(DATE_TIME_CONFIG) == 'undefined') {
    window.DATE_TIME_CONFIG = {
        FONT_SIZE_PRIMARY: '0.75em',    // 主要信息字号
        FONT_SIZE_SECONDARY: '1em',  // 次要信息字号（text-muted会进一步缩小）
        LINE_HEIGHT: '1.2'             // 行高
    };
}

// 通用的对象转字符串方法 - 简单粗暴版本
function objectToString(obj) {
    if (obj === null || obj === undefined) return '';
    if (typeof obj === 'string') return obj;
    if (typeof obj === 'number' || typeof obj === 'boolean') return obj.toString();
    if (typeof obj === 'object') {
        try {
            // 使用JSON.stringify暴力转换，然后清理掉JSON格式字符
            return JSON.stringify(obj).trim();
        } catch (e) {
            // 如果JSON.stringify失败，回退到toString
            return obj.toString();
        }
    }
    return obj.toString();
}
// ========================================
// 通用工具栏管理功能
// ========================================

// 全局工厂：根据标识生成 queryParams 处理函数
// 用法：window["queryParams_"+tableId] = makeQueryParams(prefix, searchInputId)
if (typeof window.makeQueryParams === 'undefined') {
    window.makeQueryParams = function(prefix, searchInputId, extraProcessor) {
        return function(params) {
            // 处理筛选条件（按 class 前缀匹配）
            $(`.${prefix}_filter`).each(function() {
                const name = $(this).attr('name');
                const val = $(this).val();
                if (name && val != null && val != '-1') {
                    params[name] = val;
                }
            });

            // 处理搜索
            if (searchInputId) {
                const search = $(`#${searchInputId}`).val();
                if (search && search.trim() !== '') {
                    params.search = search.trim();
                }
            }

            // 额外定制处理（可选）
            if (typeof extraProcessor === 'function') {
                params = extraProcessor(params) || params;
            }

            return params;
        };
    };
}

/**
 * 初始化Bootstrap Table工具栏功能（服务器端分页）
 * @param {Object} config - 配置对象
 * @param {string} config.tableId - 表格ID
 * @param {string} config.prefix - 按钮和输入框ID前缀
 * @param {Array} config.filterSelectors - 筛选选择器数组，如 ['spj', 'defunct']
 * @param {string} config.searchInputId - 搜索框ID
 * @param {Function} config.customQueryParams - 自定义查询参数处理函数
 * @param {Object} config.customHandlers - 自定义事件处理器
 */
function initBootstrapTableToolbar(config) {
    const {
        tableId,
        prefix,
        filterSelectors = [],
        searchInputId,
        customQueryParams = null,
        customHandlers = {}
    } = config;
    
    const table = $(`#${tableId}`);
    
    // 保留原接口：如需通过本函数生成 handler，可使用 makeQueryParams
    
    // 工具栏按钮事件处理
    $(function() {
        // 刷新按钮
        $(`#${prefix}_refresh`).on('click', function() {
            table.bootstrapTable('refresh');
        });
        
        // 清空筛选条件按钮
        $(`#${prefix}_clear`).on('click', function() {
            filterSelectors.forEach(selector => {
                $(`select[name="${selector}"]`).val('-1');
            });
            if (searchInputId) {
                $(`#${searchInputId}`).val('');
            }
            table.bootstrapTable('refresh');
        });
        
        // 应用筛选按钮
        $(`#${prefix}_filter`).on('click', function() {
            table.bootstrapTable('refresh');
        });
        
        // 筛选条件变化时自动刷新
        $(`.${prefix}_filter`).change(function() {
            table.bootstrapTable('refresh');
        });
        
        // 搜索框实时搜索（防抖处理）
        if (searchInputId) {
            let searchTimeout;
            $(`#${searchInputId}`).on('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    table.bootstrapTable('refresh');
                }, 500); // 500ms 防抖
            });
        }
        
        // 表格加载完成后初始化Bootstrap 5 tooltips
        table.on('post-body.bs.table', function(){
            // 表格刷新后重新初始化 tooltip
            if (window.autoTooltips) {
                window.autoTooltips.refresh();
            }
        });
        
        // 执行自定义处理器
        if (customHandlers && typeof customHandlers === 'object') {
            Object.keys(customHandlers).forEach(key => {
                if (typeof customHandlers[key] === 'function') {
                    customHandlers[key]();
                }
            });
        }
    });
}

/**
 * 初始化Bootstrap Table工具栏功能（客户端分页）
 * @param {Object} config - 配置对象
 * @param {string} config.tableId - 表格ID
 * @param {string} config.prefix - 按钮和输入框ID前缀
 * @param {Array} config.filterSelectors - 筛选选择器数组，如 ['spj', 'defunct']
 * @param {string} config.searchInputId - 搜索框ID
 * @param {Object} config.searchFields - 搜索字段配置，如 {content: 'content', team_id: 'team_id'}
 * @param {Function} config.customFilterAlgorithm - 自定义筛选算法
 * @param {Object} config.customHandlers - 自定义事件处理器
 */
function initBootstrapTableClientToolbar(config) {
    const {
        tableId,
        prefix,
        filterSelectors = [],
        searchInputId,
        searchFields = {},
        customFilterAlgorithm = null,
        customHandlers = {}
    } = config;
    
    const table = $(`#${tableId}`);
    
    // 工具栏按钮事件处理
    $(function() {
        // 刷新按钮
        $(`#${prefix}_refresh`).on('click', function() {
            table.bootstrapTable('refresh');
        });
        
        // 清空筛选条件按钮
        $(`#${prefix}_clear`).on('click', function() {
            filterSelectors.forEach(selector => {
                $(`select[name="${selector}"]`).val('-1');
            });
            if (searchInputId) {
                $(`#${searchInputId}`).val('');
            }
            // 清空bootstrap-table的筛选
            table.bootstrapTable('filterBy', {});
        });
        
        // 应用筛选按钮
        $(`#${prefix}_filter`).on('click', function() {
            applyClientFilter();
        });
        
        // 筛选条件变化时自动刷新
        $(`.${prefix}_filter`).change(function() {
            applyClientFilter();
        });
        
        // 搜索框实时搜索（防抖处理）
        if (searchInputId) {
            let searchTimeout;
            $(`#${searchInputId}`).on('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    applyClientFilter();
                }, 500); // 500ms 防抖
            });
        }
        
        // 表格加载完成后初始化Bootstrap 5 tooltips
        table.on('post-body.bs.table', function(){
            // 表格刷新后重新初始化 tooltip
            if (window.autoTooltips) {
                window.autoTooltips.refresh();
            }
        });
        
        // 执行自定义处理器
        if (customHandlers && typeof customHandlers === 'object') {
            Object.keys(customHandlers).forEach(key => {
                if (typeof customHandlers[key] === 'function') {
                    customHandlers[key]();
                }
            });
        }
    });
    
    // 应用客户端筛选
    function applyClientFilter() {
        // 获取搜索文本
        let searchText = '';
        if (searchInputId) {
            const search = $(`#${searchInputId}`).val();
            if (search && search.trim() !== '') {
                searchText = search.trim();
            }
        }
        
        // 构建筛选数据
        let filterData = {};
        // 添加搜索条件
        if (searchText) {
            // 如果有自定义搜索字段配置，使用它
            if (Object.keys(searchFields).length > 0) {
                Object.keys(searchFields).forEach(field => {
                    filterData[field] = searchText;
                });
            }
            // 同时添加通用搜索，确保搜索所有字段
            filterData.search = searchText;
        }
        
        // 添加筛选条件
        filterSelectors.forEach(selector => {
            const value = $(`select[name="${selector}"]`).val();
            if (value != -1) {
                filterData[selector] = value;
            }
        });
        
        // 应用筛选
        if (customFilterAlgorithm && typeof customFilterAlgorithm === 'function') {
            // 使用自定义筛选算法
            table.bootstrapTable('filterBy', filterData, {
                filterAlgorithm: customFilterAlgorithm
            });
        } else {
                // 使用默认筛选算法
                table.bootstrapTable('filterBy', filterData, {
                    filterAlgorithm: function(row, filters) {
                        // 检查filters是否存在
                        if (!filters) {
                            return true; // 没有筛选条件时显示所有行
                        }
                        
                        let searchMatch = true; // 搜索条件匹配结果
                        let filterMatch = true; // 筛选条件匹配结果
                        
                        // 处理搜索条件（任意字段匹配 - OR 逻辑）
                        let hasSearchCondition = false;
                        
                        // 处理通用搜索（filters.search）
                        if (filters.search !== undefined && filters.search !== '') {
                            hasSearchCondition = true;
                            const searchLower = filters.search.toLowerCase();
                            searchMatch = false; // 初始化为false，任意字段匹配就为true
                            
                            // 搜索所有字段
                            Object.keys(row).forEach(key => {
                                if (row[key] && row[key].toString().toLowerCase().includes(searchLower)) {
                                    searchMatch = true;
                                }
                            });
                        }
                        
                        // 处理自定义搜索字段（searchFields配置的字段）
                        if (Object.keys(searchFields).length > 0) {
                            Object.keys(searchFields).forEach(field => {
                                if (filters[field] !== undefined && filters[field] !== '') {
                                    hasSearchCondition = true;
                                    const searchLower = filters[field].toLowerCase();
                                    const fieldValue = row[searchFields[field]];
                                    
                                    const searchableText = objectToString(fieldValue);
                                    if (searchableText.toLowerCase().includes(searchLower)) {
                                        searchMatch = true;
                                    }
                                }
                            });
                        }
                        
                        // 处理筛选条件（不同筛选器之间是交集 - AND 逻辑）
                        let hasFilterCondition = false;
                        filterSelectors.forEach(selector => {
                            if (filters[selector] !== undefined && filters[selector] !== '' && filters[selector] !== '-1') {
                                hasFilterCondition = true;
                                // 对于数组字段（如langlist），需要特殊处理
                                if (selector === 'langlist' && Array.isArray(row[selector])) {
                                    // 检查数组中是否包含筛选值
                                    if (!row[selector].includes(filters[selector])) {
                                        filterMatch = false;
                                    }
                                } else {
                                    // 直接比较，支持字符串和数字
                                    if (row[selector] != filters[selector]) {
                                        filterMatch = false;
                                    }
                                }
                            }
                        });
                        
                        // 如果没有搜索条件，搜索匹配为true
                        if (!hasSearchCondition) {
                            searchMatch = true;
                        }
                        
                        // 如果没有筛选条件，筛选匹配为true
                        if (!hasFilterCondition) {
                            filterMatch = true;
                        }
                        
                        // 搜索和筛选都匹配才显示该行
                        return searchMatch && filterMatch;
                    }
                });
        }
    }
}

/**
 * 页码跳转功能
 * @param {string} tableId - 表格ID
 * @param {string} jumpButtonId - 跳转按钮ID
 * @param {string} pageInputId - 页码输入框ID
 */
function initPageJump(tableId, jumpButtonId, pageInputId) {
    const table = $(`#${tableId}`);
    const jumpButton = $(`#${jumpButtonId}`);
    const pageInput = $(`#${pageInputId}`);
    
    function jumpPage() {
        var jump_page = pageInput.val();
        if (typeof(jump_page) != 'undefined' && !isNaN(jump_page)) {
            if (jump_page.length == 0) {
                jump_page = 1;
            } else {
                jump_page = parseInt(jump_page);
                if (jump_page < 1) {
                    jump_page = 1;
                } else if (jump_page > table.bootstrapTable('getOptions')['totalPages']) {
                    jump_page = table.bootstrapTable('getOptions')['totalPages'];
                }
            }
            table.bootstrapTable('selectPage', jump_page);
            pageInput.val(jump_page);
        }
    }
    
    jumpButton.on('click', jumpPage);
    pageInput.on('keydown', function(e) {
        if (e.keyCode == '13') {
            jumpPage();
        }
    });
}



function createDateTimeFormatter(config) {
    return function(value, row, index, field) {
        if (!value || typeof(value) != 'string' || value.trim() === '') {
            return '<span class="text-muted">-</span>';
        }
        value = value.substring(2);
        
        // 按空格分割日期和时间部分
        let parts = value.trim().split(' ');
        if (parts.length !== 2) {
            return value; // 如果格式不符合预期，直接返回原值
        }
        
        let datePart = parts[0]; // 2024-10-24
        let timePart = parts[1]; // 12:25:18
        
        // 根据配置返回不同优先级的显示，但保持上下位置一致
        if (config.priority === 'date') {
            // 日期优先：日期显著，时间次要
            return `<div style="font-size: ${window.DATE_TIME_CONFIG.FONT_SIZE_PRIMARY}; line-height: ${window.DATE_TIME_CONFIG.LINE_HEIGHT};">
                        <div class="fw-bold">${datePart}</div>
                        <div class="text-muted" style="font-size: ${window.DATE_TIME_CONFIG.FONT_SIZE_SECONDARY};">${timePart}</div>
                    </div>`;
        } else if (config.priority === 'time') {
            // 时间优先：时间显著，日期次要
            return `<div style="font-size: ${window.DATE_TIME_CONFIG.FONT_SIZE_PRIMARY}; line-height: ${window.DATE_TIME_CONFIG.LINE_HEIGHT};">
                        <div class="text-muted" style="font-size: ${window.DATE_TIME_CONFIG.FONT_SIZE_SECONDARY};">${datePart}</div>
                        <div class="fw-bold">${timePart}</div>
                    </div>`;
        } else if (config.priority === 'both') {
            // 两者都显著：日期和时间都加粗显示
            return `<div style="font-size: ${window.DATE_TIME_CONFIG.FONT_SIZE_PRIMARY}; line-height: ${window.DATE_TIME_CONFIG.LINE_HEIGHT};">
                        <div class="fw-bold">${datePart}</div>
                        <div class="fw-bold">${timePart}</div>
                    </div>`;
        } else {
            // 平衡显示：两者都不显著
            return `<div style="font-size: ${window.DATE_TIME_CONFIG.FONT_SIZE_SECONDARY}; line-height: ${window.DATE_TIME_CONFIG.LINE_HEIGHT};">
                        <div>${datePart}</div>
                        <div class="text-muted">${timePart}</div>
                    </div>`;
        }
    };
}

// 日期优先formatter（年月日内容显著）
function FormatterDate(value, row, index, field) {
    return createDateTimeFormatter({ priority: 'date' })(value, row, index, field);
}

// 时间优先formatter（时分秒内容显著）
function FormatterTime(value, row, index, field) {
    return createDateTimeFormatter({ priority: 'time' })(value, row, index, field);
}

// 两者都显著formatter（日期和时间都加粗）
function FormatterDateTimeBoth(value, row, index, field) {
    return createDateTimeFormatter({ priority: 'both' })(value, row, index, field);
}

// 平衡formatter（两者都不显著）
function FormatterDateTime(value, row, index, field) {
    return createDateTimeFormatter({ priority: 'balanced' })(value, row, index, field);
}

// 通用defunct状态formatter生成器
function createDefunctFormatter(config) {
    return function(value, row, index, field) {
        if(row.is_admin) {
            let currentStatus = row.defunct == '0' ? config.publicText : config.hiddenText;
            let nextStatus = row.defunct == '0' ? config.hiddenText : config.publicText;
            let currentStatusEn = row.defunct == '0' ? config.publicTextEn : config.hiddenTextEn;
            let nextStatusEn = row.defunct == '0' ? config.hiddenTextEn : config.publicTextEn;
            
            const iconClass = row.defunct == '0' ? 'bi bi-unlock-fill ' : 'bi bi-lock-fill ';
            const textContent = row.defunct == '0' ? 
                (`<span class='en-text'>${config.publicTextEn}</span>`) : 
                (`<span class='en-text'>${config.hiddenTextEn}</span>`);
            
            return `
                <button type='button' field='defunct' itemid='${row[config.idField] ?? ""}' 
                    class='change_status btn btn-sm ${row.defunct == '0' ? "btn-success" : "btn-warning"}' 
                    status='${row.defunct}' title="点击更改为${nextStatus}状态(Click to change to ${nextStatusEn})">
                    <i class="${iconClass}"></i>${textContent}
                </button>
            `;
        } else {
            const iconClass = row.defunct == '0' ? 'bi bi-unlock-fill ' : 'bi bi-lock-fill ';
            const textContent = row.defunct == '0' ? 
                (`<span class='en-text'>${config.publicTextEn}</span>`) : 
                (`<span class='en-text'>${config.hiddenTextEn}</span>`);
            
            return row.defunct == '0' ? 
                `<span class='text-success'><i class="${iconClass}"></i>${textContent}</span>` : 
                `<span class='text-warning'><i class="${iconClass}"></i>${textContent}</span>`;
        }
    };
}

// 题目defunct状态formatter（公开/隐藏）
function FormatterDefunctPro(value, row, index, field) {
    return createDefunctFormatter({
        idField: 'problem_id',
        publicText: '公开',
        hiddenText: '隐藏',
        publicTextEn: 'Public',
        hiddenTextEn: 'Hidden'
    })(value, row, index, field);
}

// 比赛defunct状态formatter（启用/禁用）
function FormatterDefunctContest(value, row, index, field) {
    return createDefunctFormatter({
        idField: 'contest_id',
        publicText: '启用',
        hiddenText: '禁用',
        publicTextEn: 'Public',
        hiddenTextEn: 'Hidden'
    })(value, row, index, field);
}


// 队伍ID formatter（带链接）
function FormatterTeamId(value, row, index, field) {
    if (row.user_info_url) {
        return `<a href="${row.user_info_url}">${value}</a>`;
    }
    return value;
}
