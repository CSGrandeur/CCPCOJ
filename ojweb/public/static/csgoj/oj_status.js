let status_page_information = $('#status_page_information');
let status_ajax_url = status_page_information.attr('status_ajax_url');
let show_code_url = status_page_information.attr('show_code_url');
let rejudge_url = status_page_information.attr('rejudge_url');
let status_page_where = status_page_information.attr('status_page_where');
let module = status_page_information.attr('module');
let user_id = status_page_information.attr('user_id');
let OJ_MODE = status_page_information.attr('OJ_MODE');
let OJ_STATUS = status_page_information.attr('OJ_STATUS');
function FormatterSolutionId(value, row, index, field) {
    if("contest_id" in row && row.contest_id != 0) {
        let status_url = "/csgoj/contest/status";
        if(OJ_MODE == 'cpcsys') {
            if(OJ_STATUS == 'cpc') {
                status_url = "/cpcsys/contest/status";
            } else {
                status_url = "/expsys/contest/status";
            }
        } else if(OJ_STATUS == 'exp') {
            status_url = "/csgoj/contestexp/status";
        }
        return `<a href="${status_url}?cid=${row.contest_id}#solution_id=${value}" title="Contest: ${row.contest_id}">${value}</a>`;
    }
    return value;
}
function FormatterProblemId(value, row, index, field) {
    if('contest_type' in row) {
        if(row['contest_type'] == 5) {
            return value;
        } else {
            return "<a href='problem?cid=" + row['contest_id'] + "&pid=" + value +  "'>" + value + "</a>";
        }        
    } else {
        return "<a href='/csgoj/problemset/problem?pid=" + value + "'>" + value + "</a>";
    }
}
function FormatterStatusUser(value, row, index, field) {
    if(('contest_type' in row ) && (row['contest_type'] == 5 || row['contest_type'] == 2)) {
        // standard contest、exam
        return "<a href='teaminfo?cid=" + row['contest_id'] + "&team_id=" + value + "'>" + value + "</a>";
    } else if(value.startsWith('#cpc')) {
        let cid = value.split('_')[0].substring(4);
        return "<a href='/" + module + "/contest/contest?cid=" + cid + "' title='Contestant in #" + cid + "'>" + value + "</a>";
    }
    return "<a href='/" + module + "/user/userinfo?user_id=" + value + "'>" + value + "</a>";
}
function FormatterStatusResult(value, row, index, field) {
    if(typeof(value) == undefined || value == '-') {
        return '-';
    }
    if(value < 4) {
        return `<div class='result-span loading-overlay text-secondary' title='${row.res_text}'><span class='loading-text'>${row.res_short}</span><div class='spinner-overlay'><div class='spinner-border spinner-border-sm' role='status'><span class='visually-hidden'>Loading...</span></div></div></div>`;
    } else if(row['res_show']) {
        return `<div class='btn result_show_btn btn-${row['res_color']} result-btn' title='${row.res_text}'>${row.res_short}</div>`;
    } else {
        return `<span class='text-${row['res_color']} result-span' title='${row.res_text}'>${row.res_short}</span>`;
    }
}
function FormatterPassRate(value, row, index, field) {
    return value === null ? '-' : `${value * 100}%`;
}
function FormatterLanguage(value, row, index, field) {
    if(('code_show' in row) && row['code_show']) {
        let value_show = value;
        switch(value) {
            case 'Python3': value_show = 'Py3'; break;
            default: value_show = value;
        }
        return `<button class='btn btn-primary lang-btn' solution_id='${row['solution_id']}' title='${value}'>${value_show}</button>`;
    } else {
        return `<strong class='lang-strong'>${value}</strong>`;
    }
}
function FormatterRejudge(value, row, index, field) {
    if("contest_id" in row && row.contest_id != 0 && status_page_where != 'contest') {
        row.allow_rejudge = false;
        return `<a href="/csgoj/admin/contest_rejudge?cid=${row.contest_id}" title="比赛中的代码需在比赛中执行重测 (Rejudge in contest)" target="_blank"><i class="bi bi-calendar-event"></i></span>`
    } else {
        row.allow_rejudge = row.result != 4;
        return row.result == 4 ? `<span class="text-muted" title="AC 的代码不能快捷重测，请到后台慎重执行"><i class="bi bi-shield-check"></i></span>` : `<button class='btn btn-warning btn-sm' title="重新评测 (Rejudge)"><i class="bi bi-arrow-clockwise"></i></button>`;
    }
}
function FormatterSim(value, row, index, field) {
    if(value == null) return '-';
    return `<a href='status_code_compare?sid0=${row.solution_id}&sid1=${row.sim_s_id}&cid=${row.contest_id}' target='_blank' class='btn btn-primary'>${row.sim_s_id}:${row.sim}%</a>`;
}
//table related
var $table = $('#status_table');
var $ok = $('#status_ok'), $refresh = $('#status_refresh'), $clear = $('#status_clear');
var status_table_div = $('#status_table_div');
var status_toolbar = $('#status_toolbar');

