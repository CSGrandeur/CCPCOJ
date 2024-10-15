let flag_rolling = false;
let map_fb_now; // data maps

let award_modal_div = $('#award_modal_div');
let award_modal_obj = new bootstrap.Modal(document.getElementById('award_modal_div'));
// AwardModalToggle(true);

const URL_PRE_TEAM_PHOTO = `${FLG_PKG_MODE ? "./resource" : ""}/upload/contest_attach/${contest_attach}/team_photo`;

function FontNarrowAward() {
    let award_info_list = document.getElementsByClassName('award_info_content');
    for (let i = 0; i < award_info_list.length; i++) {
        FontNarrow(award_info_list[i]);
    }
}

function TeamPhotoUri(team_id) {
    return `${URL_PRE_TEAM_PHOTO}/${team_id}.jpg`;
}

function TeamPhotoUriOnError(img_obj) {
    let ic;
    let award = img_obj.getAttribute('award');
    if (award == 'gold') {
        ic = "🥇";
    } else if (award == 'silver') {
        ic = "🥈"
    } else {
        ic = "🥉"
    }
    if (img_obj.getAttribute('award'))
        img_obj.src = `data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg'%3E%3Ctext x='50%25' y='50%25' font-size='32rem' fill='%23a2a9b6' font-family='等线,system-ui, sans-serif' text-anchor='middle' dominant-baseline='middle'%3E${ic}%3C/text%3E%3C/svg%3E`
}

function DomJudgeConfirm(team_id, pro_id, undo = false, pos_before = null) {
    AwardModalToggle(false);
    $('.g_pro_judging').removeClass('g_pro_judging');
    $('.g_team_judging').removeClass('g_team_judging');
    map_item[team_id].dom.addClass('g_team_judging');
    if (pro_id !== null) {
        map_item[team_id].dom.find(`.g_pro[problem_id="${pro_id}"]`).addClass('g_pro_judging');
    }
    if (undo || stack_judge.length == 0 || stack_judge[stack_judge.length - 1].team_id != team_id) {
        var elementPosition = pos_before === null ? map_item[team_id].gitem._top : pos_before;
        var offsetPosition = elementPosition - $(window).height() + 300;
        rankroll_div.animate({
            scrollTop: offsetPosition
        }, auto_speed);
    }
}

function JudgeConfirm() {
    flag_nowstep = 'confirm';
    let team_sol;

    AwardModalToggle(false);
    for (; now_judging_ith >= 0; now_judging_ith--) {
        if (!(now_judging_ith in now_order) || !(now_order[now_judging_ith] in map_team_sol)) {
            continue;
        }
        team_sol = map_team_sol[now_order[now_judging_ith]];
        let frozen_flag = false;
        for (let i = 0; i < map_num2p.length; i++) {
            if (map_num2p[i] in team_sol.frozen) {
                frozen_flag = true;
                judging_pro_id = map_num2p[i];
                break;
            }
        }
        judging_team_id = now_order[now_judging_ith];
        if (!frozen_flag) {
            if (judging_team_id_last === judging_team_id) {
                // 无frozen题目情况下是否已查看过该team
                continue;
            }
            judging_pro_id = null;
        }
        judging_team_id_last = judging_team_id;
        DomJudgeConfirm(judging_team_id, judging_pro_id);
        if (flag_auto) {
            setTimeout(function() {
                JudgeDo();
            }, auto_speed);
        }
        return {
            'team_id': judging_team_id,
            'pro_id': judging_pro_id
        }
    }
    return null;
}

function UpdateSolPenalty(team_id, solved, penalty) {
    let team_item = map_item[team_id];
    team_item.dom.find('.item-content').attr('solved', solved).attr('penalty', penalty);
    team_item.dom.find('.g_solve').text(solved);
    team_item.dom.find('.g_time').text(penalty);
    team_item.gitem._sortData.solved = parseInt(solved);
    team_item.gitem._sortData.penalty = parseInt(penalty);
}

