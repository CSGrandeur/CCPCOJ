// 确保在bootstrap-table初始化之前就有可用的queryParams函数
if (typeof window !== 'undefined' && typeof window.queryParams === 'undefined') {
    window.queryParams = function(params) { return params; };
}

var pro_sample_split_reg = /\n##CASE##\n/m;

/**
 * 解析样例数据，兼容新格式（包装对象）和旧格式（##CASE## 分隔）
 * @param {string} sample_str - 样例字符串
 *   - 新格式：{"data_type":"json","data":["样例1","样例2"]}
 *   - 旧格式："样例1\n##CASE##\n样例2"
 * @returns {Array<string>} - 解析后的样例数组
 */
function ParseSampleData(sample_str) {
    if (!sample_str || sample_str.trim() === '') {
        return [];
    }
    
    // 优先检查新格式：尝试解析为 JSON 格式
    try {
        const parsed = JSON.parse(sample_str);
        
        // 检查是否是新格式：有 data_type 字段且值为 'json'
        if (parsed && typeof parsed === 'object' && parsed.data_type === 'json') {
            // 新格式：返回 data 字段（应该是数组）
            if (Array.isArray(parsed.data)) {
                return parsed.data;
            }
            // data 字段不是数组，当作单个样例处理
            return [sample_str];
        }
        
        // 解析成功但不是新格式，继续检查旧格式
    } catch (e) {
        // 不是有效的 JSON，继续检查旧格式
    }
    
    // 其次检查旧格式：包含 ##CASE## 分隔符
    if (sample_str.includes('##CASE##')) {
        return sample_str.split(pro_sample_split_reg);
    }
    
    // 如果都不匹配，返回包含原始字符串的数组（单个样例）
    return [sample_str];
}

function ProblemSampleHtml(sample_in_str, sample_out_str, hlevel=4, is_input_dom=false) {
    let sample_in_list = ParseSampleData(sample_in_str);
    let sample_out_list = ParseSampleData(sample_out_str);
    let sample_num_max = Math.max(sample_in_list.length, sample_out_list.length);
    let sample_html = ``;
    for(let i = 0; i < sample_num_max; i ++) {
        sample_html += OneSample(
            i, 
            i < sample_in_list.length ? DomSantize(sample_in_list[i]) : '',
            i < sample_out_list.length ? DomSantize(sample_out_list[i]) : '',
            hlevel, 
            is_input_dom
        );
    }
    if(sample_num_max == 0) {
        sample_html = `<h${hlevel} class="bilingual-inline">无样例<span class="en-text">No Sample</span></h${hlevel}>`
    }
    return sample_html;
}
function ResetSampleIdx(problem_sample_div) {
    problem_sample_div.find('.ith_case').each((idx, item) => {
        item.innerText = idx;
    });
}
function OneSample(i, sample_in_item, sample_out_item, hlevel=4, is_input_dom=false) {
    let ith_info = `cs="${i}"`;
    let sample_txt_area_type = is_input_dom ? "textarea" : "pre";
    let clss = is_input_dom ? "form-control sample_text_input" : "";
    let func_btn = is_input_dom ? `
        <div class="sample-controls">
            <div class="btn-group btn-group-sm" role="group">
                <button type="button" class="btn btn-outline-primary up_sample_btn" title="上移 (Move Up)">
                    <i class="bi bi-arrow-up"></i>
                </button>
                <button type="button" class="btn btn-outline-danger del_sample_btn" title="双击删除 (Double Click to Delete)">
                    <i class="bi bi-trash"></i>
                </button>
                <button type="button" class="btn btn-outline-primary down_sample_btn" title="下移 (Move Down)">
                    <i class="bi bi-arrow-down"></i>
                </button>
            </div>
        </div>
        ` : "";
    function CopyBtn(i, flg_in) {
        return `<button type="button" class="btn btn-outline-secondary btn-sm sample_copy" target_cs="sample_${flg_in ? 'in' : 'out'}_${i}" title="复制 (Copy)">
                        <i class="bi bi-clipboard"></i>
                    </button>`;
    }
    
    return `
    <div class="sample-item mb-3" ${ith_info}>
        <div class="card border">
            ${func_btn ? `<div class="card-header bg-light py-2 px-3 d-flex justify-content-end">
                ${func_btn}
            </div>` : ''}
            <div class="card-body p-0">
                <div class="row g-0">
                    <div class="col-6 border-end">
                        <div class="sample-section-header bg-light border-bottom px-3 py-2 d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center gap-2">
                                <h4 class="mb-0 text-primary">输入 <span class="en-text">Input</span></h4>
                                <span class="sample-index">#<span class="ith_case">${i}</span></span>
                            </div>
                            ${CopyBtn(i, true)}
                        </div>
                        <div class="sample-content p-3">
                            <${sample_txt_area_type} class="sampledata sample_input_area ${clss}" id="sample_in_${i}" ${ith_info} stype="input" ${is_input_dom ? 'rows="4"' : ''}>${sample_in_item}</${sample_txt_area_type}>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="sample-section-header bg-light border-bottom px-3 py-2 d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center gap-2">
                                <h4 class="mb-0 text-success">输出 <span class="en-text">Output</span></h4>
                                <span class="sample-index">#<span class="ith_case">${i}</span></span>
                            </div>
                            ${CopyBtn(i, false)}
                        </div>
                        <div class="sample-content p-3">
                            <${sample_txt_area_type} class="sampledata sample_output_area ${clss}" id="sample_out_${i}" ${ith_info} stype="output" ${is_input_dom ? 'rows="4"' : ''}>${sample_out_item}</${sample_txt_area_type}>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    `;
}
// ========================================
// 题目列表表格 Formatter 函数
// ========================================

