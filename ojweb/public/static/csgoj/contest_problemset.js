let pro_page_info;
let pro_module;
let pro_controller;
let pro_cid;

function FormatterContestProTitle(value, row, index, field) {
    let pid = String.fromCharCode('A'.charCodeAt(0) + row.num)
    
    // 根据窗口宽度计算合理的标题宽度
    let windowWidth = window.innerWidth;
    let titleWidth;
    
    if (windowWidth >= 1200) {
        // 大屏幕：标题列可以更宽
        titleWidth = Math.min(400, Math.max(200, windowWidth * 0.25));
    } else if (windowWidth >= 992) {
        // 中等屏幕：标题列适中
        titleWidth = Math.min(250, Math.max(150, (windowWidth - 250) * 0.25));
    } else if (windowWidth >= 768) {
        // 小屏幕：标题列较窄
        titleWidth = Math.min(250, Math.max(120, (windowWidth - 250) * 0.35));
    } else {
        // 移动端：标题列很窄
        titleWidth = Math.min(300, Math.max(100, windowWidth * 0.3));
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
    
    return `<a class="contest_problem_title text-decoration-none ${colorClass}" title="${value}" href="/${pro_module}/${pro_controller}/problem?cid=${pro_cid}&pid=${pid}" style="max-width: ${titleWidth}px; display: inline-block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${row['title']}</a>`;
}
function FormatterProAc(value, row, index, field) {
    return `<div title="AC提交总数(非AC队伍数)/ AC Total (Not AC Team Count)">${value}</div>`;
}
function FormatterProSubmit(value, row, index, field) {
    return `<div title="提交总数(非提交队伍数)/ Submit Total (Not Team Count)">${value}</div>`;
}
function FormatterProBal(value, row, index, field) {
    // 使用统一的颜色规范化函数处理颜色
    const normalizedColor = NormalizeColorForDisplay(value);
    
    // 如果规范化失败，使用默认颜色或原值
    let cl = normalizedColor || value || '#CCCCCC';
    let cl_title = normalizedColor || value || '#CCCCCC';
    
    // 如果规范化失败但有原值，尝试使用原值
    if (!normalizedColor && value) {
        cl = value;
        cl_title = value;
    }
    
    return `<div class="d-flex justify-content-end align-items-center" title="气球颜色(Balloon Color): ${cl_title}">
                <i class="bi bi-balloon-fill" style="color: ${cl}; font-size: 1.5rem;" title="${cl_title}"></i>
            </div>`;
}
$(document).ready(function(){
    pro_page_info = $('#pro_page_info');
    pro_module = pro_page_info.attr('module');
    pro_controller = pro_page_info.attr('controller');
    pro_cid = pro_page_info.attr('cid');
    
    // 窗口大小变化时重新计算标题宽度
    let resizeTimeout;
    $(window).on('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(function() {
            // 重新刷新表格以应用新的标题宽度
            if (typeof $('.bootstraptable_refresh_local').bootstrapTable !== 'undefined') {
                $('.bootstraptable_refresh_local').bootstrapTable('refresh');
            }
        }, 300); // 防抖，300ms后执行
    });
});