function JudgeDo() {
    flag_nowstep = 'do';
    flag_judgingdo_step = true;
    if (now_judging_ith < 0) {
        return;
    }
    let change_solved = 0;
    let change_penalty = 0;
    if (judging_pro_id !== null) {
        let team_sol = map_team_sol[judging_team_id];
        let submit_num = 0;
        let last_submit_time = 0;
        let ac = false;
        for (let i = 0; i < team_sol[judging_pro_id].length; i++) {
            submit_num = i + 1;
            last_submit_time = team_sol[judging_pro_id][i].in_date;
            if (team_sol[judging_pro_id][i].result == 4) {
                ac = true;
                break;
            }
        }
        let team_item = map_item[judging_team_id];
        let pro_div = team_item.dom.find(`.g_pro[problem_id="${judging_pro_id}"]`);
        judging_ac_flag = false;
        if (ac) {
            judging_ac_flag = true;
            change_solved = 1;
            change_penalty = ProPenalty(judging_team_id, judging_pro_id, submit_num - 1);
            team_item.solved += change_solved;
            team_item.penalty_sec += change_penalty;
            team_item.penalty_mi = Math.floor(team_item.penalty_sec / 60 + 0.00000001);
            team_item.penalty = team_item.penalty_mi;
            team_sol.ac[judging_pro_id] = last_submit_time;
            pro_div.children()[0].innerText = submit_num;
            pro_div.children()[2].innerText = SolTime(last_submit_time);
        }
        delete team_sol.frozen[judging_pro_id];

        UpdateSolPenalty(judging_team_id, team_item.solved, team_item.penalty);

        pro_div.removeClass('g_pro_nn g_pro_pd g_pro_wa g_pro_ac').addClass(ac ? 'g_pro_ac' : 'g_pro_wa');
    }
    stack_judge.push({
        'team_id': judging_team_id,
        'pro_id': judging_pro_id,
        'change_solved': change_solved,
        'change_penalty': change_penalty,
        'pos_before': map_item[judging_team_id].gitem._top
    });
    if (judging_pro_id === null) {
        JudgeSort(true);
    } else if (flag_auto) {
        setTimeout(function() {
            JudgeSort(true);
        }, auto_speed);
    }
}
const AWARD_NAME = {
    'gold': '金奖',
    'silver': '银奖',
    'bronze': '铜奖'
};

function DisplayInfo(info_key, team_item, content = null) {
    if (content === null && (info_key in team_item) && !StrEmpty(team_item[info_key])) {
        $(`#award_${info_key}`).show().find(`.award_info_content`).text(team_item[info_key]);
    } else if (content !== null && content.toString().length > 0) {
        $(`#award_${info_key}`).show().find(`.award_info_content`).text(content);
    } else {
        $(`#award_${info_key}`).hide();
    }
}

function AwardModalSet(team_id, award) {
    function GetFbList(team_id) {
        let mf = flag_star_mode == STAR_RANK ? map_fb.global : map_fb.formal;
        ret = [];
        for (let i = 0; i < map_num2p.length; i++) {
            if ((map_num2p[i] in mf) && (team_id in mf[map_num2p[i]].teams)) {
                ret.push(String.fromCharCode('A'.charCodeAt(0) + i));
            }
        }
        return ret.join(', ');
    }
    $('#award_team_img').attr('award', award);
    $('#award_team_img').attr('src', TeamPhotoUri(team_id));
    $('#award_info').attr('award', award);
    $('#award_level').text(AWARD_NAME[award]).attr('award', award);
    $('#awrad_school_logo').show().find('img').attr('school', DomSantize(map_team[team_id].school)).attr('src', SchoolLogoUri(map_team[team_id].school));
    DisplayInfo('school', map_team[team_id]);
    DisplayInfo('name', map_team[team_id]);
    DisplayInfo('tmember', map_team[team_id]);
    DisplayInfo('coach', map_team[team_id]);
    DisplayInfo('rk', null, now_rank[team_id].rank);
    DisplayInfo('fb', null, GetFbList(team_id));
    DisplayInfo('solved', null, map_item[team_id].solved);
}

function AwardModalToggle(show = true) {
    award_modal_div.find('.modal-dialog').css('transition', `transform ${Math.min(auto_speed >> 1, DEFAULT_SPEED)}ms ease-out`);
    if (show) {
        award_modal_obj.show();
    } else {
        award_modal_obj.hide();
    }
}

