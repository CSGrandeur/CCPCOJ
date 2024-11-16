let pro_page_info;
let pro_module;
let pro_controller;
let pro_cid;
let oj_balloon_icon = '<svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" class="bi bi-balloon-fill" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M8.48 10.901C11.211 10.227 13 7.837 13 5A5 5 0 0 0 3 5c0 2.837 1.789 5.227 4.52 5.901l-.244.487a.25.25 0 1 0 .448.224l.04-.08c.009.17.024.315.051.45.068.344.208.622.448 1.102l.013.028c.212.422.182.85.05 1.246-.135.402-.366.751-.534 1.003a.25.25 0 0 0 .416.278l.004-.007c.166-.248.431-.646.588-1.115.16-.479.212-1.051-.076-1.629-.258-.515-.365-.732-.419-1.004a2.376 2.376 0 0 1-.037-.289l.008.017a.25.25 0 1 0 .448-.224l-.244-.487ZM4.352 3.356a4.004 4.004 0 0 1 3.15-2.325C7.774.997 8 1.224 8 1.5c0 .276-.226.496-.498.542-.95.162-1.749.78-2.173 1.617a.595.595 0 0 1-.52.341c-.346 0-.599-.329-.457-.644Z"/></svg>';

function FormatterProTitle(value, row, index, field) {
    let pid = String.fromCharCode('A'.charCodeAt(0) + row.num)
    return `<a class="contest_problem_title" title="${value}" href="/${pro_module}/${pro_controller}/problem?cid=${pro_cid}&pid=${pid}">${row['title']}</a>`;
}
function FormatterProAc(value, row, index, field) {
    return `<div title="AC的提交数 / Total AC Submits">${value}</div>`;
}
function FormatterProBal(value, row, index, field) {
    let cl=value, cl_title = value;
    // cl 全为小写字母，保持不变
    if (!(/^[a-z]+$/.test(value))) {
        // 否则按16进制颜色处理
        cl = parseInt(value, 16);
        if(cl < 0 || cl >= 16777216 || isNaN(cl)) {
            cl = 3373751;
        }
        cl = cl.toString(16).toUpperCase().padStart(6, '0');
        cl_title = `0x${cl}`;
        cl = `#${cl}`;
    }
    
    return `<div style="display: flex; justify-content: flex-end; align-items: center;" title="Color: ${cl}">
                <div class="oj_balloon_icon" style="color:${cl}; width: 32px;height:32px" title="${cl_title}">${oj_balloon_icon}</div>
            </div>`;
}
function FormatterProSubmit(value, row, index, field) {
    return `<div title="总提交数 / Total Submits">${value}</div>`;
}
$(document).ready(function(){
    pro_page_info = $('#pro_page_info');
    pro_module = pro_page_info.attr('module');
    pro_controller = pro_page_info.attr('controller');
    pro_cid = pro_page_info.attr('cid');
});