// 前台题目列表标题formatter - 带自适应宽度和spj配色
function FormatterProblemTitle(value, row, index, field) {
    // 根据窗口宽度计算合理的标题宽度
    let windowWidth = window.innerWidth;
    let titleWidth;
    
    if (windowWidth >= 1200) {
        titleWidth = Math.min(850, Math.max(550, (windowWidth - 300) * 0.5));
    } else if (windowWidth >= 992) {
        titleWidth = Math.min(300, Math.max(150, (windowWidth - 300) * 0.3));
    } else if (windowWidth >= 768) {
        titleWidth = Math.min(250, Math.max(120, (windowWidth - 200) * 0.4));
    } else {
        titleWidth = Math.min(200, Math.max(100, windowWidth * 0.4));
    }
    
    // 根据spj值确定颜色类
    let colorClass = '';
    if (row['spj'] == '1') {
        colorClass = 'text-warning';
    } else if (row['spj'] == '2') {
        colorClass = 'text-success';
    } else {
        colorClass = 'text-primary';
    }
    
    return `<a class="text-decoration-none ${colorClass}" title="${value}" href="/csgoj/problemset/problem?pid=${row['problem_id']}" style="max-width: ${titleWidth}px; display: inline-block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${value}</a>`;
}


// 来源formatter - 通用
function FormatterSource(value, row, index) {
    // 如果值为空或不是字符串，直接返回
    if (!value || typeof value !== 'string') {
        return value;
    }
    
    // 清理除了 a 标签之外的所有 HTML 标签
    let processedValue = value;
    let hasChanged = true;
    
    // 循环去除标签，直到没有变化为止
    while (hasChanged) {
        const before = processedValue;
        // 去除除了 a 标签之外的所有标签（包括自闭合标签）
        processedValue = processedValue.replace(/<(?!\/?a\b)[^>]*\/?>/gi, '');
        hasChanged = before !== processedValue;
    }
    
    // 检查最终结果是否只包含 a 标签和文本
    const cleanText = processedValue.replace(/<[^>]*>/g, '');
    
    // 如果处理后的值等于原值，说明没有其他标签被去除，只有 a 标签
    const hasOnlyATags = processedValue === value;
    
    if (hasOnlyATags && cleanText.trim()) {
        // 如果最终只剩下 a 标签和文本，返回原值
        return value;
    }
    
    // 如果清理后的文本为空，返回原值
    if (!cleanText.trim()) {
        return value;
    }
    // 其他情况创建搜索链接
    const search_url = "/csgoj/problemset#search=" + encodeURIComponent(cleanText);
    return `<div style="max-width: 120px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${cleanText}"><a href="${search_url}">${cleanText}</a></div>`;
}