function JudgeAward() {
    // 该队获奖
    flag_nowstep = 'award';
    if (now_judging_ith < 0) {
        return;
    }
    let award_modal_show_flag = false;
    if (real_rank_map[judging_team_id].rank != '*' && now_rank[judging_team_id].ith == real_rank_map[judging_team_id].ith && Object.keys(map_team_sol[judging_team_id].frozen).length == 0) {
        let award = null;
        if (real_rank_map[judging_team_id].rank <= rank_gold) {
            award = 'gold';
        } else if (real_rank_map[judging_team_id].rank <= rank_silver) {
            award = 'silver';
        } else if (real_rank_map[judging_team_id].rank <= rank_bronze) {
            award = 'bronze';
        }
        if (award != null) {
            AwardModalSet(judging_team_id, award);
            award_modal_show_flag = true;
            if (!flag_award_area) {
                flag_auto = false; // 进入奖区暂停auto
                auto_speed = DEFAULT_SPEED;
            }
            flag_award_area = true;
            AwardModalToggle(true);
        }
    }
    if (!award_modal_show_flag) {
        JudgeConfirm();
    } else if (flag_auto) {
        setTimeout(function() {
            JudgeConfirm();
        }, Math.max(auto_speed, 256));
    }
}

function JudgeUndo() {
    if (stack_judge.length == 0) {
        return;
    }
    flag_auto = false;
    let judge_record = stack_judge[stack_judge.length - 1];
    judging_team_id = judge_record.team_id;
    DomJudgeConfirm(judge_record.team_id, judge_record.pro_id, true, judge_record.pos_before);
    if (judge_record.pro_id !== null) {
        let team_sol = map_team_sol[judge_record.team_id];
        let team_item = map_item[judge_record.team_id];
        let pro_div = team_item.dom.find(`.g_pro[problem_id="${judge_record.pro_id}"]`);
        let change_solved = 0;
        let change_penalty = 0;

        team_item.solved -= judge_record.change_solved;
        team_item.penalty_sec -= judge_record.change_penalty;
        team_item.penalty_mi = Math.floor(team_item.penalty_sec / 60 + 0.00000001);
        team_item.penalty = team_item.penalty_mi;
        if (judge_record.pro_id in team_sol.ac) {
            delete team_sol.ac[judge_record.pro_id];
        }
        pro_div.children()[0].innerText = team_sol[judge_record.pro_id].length;
        pro_div.children()[2].innerText = SolTime(team_sol[judge_record.pro_id][team_sol[judge_record.pro_id].length - 1].in_date);
        team_sol.frozen[judge_record.pro_id] = true;

        UpdateSolPenalty(judge_record.team_id, team_item.solved, team_item.penalty)

        pro_div.removeClass('g_pro_nn g_pro_pd g_pro_wa g_pro_ac').addClass('g_pro_pd');
    }
    JudgeSort();
    // now_judging_ith = now_order.length;
    now_judging_ith = map_now_order[judge_record.team_id];
    judging_team_id_last = null;
    flag_nowstep = null;
    if (now_rank[judge_record.team_id].rank != '*' && now_rank[judge_record.team_id].rank >= rank_bronze) {
        flag_award_area = false;
    }
    stack_judge.pop();
}

function JudgeNextStep() {
    if (flag_nowstep == 'sort' || flag_nowstep == null) {
        JudgeConfirm();
    } else if (flag_nowstep == 'confirm') {
        JudgeDo();
    } else if (flag_nowstep == 'do') {
        JudgeSort(true);
    } else if (flag_nowstep == 'sort_award') {
        JudgeAward();
    } else if (flag_nowstep == 'award') {
        JudgeConfirm();
    }
}