// 移除旧的modal相关代码，使用新的 CodeViewer

$(window).keydown(function(e) {
    if (e.keyCode == 116 && !e.ctrlKey) {
        if(window.event){
            try{e.keyCode = 0;}catch(e){}
            e.returnValue = false;
        }
        e.preventDefault();
        RefreshTable();
    }
});
var lastQuery = [];
$ok.on('click', function () {
    // 点下 search 按钮
    $table.bootstrapTable('refresh', {pageNumber: 1});
});
$clear.on('click', function() {
    SetFilter(true);
});
$refresh.on('click', function(){
    // 点下刷新/f5
    RefreshTable();
});
function RefreshTable()
{
    status_toolbar.find('input[name]').each(function () {
        $(this).val(lastQuery[$(this).attr('name')]);
    });
    status_toolbar.find('select[name]').each(function () {
        $(this).val(lastQuery[$(this).attr('name')]);
    });
    $table.bootstrapTable('refresh');

}
function queryParams(params) {
    status_toolbar.find('input[name]').each(function () {
        params[$(this).attr('name')] = $(this).val();
        lastQuery[$(this).attr('name')] = $(this).val();
    });
    status_toolbar.find('select[name]').each(function () {
        params[$(this).attr('name')] = $(this).val();
        lastQuery[$(this).attr('name')] = $(this).val();
    });
    return params;
}
$('.fake-form').on('keypress', function(e){
    // it'ts not a real form, so overload 'enter' to take effect.
    if(event.keyCode == 13){
        $('#status_ok').click();
    }
});
var timer_ids = [];
let flg_auto_refresh_status=false;
// 已经移除了 ensureResultModal 函数，使用独立的 RuninfoViewer 组件代替
function auto_refresh_results(time_cnt=2) {
    if(!flg_auto_refresh_status) {
        return;
    }
    // refresh results which are running.
    let status_data = $table.bootstrapTable('getData')
    let solution_id_list = [];
    for(let i in status_data) {
        if(status_data[i]['result'] == '-' || status_data[i]['result'] >= 4) continue;
        solution_id_list.push(status_data[i]['solution_id']);
    }
    if(solution_id_list.length > 0) {
        $.get(
            status_ajax_url,
            {
                'solution_id_list': solution_id_list
            },
            function(ret) {
                let finish_flag = true;
                for(let i in ret.rows) {
                    const row_in_table = $table.bootstrapTable('getRowByUniqueId', ret.rows[i].solution_id);
                    if(ret.rows[i].result != row_in_table.result || ret.rows[i].memory != row_in_table.memory) {
                        $table.bootstrapTable('updateByUniqueId', {
                            id: ret.rows[i].solution_id,
                            row: ret.rows[i]
                        });
                    }
                    if(ret.rows[i]['result'] != '-' && ret.rows[i]['result'] < 4) {
                        finish_flag = false;
                    }
                }
                if(finish_flag) {
                    flg_auto_refresh_status = false;
                }
                if(flg_auto_refresh_status) {
                    setTimeout(function(){auto_refresh_results(time_cnt * 2);}, time_cnt * 1000);
                }
            }
        )
    } else {
        flg_auto_refresh_status = false;
    }
}
function BtnCodeShow(td, row) {
    // 点击查看代码 - 使用新的CodeViewer
    let solution_id = row.solution_id;
    if(!row.code_show) return;
    
    // 显示加载状态
    if (window.codeViewer) {
        window.codeViewer.contentContainer.innerHTML = `
            <div class="text-center p-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">加载中...</span>
                </div>
                <p class="mt-2 text-muted">正在加载代码...</p>
                <p class="en-text">Loading code...</p>
            </div>
        `;
        window.codeViewer.modal.show();
    }
    
    $.get(
        show_code_url,
        {
            'solution_id': solution_id,
            'cid': status_page_information.attr('cid')
        },
        function(ret){
            if(ret?.code == 1) {
                let data = ret.data;
                let language = data.language ?? row?.language;
                // 使用新的CodeViewer显示代码（前端会生成头部注释）
                if (window.codeViewer) {
                    window.codeViewer.showCode(solution_id, language, {
                        solution_id: solution_id,
                        source: data.source,
                        submitTime: data.submit_time ?? row?.in_date,
                        codeLength: data.code_length ?? row?.code_length,
                        problemId: data.problem_id ?? row?.problem_id,
                        user_id: data.user_id ?? row?.user_id,
                        result: data.result ?? row?.result,
                        time: data.time ?? row?.time,
                        memory: data.memory ?? row?.memory,
                        contest_id: data?.contest_id ?? row?.contest_id
                    });
                }
            }
            else {
                // 显示错误信息
                if (window.codeViewer) {
                    window.codeViewer.contentContainer.innerHTML = `
                        <div class="text-center p-5 text-danger">
                            <i class="bi bi-exclamation-triangle fs-1"></i>
                            <p class="mt-2">加载代码失败</p>
                            <p class="en-text">Failed to load code</p>
                        </div>
                    `;
                }
            }
        }
    ).fail(function() {
        // 网络错误处理
        if (window.codeViewer) {
            window.codeViewer.contentContainer.innerHTML = `
                <div class="text-center p-5 text-danger">
                    <i class="bi bi-wifi-off fs-1"></i>
                    <p class="mt-2">网络连接失败</p>
                    <p class="en-text">Network connection failed</p>
                </div>
            `;
        }
    });
}
function BtnResultShow(td, row) {
    // 查看Result的提示信息
    if(!row.res_show) return;
    let solution_id = row['solution_id'];

    $.get(
        status_page_information.attr('show_res_url'),
        {
            'solution_id': solution_id,
            'cid': status_page_information.attr('cid')
        },
        function (ret) {
            window.runinfoViewer.showInfo(solution_id, ret);
        });
}
function SetStatusButton() {
    // show running information and code.
    $table.on('click-cell.bs.table', function(e, field, td, row){
        if(field == 'language') {
            BtnCodeShow(td, row)
        } else if(field == 'result') {
            BtnResultShow(td, row);
        } else if(field == 'rejudge' && row.allow_rejudge) {
            alerty.confirm({
                message: `确定要重测该提交 [提交号=<strong class='text-danger'>${row.solution_id}</strong>]?`, 
                message_en: `Confirm to rejudge solution [RunID=<strong class='text-danger'>${row.solution_id}</strong>]?`, 
                callback: function() {
                    $.post(rejudge_url, {solution_id: row.solution_id, rejudge_res_check: ['any']}, function(ret) {
                        if(ret.code == 1) {
                            $table.bootstrapTable('updateByUniqueId', {
                                id: row.solution_id,
                                row: {
                                    result: 0,
                                    memory: 0,
                                    time: 0,
                                    res_text: 'Pending Rejudging'
                                }
                            });
                            if(!flg_auto_refresh_status) {
                                flg_auto_refresh_status = true;
                                auto_refresh_results();
                            }
                            alerty.success(`重测启动 [RunID=${row.solution_id}]`, `Rejudge started`);
                        } else {
                            alerty.error(`重测启动失败 [RunID=${row.solution_id}]`, `Rejudge start failed\n${ret.msg}`);
                        }

                    });
                }
            });
        }
    });
}

