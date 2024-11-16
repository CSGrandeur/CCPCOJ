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
        return "<div class='inline-waiting'><div class='load-container loadingblock'><div class='loader'>Loading...</div></div>" + row['res_text'] + "</div>";
    } else if(row['res_show']) {
        return "<div class='btn result_show_btn btn-" + row['res_color'] + "'>" + row['res_text'] + "</div>";
    } else {
        return "<span class='text-" + row['res_color'] + "'>" + row['res_text'] + "</span>";
    }
}
function FormatterPassRate(value, row, index, field) {
    return value === null ? '-' : `${value * 100}%`;
}
function FormatterLanguage(value, row, index, field) {
    if(('code_show' in row) && row['code_show']) {
        return "<button class='btn btn-primary' solution_id='" + row['solution_id'] + "' >" + value + "</button>";
    } else {
        return value;
    }
}
function FormatterRejudge(value, row, index, field) {
    if("contest_id" in row && row.contest_id != 0 && status_page_where != 'contest') {
        row.allow_rejudge = false;
        return "IN CONTEST"
    } else {
        row.allow_rejudge = row.result != 4;
        return row.result == 4 ? '-' : `<button class='btn btn-warning'>Rejudge</button>`;
    }
}
function FormatterSim(value, row, index, field) {
    if(value == null) return '-';
    return `<a href='status_code_compare?sid0=${row.solution_id}&sid1=${row.sim_s_id}&cid=${row.contest_id}' target='_blank' class='btn btn-info'>${row.sim_s_id}:${row.sim}%</a>`;
}
//table related
var $table = $('#status_table');
var $ok = $('#status_ok'), $refresh = $('#status_refresh'), $clear = $('#status_clear');
var status_table_div = $('#status_table_div');
var status_toolbar = $('#status_toolbar');

var content_show_modal = $('#content_show_modal');
var content_show_modal_label = $('#content_show_modal_label');
var content_show_modal_content = $('#content_show_modal_content');
var content_show_modal_label_span = $('#content_show_modal_label_span');

var clipboard = new ClipboardJS('.content_show_modal_copy');

clipboard.on('success', function(e) {
    alertify.success('Content Copied');
    e.clearSelection();
});

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
                for(let i in ret['rows']) {                    
                    $table.bootstrapTable('updateByUniqueId', {
                        id: ret['rows'][i]['solution_id'],
                        row: ret['rows'][i]
                    });
                    if(ret['rows'][i]['result'] != '-' && ret['rows'][i]['result'] < 4) {
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
    // 点击查看代码
    let solution_id = row.solution_id;
    if(!row.code_show) return;
    $.get(
        show_code_url,
        {
            'solution_id': solution_id,
            'cid': status_page_information.attr('cid')
        },
        function(ret){
            if(ret['code'] == 1) {
                let data = ret['data'];
                let showcode_pre = $('<pre class="' + $(td).text().toLowerCase() + '" id="content_show_to_copy"><code>' + data['source'] + data['auth'] + '</code></pre>')[0];
                let showcode_div = $('<div class="code_linenumber_div"></div>').append(showcode_pre);
                AddLineNumber(showcode_div);
                hljs.highlightElement(showcode_div.find('.ln_code_pre')[0]);

                content_show_modal_content.empty();
                content_show_modal_content.append(showcode_div);
                content_show_modal_label_span.text('Code of Submission #' + solution_id);
                content_show_modal.modal('show');
            }
            else {
                return false;
            }
        }
    );
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
            let info_hidden = '';
            let info_addition = '<div></div>';
            if(ret.code == 1) {
                if(row.result == 6 || row.result == 5 || row.result == 9) {
                    // WA diff
                    info_hidden = 'style="display:none;';
                    let str = ret.msg;
                    // 先将 diff 部分替换为 HTML 格式
                    str = str.replace(/(------diff out top.*?-----)([\s\S]*?)(------diff|=====)/g, (match, p1, p2, p3) => {
                        try {
                            let htmlStr = Diff2Html.html(p2, {drawFileList: false, matching: 'lines', outputFormat: 'side-by-side'});
                            let diff_str = '';
                            if(htmlStr.includes('<span class="d2h-code-line-ctn">')) {
                                diff_str = htmlStr;
                            } else {
                                diff_str = `<pre class="content_show_modal_realinfo">${p2}</pre>`;
                            }
                            return `</pre><h4>${p1.replace(/-/g, '')}</h4><strong class='text-danger'>输出比对仅展示前若干字节数据，数据过大时可能看不到差异部分，建议下载测试数据在本地进行测试</strong>${diff_str}<pre class="content_show_modal_realinfo">${p3}`;
                        } catch (error) {
                            return match;
                        }                        
                    });
                    str = str.replace(/========\[(.*?)\]=========/g, (match, p1) => {
                        return `</pre><h3># Data: ${p1.replace('.out', '')}</h3><pre class="content_show_modal_realinfo">`
                    });
                    str = str.replace(/------(test in top.*?)------/g, (match, p1) => {
                        return `</pre><h4>${p1}</h4><pre class="content_show_modal_realinfo">`
                    });

                    // info_addition = `<strong class='text-danger'>输出比对仅展示前若干字节数据，数据过大时可能看不到差异部分，建议下载测试数据在本地进行测试</strong><pre class="content_show_modal_realinfo">${str}</pre>`;
                    info_addition = `<pre class="content_show_modal_realinfo">${str}</pre>`;
                    info_addition = info_addition.replace(/<pre class="content_show_modal_realinfo">\s*?<\/pre>/g, '');
                }
                var info = $(`<pre class="content_show_modal_realinfo" id="content_show_to_copy" ${info_hidden}>${ret.msg}</pre>`)[0];
                // hljs.highlightElement(info);
                content_show_modal_content.empty();
                content_show_modal_content.append(info);
                content_show_modal_content.append(info_addition);
                content_show_modal_label_span.text('Runinfo of Submission #' + solution_id);
                content_show_modal.modal('show');
            }
            else {
                return false;
            }
            return false;
        }
    );
}
function SetStatusButton() {
    // show running information and code.
    $table.on('click-cell.bs.table', function(e, field, td, row){
        if(field == 'language') {
            BtnCodeShow(td, row)
        } else if(field == 'result') {
            BtnResultShow(td, row);
        } else if(field == 'rejudge' && row.allow_rejudge) {
            alertify.confirm(`确定要重测该提交 [提交号=<strong class='text-danger'>${row.solution_id}</strong>]?<br/>Confirm to rejudge solution [RunID=<strong class='text-danger'>${row.solution_id}</strong>]?`, function() {
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
                        alertify.success(`[RunID=${row.solution_id}]<br/>rejudge start.`);
                    } else {
                        alertify.err(`[RunID=${row.solution_id}]<br/>rejudge failed.<br/>${ret.msg}`);
                    }

                })
            }).set('title', '确认重测');
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