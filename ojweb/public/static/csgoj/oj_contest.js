// ========================================
// 比赛列表表格 Formatter 函数
// ========================================

// 比赛标题formatter - 带自适应宽度
function FormatterContestTitle(value, row, index, field) {
    // 根据窗口宽度计算合理的标题宽度
    let windowWidth = window.innerWidth;
    let titleWidth;
    
    if (windowWidth >= 1200) {
        titleWidth = Math.min(600, Math.max(300, (windowWidth - 400) * 0.4));
    } else if (windowWidth >= 992) {
        titleWidth = Math.min(250, Math.max(150, (windowWidth - 300) * 0.3));
    } else if (windowWidth >= 768) {
        titleWidth = Math.min(200, Math.max(120, (windowWidth - 200) * 0.4));
    } else {
        titleWidth = Math.min(150, Math.max(100, windowWidth * 0.3));
    }
    
    // 获取页面模块信息
    let page_module = 'csgoj'; // 默认值
    if (typeof page_info !== 'undefined' && page_info.length > 0) {
        page_module = page_info.attr('page_module') || 'csgoj';
    }
    
    return `<a class="text-decoration-none text-primary" title="${value}" href="/${page_module == 'admin' ? 'csgoj' : 'cpcsys'}/contest/problemset?cid=${row['contest_id']}" style="max-width: ${titleWidth}px; display: inline-block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${value}</a>`;
}

// 比赛类型formatter - 管理后台专用
function FormatterContestType(value, row, index, field) {
    const privateValue = parseInt(value);
    const attach = Math.floor(privateValue / 10 + 1e-8);
    const ckind = privateValue % 10;
    let ckind_str, cl;
    
    switch(ckind) {
        case 0:
            if(row.password != '') {
                cl = 'warning';
                ckind_str = "加密<span class='en-text'>Encrypted</span>";
            } else {
                cl = 'success';
                ckind_str = "公开<span class='en-text'>Public</span>";
            }
            break;
        case 1:
            cl = 'info';
            ckind_str = "私有<span class='en-text'>Private</span>";
            break;
        case 2:
            cl = 'primary';
            ckind_str = "标准<span class='en-text'>Standard</span>";
            break;
        default:
            cl = 'secondary';
            ckind_str = "未知<span class='en-text'>Unknown</span>";
    }
    
    return `<span class='badge bg-${cl}' style="min-width: 60px; display: inline-block; text-align: center;">${ckind_str}</span>`;
}

// 比赛启用状态formatter - 管理后台专用（defunct字段）
function FormatterContestStatus(value, row, index, field) {
    // 这是管理后台的defunct状态，表示比赛是否启用/禁用
    let currentStatus = row.defunct == '0' ? "启用" : "禁用";
    let nextStatus = row.defunct == '0' ? "禁用" : "启用";
    let currentStatusEn = row.defunct == '0' ? "Enabled" : "Disabled";
    let nextStatusEn = row.defunct == '0' ? "Disabled" : "Enabled";
    
    return `
        <button type='button' field='defunct' itemid='${row.contest_id}' 
            class='change_status btn btn-sm ${row.defunct == '0' ? "btn-success" : "btn-warning"}' 
            status='${row.defunct}' title="点击更改为${nextStatus}状态(Click to change to ${nextStatusEn})">${currentStatus}<span class='en-text'>${currentStatusEn}</span>
        </button>
    `;
}

// 比赛进行状态formatter - 前台专用（根据时间判断）
function FormatterContestTimeStatus(value, row, index, field) {
    // 获取当前时间
    let now_time = $('#page_info').attr('time_stamp');
    if(typeof(now_time) == 'undefined' || now_time == null || now_time.length == 0) {
        now_time = new Date().getTime();
    } else {
        now_time *= 1000;
    }
    now_time = Timestamp2Time(now_time);
    
    if(now_time < row.start_time) {
        return "<span class='badge bg-success'>即将开始<span class='en-text'>Coming</span></span>";
    } else if(now_time <= row.end_time) {
        return "<span class='badge bg-danger'>进行中<span class='en-text'>Running</span></span>";
    } else {
        return "<span class='badge bg-secondary'>已结束<span class='en-text'>Ended</span></span>";
    }
}


// 编辑formatter - 管理后台专用
function FormatterContestEdit(value, row, index, field) {
    if (row.is_admin) {
        return `<a href='/admin/contest/contest_edit?id=${row.contest_id}' class="btn btn-sm btn-outline-primary" title="编辑比赛(Edit Contest)">
                    <i class="bi bi-pencil-square"></i>
                </a>`;
    } else {
        return `<span class="btn btn-sm btn-outline-secondary disabled" title="无权限编辑(No Permission)">
                    <i class="bi bi-lock"></i>
                </span>`;
    }
}

// 复制formatter - 管理后台专用
function FormatterContestCopy(value, row, index, field) {
    if (row.is_admin) {
        return `<a href='/admin/contest/contest_copy?id=${row.contest_id}' class="btn btn-sm btn-outline-warning" title="复制比赛(Copy Contest)">
                    <i class="bi bi-files"></i>
                </a>`;
    } else {
        return `<span class="btn btn-sm btn-outline-secondary disabled" title="无权限复制(No Permission)">
                    <i class="bi bi-lock"></i>
                </span>`;
    }
}

// 附件formatter - 管理后台专用
function FormatterContestAttach(value, row, index, field) {
    if (row.is_admin) {
        const title = row.title ? (row.title.length > 150 ? row.title.substring(0, 150) + '...' : row.title) : '';
        return `<button type="button" class="btn btn-sm btn-outline-info" 
                    data-modal-url="/admin/filemanager/filemanager?item=contest&id=${row.contest_id}" 
                    data-modal-title="附件管理 - 比赛 #${row.contest_id} - ${title}"
                    title="附件管理 (File Manager)">
                    <i class="bi bi-paperclip"></i>
                </button>`;
    } else {
        return `<span class="btn btn-sm btn-outline-secondary disabled" title="无权限管理附件(No Permission)">
                    <i class="bi bi-lock"></i>
                </span>`;
    }
}

// 重判formatter - 管理后台专用
function FormatterContestRejudge(value, row, index, field) {
    if (row.is_admin) {
        return `<a href='/cpcsys/admin/contest_rejudge?cid=${row.contest_id}' target='_blank' class="btn btn-sm btn-outline-danger" title="比赛重判控制台 (Contest Rejudge Console)">
                    <i class="bi bi-arrow-clockwise"></i>
                </a>`;
    } else {
        return `<span class="btn btn-sm btn-outline-secondary disabled" title="无权限重判(No Permission)">
                    <i class="bi bi-lock"></i>
                </span>`;
    }
}

// ========================================
// 客户端筛选功能 - 已迁移到 general_formatter.js
// ========================================

// ========================================
// 事件监听和初始化
// ========================================

// 窗口大小变化时重新计算标题宽度
$(document).ready(function() {
    const $contest_list_table = $('#contest_list_table');
    let resizeTimeout;
    $(window).on('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(function() {
            // 重新刷新表格以应用新的标题宽度
            if (typeof $contest_list_table.bootstrapTable !== 'undefined') {
                $contest_list_table.bootstrapTable('refresh');
            }
        }, 300); // 防抖，300ms后执行
    });
    
});
