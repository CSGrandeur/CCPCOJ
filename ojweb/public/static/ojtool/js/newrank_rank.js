let summary_modal_div;
let summary_modal_obj;
let summary_div;
let summary_button;
function GridFilter() {
    if(grid != null) {
        grid.filter(function(item) {
            return flag_star_mode != STAR_WITHOUT || parseInt(item.getElement().getAttribute('tkind')) != 2;
        });
        now_judging_ith = Object.keys(map_item).length;
        judging_team_id_last = null;
    }
}
function GridResetRank(currentOrder) {
    function RankDivClass(rk_show) {
        if(rk_show === '*') {
            return '';
        } else if(rk_show <= rank_gold) {
            return 'gold';
        } else if(rk_show <= rank_silver) {
            return 'silver';
        } else if(rk_show <= rank_bronze) {
            return 'bronze';
        }
    }
    $('.g_rank').removeAttr('award');
    now_order = [];
    map_now_order = {};
    now_rank = {}; 
    let rk_real = 0, rk_show, last_item = null, show_i = 0;
    for(let i = 0; i < currentOrder.length; i ++) {
        let team_id = currentOrder[i]._element.getAttribute('team_id');
        let team_info = map_team[team_id];
        let star_team = false;
        if(team_info.tkind == 2) {
            if(flag_star_mode == STAR_WITHOUT) {
                continue;
            } else if(flag_star_mode == STAR_NORANK) {
                star_team = true;
            }
        }
        now_order.push(team_id);
        map_now_order[team_id] = now_order.length - 1;
        if(!star_team) {
            rk_real ++;
        }
        if(last_item == null || last_item.solved > map_item[team_id].solved || last_item.penalty < map_item[team_id].penalty) {
            rk_show = rk_real;
        }
        if(!star_team) {
            last_item = map_item[team_id];
        }
        let now_rank_show = star_team ? '*' : rk_show;
        now_rank[team_id] = {
            rank: now_rank_show,
            ith: show_i + 1
        }
        map_item[team_id].dom.find('.g_rank').text(now_rank_show).attr('award', RankDivClass(now_rank_show));
        map_item[team_id].dom.attr('linetype', show_i & 1 ? 'odd' : 'even');
        show_i ++;
    }
}
function TeamItemRes(team_id) {
    let solved = 0;
    let penalty = 0;
    let sol = map_team_sol[team_id];
    let pro_list_dom = ``;
    for(let i = 0; i < map_num2p.length; i ++) {
        let pro_status = '';
        let submit_num = map_num2p[i] in sol ? sol[map_num2p[i]].length : '';
        let last_submit_mi = map_num2p[i] in sol ? SolTime(sol[map_num2p[i]][sol[map_num2p[i]].length - 1].in_date) : '';
        let last_submit_sec = map_num2p[i] in sol ? SolTime(sol[map_num2p[i]][sol[map_num2p[i]].length - 1].in_date, false, false) : '';
        let pro_idx = String.fromCharCode('A'.charCodeAt(0) + i);
        if(map_num2p[i] in sol.ac) {
            solved ++;
            penalty += ProPenalty(team_id, map_num2p[i], sol[map_num2p[i]].length - 1);
            pro_status = 'g_pro_ac';
        } else if(map_num2p[i] in sol.frozen) {
            pro_status = 'g_pro_pd';
        } else if(map_num2p[i] in sol) {
            pro_status = 'g_pro_wa';
        } else {
            pro_status = 'g_pro_nn';
        }
        pro_list_dom += `
        <div class="g_pro ${pro_status}" problem_id="${map_num2p[i]}">
            <span class="g_pro_lspan">${submit_num}</span>
            <span class="g_pro_mspan">${pro_idx}</span>
            <span class="g_pro_rspan" title="${last_submit_sec}">${last_submit_mi}</span>
        </div>
        `
    }
    return PostprocessDataItem({
        'solved': solved,
        'penalty': penalty,
        'pro_list_dom': pro_list_dom
    });
}
function ProcessItem() {
    InitGrid();
    now_judging_ith = -1;
    judging_team_id_last = null;
    for(let team_id in map_team_sol) {
        let team_item = TeamItem(team_id, true);
        if(team_item != null) {
            team_item.dom = $(team_item.dom);
            if(team_id in map_item) {
                for(let key in team_item) {
                    if(key != 'dom') {
                        map_item[team_id][key] = team_item[key]
                    } 
                }
                map_item[team_id].dom.html(team_item.dom.html());
                map_item[team_id].gitem._sortData.solved = map_item[team_id].solved;
                map_item[team_id].gitem._sortData.penalty = map_item[team_id].penalty;
            } else {
                map_item[team_id] = team_item;
                grid.add(team_item.dom[0]);
            }
            now_judging_ith ++;
        }
    }
    let gitem_list = grid.getItems();
    for(let i = 0; i < gitem_list.length; i ++) {
        map_item[gitem_list[i]._element.getAttribute('team_id')].gitem = gitem_list[i];
    }
    GridFilter();
    flag_award_area = false;
    FontNarrowRank();
    SetAwardRank(true);
    JudgeSort();
    if(Object.keys(map_team).length == 1) {
        // 只有一个队时不会触发sort事件
        GridResetRank([Object.values(map_item)[0].gitem]);
    }
    BatchProcessSchoolLogo();
    TryStopInterval();
}
function SetFB() {
    let mf = map_fb.formal;
    if(flag_star_mode === STAR_RANK) {
        mf = map_fb.global;
    }
    $('.g_pro_fb').removeClass('g_pro_fb');
    for(let pro_id in mf) {
        for(let team_id in mf[pro_id].teams) {
            map_item[team_id].dom.find(`.g_pro[problem_id="${pro_id}"]`).addClass('g_pro_fb');            
        }
    }
}
function SummaryUpdate() {
    if(typeof(summary_div) == 'undefined') {
        return;
    }
    let thead = `<tr><th style="width:90px;"></th>`;
    for(let i = 0; i < summary_data.pro_list.length; i ++) {
        thead += `<th style="width:65px;">${String.fromCharCode('A'.charCodeAt(0) + i)}</th>`
    }
    thead += `<th>合计</th></tr>`
    let tbody = ``;
    let lr = 0, lc;
    for(let i in summary_data.total) {
        if(i == 'sum') {
            continue;
        }
        tbody += `<tr linetype="${++ lr & 1 ? 'odd' : 'even'}"><td style="text-align:center;">${RES_CODE[i]}</td>`
        lc = 0;
        for(let j = 0; j < summary_data.pro_list.length; j ++) {
            tbody += `<td coltype="${lc ++ & 1 ? 'odd' : 'even'}">${summary_data.pro_list[j][i]}</td>`;
        }
        tbody += `<td coltype="${lc ++ & 1 ? 'odd' : 'even'}">${summary_data.total[i]}</td></tr>`;
    }
    tbody += `<tr linetype="${++ lr & 1 ? 'odd' : 'even'}"><td>合计</td>`
    for(let j = 0; j < summary_data.pro_list.length; j ++) {
        tbody += `<td>${summary_data.pro_list[j].sum}</td>`;
    }
    tbody += `<td>${summary_data.total.sum}</td></tr>`;
    let summary_table = `
        <table class="summary_table">
        <thead>${thead}</thead>
        <tbody>${tbody}</tbody>
        </table>
    `
    summary_div.html(summary_table);
    $('.modal-summary').css('max-width', summary_data.pro_list.length * 70 + 180);
}
function SummaryShow() {
    summary_modal_obj.show();
}
$(document).ready(function() {
    summary_modal_div = $('#summary_modal_div');
    if ($('#summary_modal_div').length > 0) {
        summary_modal_obj = new bootstrap.Modal(document.getElementById('summary_modal_div'));
        summary_div = $('#summary_div');
        summary_button = $('#summary_button');
        summary_button.click(function() {
            SummaryShow();
        });
    }
});
function JudgeSort() {
    grid.sort('solved:desc penalty team_id');
}