// 归档状态formatter - 管理后台专用
function FormatterProArchive(value, row, index, field) {
    if(row.isadmin) {
        let currentStatus = row.archived == '0' ? "未归档" : "已归档";
        let nextStatus = row.archived == '0' ? "已归档" : "未归档";
        let currentStatusEn = row.archived == '0' ? "UnArchive" : "Archived";
        let nextStatusEn = row.archived == '0' ? "Archived" : "UnArchive";
        
        return `
            <button type='button' field='archived' itemid='${row.problem_id}' 
                class='change_status btn btn-sm ${row.archived == '0' ? "btn-outline-secondary" : "btn-info"}' 
                status='${row.archived}' title="点击更改为${nextStatus}状态(Click to change to ${nextStatusEn})">${row.archived == '0' ? "未归档<span class='en-text'>UnArchive</span>" : "已归档<span class='en-text'>Archived</span>"}
            </button>
        `;
    } else {
        return row.archived == '0' ? "<span class='text-secondary'>未归档<span class='en-text'>UnArchive</span></span>" : "<span class='text-info'>已归档<span class='en-text'>Archived</span></span>";
    }
}

// 附件formatter - 管理后台专用
function FormatterAttach(value, row, index, field) {
    const title = row.title ? (row.title.length > 150 ? row.title.substring(0, 150) + '...' : row.title) : '';
    return `<button type="button" class="btn btn-sm btn-outline-info" 
                data-modal-url="/admin/filemanager/filemanager?item=problem&id=${row.problem_id}" 
                data-modal-title="附件管理 - 题目 #${row.problem_id} - ${title}"
                title="附件管理 (File Manager)">
                <i class="bi bi-paperclip"></i>
            </button>`;
}

// 测试数据formatter - 管理后台专用
function FormatterTestData(value, row, index, field) {
    if (parseInt(value)) {
        const title = row.title ? (row.title.length > 150 ? row.title.substring(0, 150) + '...' : row.title) : '';
        return `<button type="button" class="btn btn-sm btn-outline-success" 
                    data-modal-url="/admin/judge/judgedata_manager?item=problem&id=${row.problem_id}" 
                    data-modal-title="测试数据管理 - 题目 #${row.problem_id} - ${title}"
                    title="测试数据管理 (Test Data Manager)">
                    <i class="bi bi-database"></i>
                </button>`;
    } else {
        return `<span class="btn btn-sm btn-outline-secondary disabled" title="无测试数据(No Test Data)">
                    <i class="bi bi-database-x"></i>
                </span>`;
    }
}

// 编辑formatter - 管理后台专用
function FormatterEdit(value, row, index, field) {
    if (parseInt(value)) {
        return `<a href='/admin/problem/problem_edit?id=${row.problem_id}' class="btn btn-sm btn-outline-primary" title="编辑题目(Edit Problem)">
                    <i class="bi bi-pencil-square"></i>
                </a>`;
    } else {
        return `<span class="btn btn-sm btn-outline-secondary disabled" title="无权限编辑(No Permission)">
                    <i class="bi bi-lock"></i>
                </span>`;
    }
}

// 复制formatter - 管理后台专用
function FormatterCopy(value, row, index, field) {
    return `<a href='/admin/problem/problem_copy?id=${row['problem_id']}' class="btn btn-sm btn-outline-warning" title="复制题目(Copy Problem)">
                <i class="bi bi-files"></i>
            </a>`;
}

// 题目AC状态formatter - 前台专用
function FormatterProblemAc(value, row, index, field) {
    if('ac' in row) {
        return row['ac'] == 1 ? "<span class='text-success'>Y</span>" : "<span class='text-warning'>N</span>";
    } else {
        return "";
    }
}


// ========================================
// 事件监听和初始化
// ========================================

