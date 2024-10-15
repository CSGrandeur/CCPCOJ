if('oj_mode' in param) {
    OJ_MODE = param.oj_mode;
}
var PAGE_TYPE;
if(typeof(PAGE_TYPE) == 'undefined') {
    PAGE_TYPE = 'rank';
}
 
let QUERY_MODULE = OJ_MODE == 'online' ? 'csgoj' : 'cpcsys';
const DEFAULT_SPEED = 256;
let flag_auto = false, auto_speed = DEFAULT_SPEED;
let flag_award_area = false;    // 标识是否进入奖区，进入奖区时取消自动模式一次
let flag_nowstep = null;
let flag_judgingdo_step = false;  // 当前操作为judge，而非初始sort或undo sort
let flag_keyvalid = true;
let time_start, time_end, time_frozen, time_frozen_end;
let tmp_rank_list = [], tmp_rank_map = {};
let ratio_gold, ratio_silver, ratio_bronze;
let stack_judge = [];
let judging_team_id, judging_pro_id, judging_team_id_last, judging_ac_flag;    // single judging now

let rank_header_div;
let loading_div;
let grid = null;
let rankroll_div;
let rank_grid_div;
let now_judging_ith = -1, now_order = [], map_now_order = {}, now_rank = {};
let map_item = {}

function FontNarrow(target_dom, target_width=null) {
    target_dom.style.transform = "none";
    let width_txt = target_dom.scrollWidth;
    let width_div = target_width === null ? target_dom.clientWidth : target_width;
    if(width_txt > width_div) {
        let ratio = width_div / width_txt;
        target_dom.style['transform-origin'] = `left`;
        target_dom.style.transform = `scaleX(${ratio})`;
    }
}