// 处理搜索框动态anchor    
function SetFilter(clear=false) {
    $('.status_filter').each(function(index, elem){
        let search_input = $(this);
        let search_name = search_input.attr('name');
        if(clear)
        {
            if(search_input.is('input'))
                search_input.val('');
            else
                search_input.val(-1);
            SetAnchor(null, search_name);
        } else {
            let search_str = GetAnchor(search_name);
            search_input.unbind('input').on('input', function() {
                SetAnchor(search_input.val(), search_name);
            });
            if(search_str !== null) {
                search_input.val(search_str);
            }
        }
    });
    $table.bootstrapTable('refresh', {pageNumber: 1});
    $('.status_filter').change(function(){
        $table.bootstrapTable('refresh', {pageNumber: 1});
    });
}
$(document).ready(function(){
    SetFilter();
    SetStatusButton();
    $table.on('post-body.bs.table', function(){
        //处理rank宽度
        if($table[0].scrollWidth > status_table_div.width())
            status_table_div.width($table[0].scrollWidth + 20);
        //on table body loaded, refresh the running results.
        for(let i in timer_ids) {
            //clear timeoutid if the table refreshed
            clearTimeout(timer_ids[i]);
            delete timer_ids[i];
        }
    });
    $table.on('load-success.bs.table', function(){
        flg_auto_refresh_status = true;
        auto_refresh_results();
    });
});