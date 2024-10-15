var param;
if(typeof param === 'undefined') {
    param = csg.Url2Json();
}
var cid;
if (typeof cid === 'undefined') {
    cid = param?.cid;
}
let cid_list = param?.cid_list;
var cdata = null;  // 比赛原始数据
let cnt_base = null;    // 过题队数量作为评奖基数，金银铜向上取整
let map_team_sol, map_team, map_p2num, map_num2p, map_fb; // data maps
let rank_gold, rank_silver, rank_bronze;
let real_rank_list = [], real_rank_map = {};
const STAR_NORANK = 0, STAR_WITHOUT = 1, STAR_RANK = 2;
let flag_star_mode = STAR_NORANK;
let summary_data;
function Str2Sec(time_str) {
    if(typeof(time_str) != 'string') {
        return time_str;
    }
    return Math.floor(new Date(time_str).getTime() / 1000 + 1e-6);
}
function SetFB() {
}
function SetAwardRank(re_calc=false) {
    // get real final rank
    function UpdateFb(mf, team_id, pro_id, in_date) {
        if(!(pro_id in mf) || in_date < mf[pro_id].in_date) {
            mf[pro_id] = {
                'in_date': in_date,
                'teams': {}
            }
        }
        if(in_date == mf[pro_id].in_date) {
            mf[pro_id].teams[team_id] = true;
        }
    }
    if(re_calc) {
        map_fb = {
            'global': {},
            'formal': {}
        };
        real_rank_list = [];
        for(let team_id in map_team_sol) {
            item = {
                'team_id': team_id,
                'sol': 0,
                'penalty': 0
            }
            let team_sol = map_team_sol[team_id];
            for(let i = 0; i < map_num2p.length; i ++) {
                let pro_id = map_num2p[i];
                if(pro_id in team_sol.ac) {
                    UpdateFb(map_fb.global, team_id, pro_id, team_sol[pro_id][team_sol[pro_id].length - 1].in_date);
                    if(map_team[team_id].tkind != 2) {
                        UpdateFb(map_fb.formal, team_id, pro_id, team_sol[pro_id][team_sol[pro_id].length - 1].in_date);
                    }
                    item.sol ++;
                    item.penalty += ProPenalty(team_id, pro_id, team_sol[pro_id].length - 1);
                } else if(pro_id in team_sol) {
                    for(let j = 0; j < team_sol[pro_id].length; j ++) {
                        if(team_sol[pro_id][j].result == 4) {
                            UpdateFb(map_fb.global, team_id, pro_id, team_sol[pro_id][j].in_date);
                            if(map_team[team_id].tkind != 2) {
                                UpdateFb(map_fb.formal, team_id, pro_id, team_sol[pro_id][j].in_date);
                            }
                            item.sol ++;
                            item.penalty += ProPenalty(team_id, pro_id, j);
                            break;
                        }
                    }
                }
            }
            real_rank_list.push(PostprocessDataItem(item));
        }
        real_rank_list.sort((a, b) => {
            if(a.sol == b.sol) {
                if(a.penalty == b.penalty) {
                    if(a.team_id == b.team_id) {
                        return 0;
                    } else {
                        return a.team_id < b.team_id ? -1 : 1;
                    }
                } else {
                    return a.penalty < b.penalty ? -1 : 1;
                }
            } else {
                return a.sol < b.sol ? 1 : -1;
            }
        });
    }
    let rk_real = 0, rk_show = 0, last_item = null, show_i = 0;
    cnt_base = 0;
    real_rank_map = {};
    for(let i = 0; i < real_rank_list.length; i ++) {
        let tm_rrkl = real_rank_list[i];
        let star_team = false;
        if(map_team[tm_rrkl.team_id].tkind == 2) {
            if(flag_star_mode == STAR_WITHOUT) {
                continue;
            } else if(flag_star_mode == STAR_NORANK) {
                star_team = true;
            }
        }
        if(!star_team) {
            rk_real ++;
        }
        if(last_item == null || tm_rrkl.sol < last_item.sol || tm_rrkl.penalty > last_item.penalty) {
            rk_show = rk_real;
        }
        if(!star_team) {
            last_item = tm_rrkl;
        }
        real_rank_map[tm_rrkl.team_id] = {
            rank: star_team ? '*' : rk_show,
            ith: show_i + 1
        }
        show_i ++;
        if(real_rank_list[i].sol) {
            cnt_base += star_team ? 0 : 1;
        }
    }
    SetFB();
    
    let award_rank = GetAwardRank(cnt_base, ratio_gold, ratio_silver, ratio_bronze);
    rank_gold = award_rank[0];
    rank_silver = award_rank[1];
    rank_bronze = award_rank[2];
}
function SummaryTemplate() {
    return {
        4: 0,
        5: 0,
        6: 0,
        7: 0,
        8: 0,
        9: 0,
        10: 0,
        'other': 0,
        'sum': 0 
    }
}
function PostprocessDataItem(team_line_item) {
    if('penalty' in team_line_item) {
        // 处理penalty的单位
        team_line_item.penalty_mi = Math.floor(team_line_item.penalty / 60 + 0.00000001);
        team_line_item.penalty_sec = team_line_item.penalty;
        team_line_item.penalty = team_line_item.penalty_mi;
    }
    return team_line_item;
}
function StrEmpty(st) {
    return st === null || st.trim() == '';
}
function ContestUserId(user_id) {
    if(user_id.startsWith('#')) {
        return user_id.replace(/#cpc\d+?_/g, '');
    }
    return user_id;
}

function SolTime(in_date, mi=true, format_int=true) {
    let ret = in_date - time_start;
    if(mi) {
        ret = Math.floor(ret / 60 + 0.00000001);
    }
    if(!format_int) {
        if(mi) {
            ret *= 60;
        }
        ret =Timeint2Str(ret);
    }
    return ret;
}
function ProPenalty(team_id, pro_id, ith_ac) {
    return ith_ac * 20 * 60 + map_team_sol[team_id][pro_id][ith_ac].in_date - time_start;
}
function ProcessData() {
    cnt_base = null;
    map_team_sol = {};
    map_team = {};
    stack_judge = [];
    summary_data = {
        'total': SummaryTemplate(),
        'pro_list': []
    };
    for(let i = 0; i < cdata.problem.length; i ++) {
        summary_data.pro_list.push(SummaryTemplate());
    }
    time_start = Str2Sec(cdata.contest.start_time);
    time_end = Str2Sec(cdata.contest.end_time);
    time_frozen = time_end - parseInt(cdata.contest.frozen_minute) * 60;
    time_frozen_end = time_end + parseInt(cdata.contest.frozen_after) * 60;
    
    ratio_gold = cdata.contest.award_ratio % 1000;
    ratio_silver = Math.floor(cdata.contest.award_ratio / 1000) % 1000;
    ratio_bronze = Math.floor(cdata.contest.award_ratio / 1000000);
    if(ratio_gold < 100) {
        ratio_gold = ratio_gold / 100.0 - 0.0000001;
    }
    if(ratio_silver < 100) {
        ratio_silver = ratio_silver / 100.0;
    }
    if(ratio_bronze < 100) {
        ratio_bronze = ratio_bronze / 100.0;
    }
    for(let i = 0; i < cdata.team.length; i ++) {
        map_team[cdata.team[i].team_id] = cdata.team[i];
    }
    // process problem id map
    map_p2num = {};
    map_num2p = new Array(cdata.problem.length);
    for(let i = 0; i < cdata.problem.length; i ++) {
        map_p2num[cdata.problem[i].problem_id] = cdata.problem[i].num;
        map_num2p[cdata.problem[i].num] = cdata.problem[i].problem_id;
    }
    // process solutions
    cdata.solution.sort((a, b) => a.solution_id == b.solution_id ? 0 : (a.solution_id < b.solution_id ? -1 : 1));
    let solution_new = [];
    let map_solution_aced = {};
    for(let i = 0; i < cdata.solution.length; i ++) {
        cdata.solution[i].user_id = ContestUserId(cdata.solution[i].user_id);
        cdata.solution[i].in_date = Str2Sec(cdata.solution[i].in_date);
        if(cdata.solution[i].in_date < time_start || 
            cdata.solution[i].in_date > time_end || 
            // cdata.solution[i].result < 4 || 
            cdata.solution[i].result == 11 ||
            !(cdata.solution[i].user_id in map_team)
        ) {
            continue;
        }
        // 如果特定选手特定题目ac过，之后的提交忽略
        if(!(cdata.solution[i].user_id in map_solution_aced)) {
            map_solution_aced[cdata.solution[i].user_id] = {};
        }
        if(cdata.solution[i].problem_id in map_solution_aced[cdata.solution[i].user_id]) {
            continue;
        }
        if(cdata.solution[i].result == 4) {
            map_solution_aced[cdata.solution[i].user_id][cdata.solution[i].problem_id] = true;
        }
        solution_new.push(cdata.solution[i]);
        // process summary
        let p_numid = map_p2num[cdata.solution[i].problem_id];
        if(cdata.solution[i].result in summary_data.pro_list[p_numid]) {
            summary_data.pro_list[p_numid][cdata.solution[i].result] ++;
            summary_data.pro_list[p_numid].sum ++;
            summary_data.total[cdata.solution[i].result] ++;
            summary_data.total.sum ++;
        } else {
            summary_data.total.other ++;
            summary_data.pro_list[p_numid].other ++;
        }
        if(!(cdata.solution[i].user_id in map_team_sol)) {
            map_team_sol[cdata.solution[i].user_id] = {};
            map_team_sol[cdata.solution[i].user_id].ac = {};
            map_team_sol[cdata.solution[i].user_id].frozen = {};
        }
        let user_sol = map_team_sol[cdata.solution[i].user_id];
        if(!(cdata.solution[i].problem_id in user_sol)) {
            user_sol[cdata.solution[i].problem_id] = [];
        }
        let user_sol_pro = user_sol[cdata.solution[i].problem_id];
        if(cdata.solution[i].problem_id in user_sol.ac) {
            continue;   // 已ac之后的提交忽略
        }
        if(PAGE_TYPE == 'roll' && cdata.solution[i].in_date <= time_frozen || PAGE_TYPE == 'rank' && cdata.solution[i].result >= 4) {
            if(cdata.solution[i].result == 4) {
                user_sol.ac[cdata.solution[i].problem_id] = cdata.solution[i].in_date;
            }
        } else {
            user_sol.frozen[cdata.solution[i].problem_id] = true;
        }
        user_sol_pro.push(cdata.solution[i]);
    }
    cdata.solution = solution_new;
}