document.addEventListener('click', (e) => {
    if(e.target.classList.contains("sample_copy") || e.target.closest(".sample_copy")) {
        let btn = e.target.classList.contains("sample_copy") ? e.target : e.target.closest(".sample_copy");
        // 通过 target_cs 属性获取目标元素的 id
        let targetId = btn.getAttribute('target_cs');
        if(!targetId) return;
        
        // 通过 id 直接找到对应的元素
        let target_sample = document.getElementById(targetId);
        if(!target_sample) return;
        
        // 复制内容
        let content = target_sample instanceof HTMLTextAreaElement ? target_sample.value : target_sample.innerText;
        if(ClipboardWrite(content)) {
            // 按钮短时特效提示复制成功
            let originalIcon = btn.innerHTML;
            let originalClass = btn.className;
            
            // 临时改变按钮样式和图标
            btn.innerHTML = '<i class="bi bi-check-circle-fill"></i>';
            btn.className = btn.className.replace('btn-outline-secondary', 'btn-success');
            btn.disabled = true;
            
            // 0.5秒后恢复原状
            setTimeout(() => {
                btn.innerHTML = originalIcon;
                btn.className = originalClass;
                btn.disabled = false;
            }, 500);
        }
    }
});

// 题目列表专用初始化函数 - 简化版，复用全局功能
function initProblemList() {
    if (typeof PROBLEM_LIST_CONFIG === 'undefined') return;
    
    // 使用全局的 initBootstrapTableToolbar 函数
    if (typeof initBootstrapTableToolbar === 'function') {
        var toolbarConfig = {
            tableId: PROBLEM_LIST_CONFIG.tableId,
            prefix: PROBLEM_LIST_CONFIG.prefix,
            filterSelectors: PROBLEM_LIST_CONFIG.filterSelectors,
            searchInputId: PROBLEM_LIST_CONFIG.searchInputId
        };
        
        // 前台特有功能：URL搜索同步
        if (PROBLEM_LIST_CONFIG.scene === 'frontend' && PROBLEM_LIST_CONFIG.customHandlers) {
            toolbarConfig.customHandlers = {
                initUrlSearch: function() {
                    let problemset_table = $('#' + PROBLEM_LIST_CONFIG.tableId);
                    let search_cookie_name = problemset_table.attr('data-cookie-id-table') + ".bs.table.searchText";
                    let search_input = $('#' + PROBLEM_LIST_CONFIG.searchInputId);
                    
                    // 使用全局的 GetAnchor/SetAnchor 函数
                    let search_str = GetAnchor("search");
                    if(search_str !== null) {
                        document.cookie = [
                            search_cookie_name, '=', search_str
                        ].join('');
                    }
                    
                    search_input.on('input', function() {
                        SetAnchor(search_input.val(), 'search');
                    });
                    
                    $(window).on('hashchange', function(e) {
                        let search_str = GetAnchor("search");
                        search_input.val(search_str).trigger('keyup');
                    });
                }
            };
        }
        
        initBootstrapTableToolbar(toolbarConfig);
    }
}

// 题目详情页专用功能 - 样例处理
function initProblemDetail() {
    // 从 hidden textarea 获取样例数据（只兼容旧的数据格式，不兼容旧的 HTML 结构）
    if ($('.sample_div').length > 0) {
        let sample_div = $('.sample_div');
        let sample_in_str = $('#sample_input_hidden').val() || '';
        let sample_out_str = $('#sample_output_hidden').val() || '';
        
        // ParseSampleData 函数会兼容旧的数据格式（##CASE## 分隔）和新格式（JSON 包装对象）
        if (sample_in_str || sample_out_str) {
            sample_div.html(ProblemSampleHtml(sample_in_str, sample_out_str));
        }
    }
}

// 窗口大小变化时重新计算标题宽度 - 复用全局防抖机制
$(document).ready(function() {
    let resizeTimeout;
    $(window).on('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(function() {
            // 重新刷新表格以应用新的标题宽度
            if (typeof PROBLEM_LIST_CONFIG !== 'undefined' && typeof $('#' + PROBLEM_LIST_CONFIG.tableId).bootstrapTable !== 'undefined') {
                $('#' + PROBLEM_LIST_CONFIG.tableId).bootstrapTable('refresh');
            }
        }, 300); // 防抖，300ms后执行
    });
    
    // 初始化题目列表（如果存在配置）
    initProblemList();
    
    // 初始化题目详情页（如果存在样例）
    initProblemDetail();
});