function JudgeSort(judging_flag = false) {
    flag_nowstep = judging_flag ? 'sort' : null;
    let team_ith_before, team_ith_after;
    if (judging_flag) {
        team_ith_before = now_rank[judging_team_id].ith;
    }
    $('.g_team_judging_last').removeClass('g_team_judging_last');
    $('.g_team_judging').addClass('g_team_judging_last');
    $('.g_team_judging').removeClass('g_team_judging');
    grid.sort('solved:desc penalty team_id');
    if (judging_flag) {
        team_ith_after = now_rank[judging_team_id].ith;
        if (team_ith_before == team_ith_after && Object.keys(map_team_sol[judging_team_id].frozen).length == 0) {
            // 该team题目已经判完且无名次变动
            DomJudgeConfirm(judging_team_id, null);
            flag_nowstep = 'sort_award';
            JudgeAward();
        } else {
            JudgeConfirm();
        }
    }
    flag_judgingdo_step = false;
}
award_modal_div.on('shown.bs.modal', function() {
    // 需在modal显示完毕后方可获取宽度
    FontNarrowAward();
});
$('.button_init_data').click(function() {
    alertify.confirm("确认", "确认初始化数据？", function() {
        InitData(true);
        $('.button_fullscreen').removeAttr('disabled');
    }, function() {});
});
$('.button_fullscreen').click(function() {
    ToggleFullScreen('rankroll_div')
});
document.addEventListener("fullscreenchange", function() {
    if (!document.fullscreenElement) {
        rank_header_div.css({
            'position': 'relative'
        });
        flag_rolling = false;
        flag_auto = false;
        AwardModalToggle(false);
        JudgeUndo(); // 退出全屏时Undo消除重新进入的不确定性
    } else {
        rank_header_div.css({
            'position': 'fixed'
        });
        flag_rolling = true;
        if (stack_judge.length == 0) {
            rankroll_div.animate({
                scrollTop: rankroll_div.offset().top + rankroll_div[0].scrollHeight
            }, Math.min(now_order.length * 100, 5000));
        } else {
            JudgeConfirm();
        }
    }
});

$('.button_help').click(function() {
    alertify.alert("帮助", `首先点击“初始化”预处理，然后点击“启动”进入全屏模式<br/>
W：加速<br/>
S：减速<br/>
R：恢复默认速度<br/>
A：自动，再次按下取消自动（进入奖区时会暂停自动）<br/>
N：手动前进一步<br/>
U：撤回一步<br/>
屏蔽了F5页面刷新以免误操作，可CTRL+R刷新页面`);
});
$('#with_star_team').change(function() {
    flag_star_mode = parseInt(this.value);
    SetAwardRank(false);
    GridFilter();
    flag_award_area = false
});

function KeyTimeout() {
    flag_keyvalid = false;
    setTimeout(function() {
        flag_keyvalid = true;
    }, auto_speed + 10);
}

function RollForward() {
    if (flag_keyvalid) {
        flag_auto = false;
        JudgeNextStep();
        KeyTimeout();
    }
}

function RollBackward() {
    if (flag_keyvalid) {
        flag_auto = false;
        JudgeUndo();
        KeyTimeout();
    }
}
window.onkeydown = (event) => {
    if (!event || !event.isTrusted || !event.cancelable) {
        return;
    }
    const key = event.key;
    if (key === 'F5') {
        event.preventDefault();
        event.returnValue = false;
    }
    if (flag_rolling) {
        if (key === 'a' || key === 'A') {
            flag_auto = !flag_auto;
            if (flag_auto) {
                JudgeNextStep();
            }
        } else if (key === 'w' || key === 'W') {
            if (auto_speed > 32) {
                auto_speed >>= 1;
            }
        } else if (key === 's' || key === 'S') {
            if (auto_speed < 4096) {
                auto_speed <<= 1;
            }
        } else if (key === 'u' || key === 'U') {
            RollBackward();
        } else if (key === 'n' || key === 'N') {
            RollForward();
        } else if (key === 'r' || key === 'R') {
            // flag_auto = false;
            auto_speed = DEFAULT_SPEED;
        } else if (key == 'PageUp') {
            event.preventDefault();
            RollBackward();
        } else if (key == 'PageDown') {
            event.preventDefault();
            RollForward();
        }
    }
}
$(document).ready(function() {

    rankroll_div = $("#rankroll_div");
    rankroll_div.dblclick(function() {
        RollForward();
    })
    $('#team_photo_btn').click(function() {
        window.open($(this).attr('href'), '_blank', 'noopener,noreferrer');
    })
});