function FontNarrowRank() {
    let g_name_list = document.getElementsByClassName('g_name');
    for(let i = 0; i < g_name_list.length; i ++) {
        FontNarrow(g_name_list[i]);
    }
}
const RES_CODE = {
    4:  '<div style="height:60px;padding:5px;margin:2px;" class="alert alert-success">  <strong>题目通过<br/>（A C）</strong></div>',
    5:  '<div style="height:60px;padding:5px;margin:2px;" class="alert alert-danger">   <strong>格式错误<br/>（P E）</strong></div>',
    6:  '<div style="height:60px;padding:5px;margin:2px;" class="alert alert-danger">   <strong>结果错误<br/>（W A）</strong></div>',
    7:  '<div style="height:60px;padding:5px;margin:2px;" class="alert alert-warning">  <strong>时间超限<br/>（TLE）</strong></div>',
    8:  '<div style="height:60px;padding:5px;margin:2px;" class="alert alert-warning">  <strong>内存超限<br/>（MLE）</strong></div>',
    9:  '<div style="height:60px;padding:5px;margin:2px;" class="alert alert-warning">  <strong>输出过多<br/>（OLE）</strong></div>',
    10: '<div style="height:60px;padding:5px;margin:2px;" class="alert alert-warning">  <strong>运行错误<br/>（R E）</strong></div>',
    11: '<div style="height:60px;padding:5px;margin:2px;" class="alert alert-info">     <strong>编译错误<br/>（C E）</strong></div>',
    0:  '<div style="height:60px;padding:5px;margin:2px;" class="alert alert-default "> <strong>等待评测<br/>（P D）</strong></div>',
    1:  '<div style="height:60px;padding:5px;margin:2px;" class="alert alert-default "> <strong>等待重测<br/>（P R）</strong></div>',
    2:  '<div style="height:60px;padding:5px;margin:2px;" class="alert alert-default">  <strong>正在编译<br/>（C I）</strong></div>',
    3:  '<div style="height:60px;padding:5px;margin:2px;" class="alert alert-info">     <strong>正在运行<br/>（R J）</strong></div>',
}
function LoadData() {
    if(typeof(FLG_PKG_MODE) == 'undefined' || FLG_PKG_MODE == false) {
        if(typeof param === 'undefined') {
            var param = csg.Url2Json();
        }
        let requests = [
            param?.cid ? csg.get(`/${QUERY_MODULE}/contest/contest_data_ajax?cid=${param.cid}`) : 
            csg.get(`/${QUERY_MODULE}/contest/contest_data_joint_ajax?cid_list=${param?.cid_list}`)
        ];
        Promise.all(requests)
        .then(responses => Promise.all(responses.map(r => r.json())))
        .then(data => {
            let ret = data[0];
            if(ret.code == 1) {
                cdata = ret.data;
                ProcessData();
                $('#page_header_main').text(cdata.contest.title);
                if(typeof(SummaryUpdate) == 'function') {
                    SummaryUpdate();
                }
                ProcessItem();
            } else {
                alertify.error(ret.msg);
            }
            loading_div.hide();
        })
        .catch(error => {
            console.error('Error:', error); 
            loading_div.hide();
        });
    } else {
        ProcessData();
        $('#page_header_main').text(cdata.contest.title);
        if(typeof(SummaryUpdate) == 'function') {
            SummaryUpdate();
        }
        ProcessItem();
        loading_div.hide();
    }
}
function StopAutoRefresh(flag_msg=true) {
    if(interval_id != null) {
        clearInterval(interval_id);
        interval_id = null;
    }
    if(flag_msg) {
        alertify.message("关闭自动更新");
    }
}
function TryStopInterval() {
    if(typeof(interval_id) != 'undefined' && interval_id != null && TimeLocal() > cdata.contest.end_time) {
        clearInterval(interval_id);
        interval_id = null;
    }
}
function InitData(re_query=false) {
    // loading_div.show();
    try {
        if(re_query) {
            LoadData();
        } else {
            ProcessData();
            ProcessItem();
        }
    } catch (e) {
        console.error(e);
        loading_div.hide();
    }
}
function TeamItem(team_id, with_dom=true) {
    if(!(team_id in map_team)) {
        return null;
    }
    let pro_res = TeamItemRes(team_id);

    let dom_rank = `<div class="g_td g_rank"></div>`;
    let dom_logo = `<div class="g_td g_logo"><img class="school_logo" school="${map_team[team_id].school}" onerror="SchoolLogoUriOnError(this)"/></div>`;
    let dom_team_content = `<div class="g_team_content">
            <div class="g_name" title="${map_team[team_id].tmember}&#10;${map_team[team_id].coach}&#10;${map_team[team_id].tkind == 0 ? '常规队' : (map_team[team_id].tkind == 1 ? '女队' : '打星队')}">
            ${TkindIcon(map_team[team_id].tkind)}
            <strong class="page_str">
            ${DomSantize(map_team[team_id].school)}
            ${!StrEmpty(map_team[team_id].school) && !StrEmpty(map_team[team_id].name) ? '：' : '' }
            ${DomSantize(map_team[team_id].name)}
            </strong>
            </div>
            <div class="g_pro_group">
                ${pro_res.pro_list_dom}
            </div>
        </div>`;
    let dom_solve = `<div class="g_td g_solve">${pro_res.solved}</div>`;
    let dom_time = `<div class="g_td g_time" title="${Timeint2Str(pro_res.penalty_sec)}">${pro_res.penalty}</div>`;
    let team_line_dom;
    if(PAGE_TYPE == 'rank') {
        team_line_dom = `${dom_rank}${dom_logo}${dom_solve}${dom_time}${dom_team_content}`;
    } else {
        team_line_dom = `${dom_rank}${dom_logo}${dom_team_content}${dom_solve}${dom_time}`;
    }
    return {
        'solved': pro_res.solved,
        'penalty': pro_res.penalty,
        'penalty_sec': pro_res.penalty_sec,
        'penalty_mi': pro_res.penalty_mi,
        'dom': with_dom ? `<div class="item" id="g_${team_id}" team_id="${team_id}" tkind="${map_team[team_id].tkind}" >
            <div class="item-content" solved="${pro_res.solved}" penalty="${pro_res.penalty}" team_id="${team_id}">
                ${team_line_dom}
            </div>
        </div>` : ''
    }
}
function InitGrid() {
    if(grid != null) {
        return;
        grid.destroy(true);
    }
    grid = new Muuri('.grid', {
        dragEnabled: false,
        layoutOnInit: true,
        horizontal: false,
        dragAxis: 'y',
            
        layoutDuration: Math.min(2000, Object.keys(map_team).length * 10),
        layoutEasing: 'cubic-bezier(1, 0, 1, 1)',
        // layoutEasing: 'ease-in',
        sortData: {
            solved: function (item, element) {
                return parseInt(element.children[0].getAttribute('solved'));
            },
            penalty: function (item, element) {
                return parseInt(element.children[0].getAttribute('penalty'));
            },
            team_id: function (item, element) {
                return element.children[0].getAttribute('team_id');
            },
        }
    });
    grid.on('sort', function (currentOrder, previousOrder) {
        let pre_ith, after_ith;
        if(flag_judgingdo_step) {
            pre_ith = now_rank[judging_team_id].ith;
        }
        GridResetRank(currentOrder);
        if(flag_judgingdo_step) {
            after_ith = now_rank[judging_team_id].ith;
            let layout_dur = Math.min(Math.max((Math.abs(after_ith - pre_ith) / 10 + 1) * auto_speed << 1, DEFAULT_SPEED), 3000);
            grid._settings.layoutDuration = layout_dur;
        } else {
            grid._settings.layoutDuration = DEFAULT_SPEED;
        }
    });
    grid.on('filter', function (shownItems, hiddenItems) {
        JudgeSort(false);
    });
}
let interval_id = null;
function StartAutoRefresh(flag_msg=true) {
    interval_id = setInterval(() => {
        InitData(true);
    }, 60000);
    if(flag_msg=true) {
        alertify.success("开启自动更新");
    }
}
let flag_auto_scroll=false;
let auto_scroll_task_id = [];
function AutoScroll(duration, delay, first=true) {
    if(!flag_auto_scroll) {
        return;
    }
    if(first) {
        window.scrollTo(0, 0);
        auto_scroll_task_id.push(setTimeout(() => AutoScroll(duration, delay, false), 0));
    } else {
        // const perTick = document.body.scrollHeight / duration * 50;
        const perTick = 500;
        auto_scroll_task_id.push(setTimeout(function() {
            window.scrollBy(0, perTick);
            if(flag_auto_scroll) {
                if (window.innerHeight + window.scrollY < document.body.scrollHeight) {
                    AutoScroll(duration, delay, false);
                } else {
                    auto_scroll_task_id.push(setTimeout(function() {
                        AutoScroll(duration, delay, true);
                    }, 5000));
                }
            }
        }, delay));
    }
}

$(document).ready(function() {
    rank_header_div = $('#rank_header_div');
    loading_div = $('#loading_div');
    rankroll_div = $("#rankroll_div");
    rank_grid_div = $('#rank_grid_div');
    $('#alink_school').attr('href', `schoolrank?${cid ? 'cid' : 'cid_list'}=${cid ? cid : cid_list}`);
    $('#alink_team').attr('href', `rank?${cid ? 'cid' : 'cid_list'}=${cid ? cid : cid_list}`);
    loading_div.show();
    InitData(true);
    $('#with_star_team').change(function() {
        flag_star_mode = parseInt(this.value);
        if(typeof(SetAwardRank) == 'function') {
            SetAwardRank(false);
        }
        GridFilter();
        flag_award_area = false
    });
    let sticky = rank_header_div.offset().top;
    window.onscroll = function() {
        if (window.scrollY > sticky) {
            rank_header_div.addClass("sticky");
        } else {
            rank_header_div.removeClass("sticky");
        }
    };
});

window.onkeydown = (event) => {
    if (!event || !event.isTrusted || !event.cancelable) {
        return;
    }
    const key = event.key;
    if (key === 'F5') {
        event.preventDefault();
        event.returnValue = false;
        InitData(true);
    }
}

$(document).on('click', function (e) {
    if(e.detail == 3){
        if(interval_id === null) {
            StartAutoRefresh();
        } else {
            StopAutoRefresh();
        }
    }
});


let flag_forbid_f5 = false;

window.onkeydown = (e) => {
    if(!FLG_PKG_MODE && PAGE_TYPE != 'roll') {
        if (!e || !e.isTrusted || !e.cancelable) {
            return;
        }
        if(e.key == 'A' || e.key == 'a') {
            if(interval_id === null) {
                StartAutoRefresh();
            } else {
                StopAutoRefresh();
            }
        } else if(e.key == 'B' || e.key == 'b') {
            flag_auto_scroll = !flag_auto_scroll;
            if(flag_auto_scroll) {
                alertify.success("开启自动滚动");
                AutoScroll(1000, 10000, true);
            } else {
                for(let i = 0; i < auto_scroll_task_id.length; i ++) {
                    try {
                        clearTimeout(auto_scroll_task_id[i]);
                    } catch {}
                }
                auto_scroll_task_id = [];
                alertify.message("关闭自动滚动");
            }
    
        } else if(e.keyCode == 116 && !e.ctrlKey) {
            e.preventDefault();
            if(flag_forbid_f5) {
                alertify.warning("不需要太频繁刷新呦~")
            } else {
                flag_forbid_f5 = true;
                setTimeout(()=>{flag_forbid_f5=false;}, 5000);
                alertify.success("更新数据...")
                StopAutoRefresh(false);
                InitData(true);
            }
        }
    }
}

