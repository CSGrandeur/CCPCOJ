let data_room = null, data_team = null;
let tmp_room_str = '', tmp_team_str = '';
let seatdraw_table = $('#seatdraw_table');
let room_info_table = $('#room_info_table'), room_input = $('#room_input'), room_show_modal = new bootstrap.Modal('#room_show_modal');
let seatdraw_button = $('#seatdraw_button');
let team_input = $('#team_input'), team_show_modal = new bootstrap.Modal('#team_show_modal');
let seat_num_span = $('#seat_num_span');
let seatdraw_seed = $('#seatdraw_seed');
let button_export = $('.button_export');
let button_import_contest = $('.button_import_contest');
let seat_max = 0, team_id_num_len;
let flag_draw = false, seed_draw = 1024;
let rd;
const SEED_MOD = 65536;
function SetSeed(seed) {
    rd = new Math.seedrandom(seed);
}
function Rand() {
    return Math.abs(rd.int32()) % SEED_MOD;
}
const header_list = {
    "team_id": ["team_id", "name", "school", "tmember", "coach", "room", "tkind", "label"],
    "school": ["idx", "school", "name", "tmember", "coach", "room", "tkind", "label", "team_id"]
}
const header_list_cn = {
    "team_id": ["队号", "队名", "学校", "成员", "教练", "考场", "队伍类型", "标签"],
    "school": ["序号", "学校", "队名", "成员", "教练", "考场", "队伍类型", "标签", "队号"]
}
const team_keys = ['name', 'school', 'tmember', 'coach', 'tkind', 'label'];
function FormatterTkind(value, row, index) {
    let v = value === null ? 0 : value;
    let icon = "balloon-fill", title_tip = "常规队", txtcolor="text-success";
    if(v == 1) {
        icon = "balloon-heart-fill", title_tip = "女队", txtcolor="text-danger";
    } else if(v == 2) {
        icon = "star-fill", title_tip = "打星队", txtcolor="text-primary";
    }
    return `<i class="tkind_icon ${txtcolor} bi bi-${icon}" title="${title_tip}"></i>`;
}
function FormatterRoom(value, row, index) {
    return `<div id="room_div_${index}"></div>`;
}
function FormatterTeamId(value, row, index) {
    return `<div id="team_div_${index}"></div>`;
}
function LoadTeam2Table(team_list) {
    team_list.sort((a, b) => {
        try {
            // 按拼音排序
            return a.school.localeCompare(b.school) || a.name.localeCompare(b.name);
        } catch(e) {
            return 0;
        }
    });
    seatdraw_table.bootstrapTable('load', team_list);
}
function GetStorage() {
    let flag_storage = false;
    data_room = localStorage.getItem(`ojtool_seatdraw_data_room`);
    data_team = localStorage.getItem(`ojtool_seatdraw_data_team`);
    try {
        if(data_room != null) {
            data_room = JSON.parse(data_room);
            room_info_table.bootstrapTable('load', data_room);
            flag_storage = true;
            SetSeatTotal();
        }
        if(data_team != null) {
            data_team = JSON.parse(data_team);
            LoadTeam2Table(data_team);
            flag_storage = true;
        }
    } catch(e) {
        console.error(e);
        data_room = null;
        data_team = null;
        flag_storage = false;
    }
    if(flag_storage) {
        alertify.success("已读取缓存数据.");
    }
}
function SetStorage() {
    try {
        if(data_room != null) {
            localStorage.setItem(`ojtool_seatdraw_data_room`, JSON.stringify(data_room));
            SetSeatTotal();
            button_export.attr('disabled', true);
            button_import_contest.attr('disabled', true);
        }
        if(data_team != null) {
            localStorage.setItem(`ojtool_seatdraw_data_team`, JSON.stringify(data_team));
            button_export.attr('disabled', true);
            button_import_contest.attr('disabled', true);
        }
    } catch(e) {
        console.error(e);
    }
}
function ClearStorage() {
    data_room = null;
    data_team = null;
    localStorage.removeItem(`ojtool_seatdraw_data_room`);
    localStorage.removeItem(`ojtool_seatdraw_data_team`);
    seatdraw_table.bootstrapTable('removeAll');
    room_info_table.bootstrapTable('removeAll');
}
$('.button_fullscreen').click(function(){ToggleFullScreen('seatdraw_div_fullscreen')});
// **************************************************
// Room
// **************************************************
function RoomData2Str() {
    if(data_room != null) {
        tmp_room_str = '';
        for(let i = 0; i < data_room.length; i ++) {
            tmp_room_str += `${data_room[i].room_name}\t${data_room[i].seat_start}\t${data_room[i].seat_end}\n`
        }
    }
    return tmp_room_str;
}
function RoomSubmit() {
    let err_msg = "";
    tmp_room_str = document.getElementById('room_input').value;
    if(tmp_room_str.trim() == '') {
        alertify.notify("什么也没有发生");
        return;
    }
    let room_str_list = tmp_room_str.trim().split('\n');
    let tmp_data_room = [];
    let cnt = 0, last_seat = 0, room_name, seat_start, seat_end, seat_num;
    for(let i = 0; i < room_str_list.length; i ++) {
        let line = room_str_list[i].trim().split(/[#\t]/);
        if(line.length < 2) {
            if(line.length && line[0].trim() != '') {
                err_msg = `数据格式不正确：${room_str_list[i]}`;
                break;
            }
            continue;
        }
        if(line.length == 2) {
            seat_start = last_seat + 1;
            seat_num = parseInt(line[1]);
            if(isNaN(seat_num) || seat_num < 0) {
                err_msg = `数据格式不正确：${room_str_list[i]}`;
                break;
            }
            seat_end = seat_start + seat_num - 1;
        } else {
            seat_start = parseInt(line[1]);
            seat_end = parseInt(line[2]);
            if(isNaN(seat_start) || isNaN(seat_end)) {
                err_msg = `数据格式不正确：${room_str_list[i]}`;
                break;
            }
            if(seat_end < seat_start) {
                [seat_start, seat_end] = [seat_end, seat_start];
            }
            if(seat_start <= last_seat) {
                err_msg = `机位编号有冲突：${room_str_list[i]}`;;
                break;
            }
            seat_num = seat_end - seat_start + 1;
        }
        room_name = line[0].trim();
        if(room_name.length > 49) {
            err_msg = `房间/区域名称过长：${room_str_list[i]}`;
            break;
        } else if(seat_num > 5000) {
            err_msg = `机位数过多：${room_str_list[i]}`;
            break;
        }
        tmp_data_room.push({
            "room_name": room_name,
            "seat_start": seat_start,
            "seat_end": seat_end,
            "seat_num": seat_num
        });
        last_seat = seat_end;
        cnt ++;
    }
    if(err_msg.length > 0) {
        alertify.error(err_msg);
        return;
    }
    data_room = tmp_data_room;
    room_info_table.bootstrapTable('load', data_room);
    alertify.success(`成功读取${cnt}个房间/区域`);
    SetStorage();
}
$(".room_submit").click(function() {
    RoomSubmit();
});
$('.room_cancel').click(function() {
    room_show_modal.hide();
    tmp_room_str = document.getElementById('room_input').value;
    alertify.notify("什么也没有发生");
});
$('#room_show_modal').on('shown.bs.modal', function() {
    room_input.focus();
});
function LoadRoom() {
    room_input.val(RoomData2Str());
    room_show_modal.show();
}

// **************************************************
// Team
// **************************************************
function TeamData2Str() {
    if(data_team != null) {
        tmp_team_str = '';
        for(let i = 0; i < data_team.length; i ++) {
            let line_item = [];
            for(let j = 0; j < team_keys.length; j ++) {
                line_item.push(data_team[i]?.[team_keys[j]] ? data_team[i]?.[team_keys[j]] : "");
            }
            tmp_team_str += line_item.join('\t');
            tmp_team_str += "\n";
        }
    }
    return tmp_team_str;
}
function TeamSubmit() {
    let err_msg = "";
    let warn_msg = "";
    tmp_team_str = document.getElementById('team_input').value;
    if(tmp_team_str.trim() == '') {
        alertify.notify("什么也没有发生");
        team_show_modal.hide();
        return;
    }
    let team_str_list = tmp_team_str.trim().split('\n');
    let tmp_data_team = [];
    let cnt = 0;
    for(let i = 0; i < team_str_list.length; i ++) {
        let line_str = team_str_list[i].trim();
        if(line_str == '') {
            continue;
        }
        let line = line_str.split(/[#\t]/);
        let team_item = {};
        let line_warn = [];
        for(let j = 0; j < team_keys.length; j ++) {
            if(team_keys[j] != 'label' && (j >= line.length || line[j].length == 0)) {
                line_warn.push(`缺少[${team_keys[j]}]`);
                team_item[team_keys[j]] = line[j];
                continue;
            }
            if(j >= line.length) {
                // team label
                team_item[team_keys[j]] = "";
                break;
            }
            switch(team_keys[j]) {
                case "name":
                    if(line[j].length > 49) {
                        line_warn.push("队名过长");
                    }
                    break;
                case "school":
                    if(line[j].length > 49) {
                        line_warn.push("校名过长");
                    }
                    break;
                case "tmember":
                    if(line[j].length > 63 || line[j].split('、').length > 10) {
                        line_warn.push("成员过多或名字过长");
                    }
                    break;
                case "coach":
                    if(line[j].length > 24) {
                        line_warn.push("教练信息过长");
                    }
                    break;
                case "tkind":
                    let tkind = parseInt(line[j]);
                    if(isNaN(tkind) || tkind < 0 || tkind > 4) {
                        line_warn.push("队伍类型不是本系统格式");
                    }
                    break;
            }
            team_item[team_keys[j]] = line[j];
        }
        tmp_data_team.push(team_item);
        if(line_warn.length > 0) {
            warn_msg += `<code>${DomSantize(line_str)}</code>：<br/>${line_warn.join('; ')}<br/>`
        }
        cnt ++;
    }
    if(warn_msg.length > 0) {
        alertify.confirm('提示', `存在部分数据可能有问题，请检查。<br/>抽签可能无法正常导入比赛，但仍可继续<br/>${warn_msg}`, function(){
            data_team = tmp_data_team;
            seatdraw_table.bootstrapTable('load', data_team);
            team_show_modal.hide();
            SetStorage();
            alertify.success(`读取到${cnt}个队伍信息`);
        }, function() {});
    } else {
        data_team = tmp_data_team;
        LoadTeam2Table(data_team)
        team_show_modal.hide();
        alertify.success(`成功读取${cnt}个队伍信息`);
        SetStorage();
    }
}
$(".team_submit").click(function() {
    TeamSubmit();
});
$('.team_cancel').click(function() {
    team_show_modal.hide();
    tmp_team_str = document.getElementById('team_input').value;
    alertify.notify("什么也没有发生");
});
$('#team_show_modal').on('shown.bs.modal', function() {
    team_input.focus();
});
function LoadTeam() {
    team_input.val(TeamData2Str());
    team_show_modal.show();
}
function SetSeatTotal() {
    let seat_total = 0;
    if(data_room != null) {
        for(let i = 0; i < data_room.length; i ++) {
            seat_total += data_room[i].seat_num;
            seat_max = Math.max(seat_max, data_room[i].seat_end);
        }
        seat_num_span.text(seat_total);
        team_id_num_len = parseInt(seat_max * 1.2).toString().length;
    }
}
document.addEventListener('click', function(e) {
    if(e.target.classList.contains('button_room')) {
        LoadRoom();
    } else if(e.target.classList.contains('button_team')) {
        LoadTeam();
    }
})
// **************************************************
// Draw
// **************************************************
function ResolveAdj(team_idx) {
    function d(ii) {
        return (ii + team_idx.length) % team_idx.length;
    }
    let team_idx_reverse = new Array(team_idx.length);      // 第 x 个座位坐着第 team_idx_reverse[x] 个学校
    for(let i = 0; i < team_idx.length; i ++) {
        team_idx_reverse[team_idx[i]] = i;
    }
    let n = team_idx.length << 2;
    
    function AdjGetIns(i, school_same_name) {
        // 当i与i-1同school时，找一个不同school的与 i 交换
        for(let k = 0; k < 5; k ++) {
            let rd = Rand() % team_idx.length;
            if(data_team[team_idx_reverse[rd]].school != school_same_name &&
                data_team[team_idx_reverse[d(rd - 1)]].school != school_same_name &&
                data_team[team_idx_reverse[d(rd + 1)]].school != school_same_name
            ) {
                return rd;
            }
        }
        let j = i + 1;
        for(; j < n && data_team[team_idx_reverse[d(j)]].school == data_team[team_idx_reverse[d(i - 1)]].school; j ++);
        if(j < n) {
            return d(j, data_team[team_idx_reverse[d(i)]].school);
        }
        return null;
    }
    for(let i = 1; i < n; i ++) {
        if(data_team[team_idx_reverse[d(i)]].school == data_team[team_idx_reverse[d(i - 1)]].school) {
            let j = AdjGetIns(i, data_team[team_idx_reverse[d(i)]].schoo);
            if(j != null) {
                [team_idx_reverse[d(i)], team_idx_reverse[d(j)]] = [team_idx_reverse[d(j)], team_idx_reverse[d(i)]];
            }
        }
    }
    for(let i = 0; i < team_idx.length; i ++) {
        team_idx[team_idx_reverse[i]] = i;
    }
}
function TeamNum2TeamStr(team_num_id) {
    return `team${pad0left(team_num_id, team_id_num_len, 0)}`;
}
function DrawBySeed(seed) {
    if(data_team == null || data_room == null) {
        alertify.error("请录入队伍与房间/区域");
        return false;
    }
    SetSeed(seed);
    let seat_list = [];
    for(let i = 0; i < data_room.length; i ++) {
        for(let j = data_room[i].seat_start; j <= data_room[i].seat_end; j ++) {
            seat_list.push({
                'team_num_id': j,
                'room_id': i
            });
        }
    }
    function getteam(num) {
        if(num >= seat_list.length) {
            return null;
        }
        return {
            'team_num_id': seat_list[num].team_num_id,
            'team_id': TeamNum2TeamStr(seat_list[num].team_num_id),
            'room': data_room[seat_list[num].room_id].room_name
        };
    }
    let team_idx = [];  // 第 i 个队坐第 team_idx[i] 个位置
    for(let i = 0; i < data_team.length; i ++) {
        team_idx.push(i);
    }
    for(let i = 0; i < data_team.length; i ++) {
        let j = Rand() % data_team.length;
        if(i != j) {
            [team_idx[i], team_idx[j]] = [team_idx[j], team_idx[i]];
        }
    }
    ResolveAdj(team_idx);
    for(let i = 0; i < data_team.length; i ++) {
        let team = getteam(team_idx[i]);
        if(team != null) {
            data_team[i].team_num_id = team.team_num_id;
            data_team[i].team_id = team.team_id;
            data_team[i].room = team.room;
        } else if('team_id' in data_team[i]) {
            delete data_team[i].team_num_id;
            delete data_team[i].team_id;
            delete data_team[i].room;
        }
        $(`#team_div_${i}`).text('team_id' in data_team[i] ? data_team[i].team_id : '');
        $(`#room_div_${i}`).text('room' in data_team[i] ? data_team[i].room : '');
    }
    button_export.removeAttr('disabled');
    button_import_contest.removeAttr('disabled');
    return true;
}
function DrawNext() {
    if(flag_draw) {
        seed_draw = Rand();
        seatdraw_seed.val(seed_draw);
        DrawBySeed(seed_draw)
        setTimeout(DrawNext, Math.max(data_team.length >> 2, 100));
    }
}
function DrawStart() {
    if(data_team == null || data_room == null) {
        alertify.error("请先录入队伍和房间/区域");
        return;
    }
    seatdraw_button.text("停！");
    seatdraw_button.removeClass("btn-success").addClass("btn-danger");
    $('.btn-func').attr('disabled', true);
    seatdraw_seed.attr('disabled', true)
    flag_draw = true;
    SetSeed(new Date().getTime());
    DrawNext();
}
function DrawStop() {
    seatdraw_button.text("开始！");
    seatdraw_button.removeClass("btn-danger").addClass("btn-success");
    $('.btn-func').removeAttr('disabled');
    seatdraw_seed.removeAttr('disabled');
    flag_draw = false;
}
function DrawToggle() {
    if(!flag_draw) {
        DrawStart();
    } else {
        DrawStop();
    }
}
seatdraw_seed.on('input', function() {
    seed_draw = parseInt("0" + seatdraw_seed.val().replace(/\D/g, "0"));
    if(seed_draw > SEED_MOD) {
        seed_draw = SEED_MOD;
    }
    seatdraw_seed.val(seed_draw);
})
seatdraw_button.click(function() {
    DrawToggle();
});
$('.button_draw').click(function() {
    if(DrawBySeed(seed_draw)) {
        alertify.success(`按种子"${seed_draw}"生成机位执行完毕`);
    }
});
function GetTeamAccordRoom() {
    // 空机位也生成 team 信息
    let team_full = [];
    
    let team_map = {};
    for(let i = 0; i < data_team.length; i ++) {
        if('team_num_id' in data_team[i]) {
            team_map[data_team[i].team_num_id] = data_team[i];
        }
        data_team[i].exported = false;
    }
    for(let i = 0; i < data_room.length; i ++) {
        for(let sj = data_room[i].seat_start; sj <= data_room[i].seat_end; sj ++) {
            if(sj in team_map) {
                team_full.push(team_map[sj]);
                team_map[sj].exported = true;
            } else {
                team_full.push({
                    "team_num_id": sj,
                    "team_id": TeamNum2TeamStr(sj),
                    "room": data_room[i].room_name
                });
            }
        }
    }
    for(let i = 0; i < data_team.length; i++) {
        if(!data_team[i].exported) {
            team_full.push(data_team[i]);
        }
    }
    return team_full;
}
function ExportCsv(btype) {
    if(data_team == null || data_room == null) {
        alertify.error("未正确录入队伍或房间/区域");
        return;
    }
    function AddLine(hl, team, idx) {
        let team_item = [];
        for (let j = 0; j < hl.length; j ++) {
            if(hl[j] == 'idx') {
                team_item.push(`${idx}`);
            } else {
                team_item.push(hl[j] in team ? `"${team[hl[j]]}"` : "");
            }
        }
        ret.push(team_item.join(",") + "\n");
    }
    let ret = [];
    let team_all = [];
    let filename;
    ret.push(header_list_cn[btype].join(",") + "\n");
    if(btype == 'school') {
        team_all = data_team;
        filename = "单位顺序";
    } else {
        // 按机位顺序导出，包含空机位。最后导出无机位队伍（机位不足的情况）
        team_all = GetTeamAccordRoom();
        filename = "队伍ID顺序";
    }
    for(let i = 0; i < team_all.length; i ++) {
        AddLine(header_list[btype], team_all[i], i + 1);
    }
    var blob = new Blob(['\uFEFF' + ret.join('')], {
        type: 'text/plain;charset=utf-8',
    });
    var downloadLink = document.createElement("a");
    downloadLink.href = URL.createObjectURL(blob);
    downloadLink.download = `抽签结果_${filename}.csv`;
    downloadLink.click();
}
button_export.click(function() {
    ExportCsv(this.getAttribute("btype"));
});

function Import2Contest(cid) {
    cid = parseInt(cid);
    if(isNaN(cid)) {
        alertify.error("比赛ID需为数字");
        return;
    }
    let team_full = GetTeamAccordRoom();
    $.get('/cpcsys/admin/teamgen_list_ajax?cid=' + cid, function(ret) {
        let ret_map = {};
        for(let i = 0; i < ret.length; i ++) {
            if(ret[i].team_id.startsWith('team')) {
                ret_map[parseInt(ret[i].team_id.replace('team', ''))] = ret[i];
            }
        }
        let team_insert_list = [];
        for(let i = 0; i < team_full.length; i ++) {
            if('team_num_id' in team_full[i]) {
                let team_item = [
                    /* 'team_num_id'    : */  TeamNum2TeamStr(team_full[i].team_num_id).replace('team', ''),
                    /* 'name'           : */  'name' in team_full[i] ? team_full[i].name : '',
                    /* 'school'         : */  'school' in team_full[i] ? team_full[i].school : '',
                    /* 'tmember'        : */  'tmember' in team_full[i] ? team_full[i].tmember : '',
                    /* 'coach'          : */  'coach' in team_full[i] ? team_full[i].coach : '',
                    /* 'room'           : */  'room' in team_full[i] ? team_full[i].room : '',
                    /* 'tkind'          : */  'tkind' in team_full[i] ? team_full[i].room : 0,
                    /* 'password'       : */  team_full[i].team_num_id in ret_map ? ret_map[team_full[i].team_num_id].password : ''
                ];
                team_insert_list.push(team_item.join('\t'));
            }
        }
        if(team_insert_list.length > 0) {
            $.post('/cpcsys/admin/contest_teamgen_ajax?cid=' + cid, {'team_description': team_insert_list.join('\n'), 'reset_team': 'on'}, function(ret) {
                if(typeof(ret) != 'object') {
                    alertify.error("导入失败。比赛ID：" + $cid);
                } else if(!('code' in ret) || ret.code != 1) {
                    if('msg' in ret && ret.msg != '') {
                        alertify.error(ret.msg)
                    } else {
                        alertify.error("导入失败。比赛ID：" + $cid);
                    }
                } else {
                    alertify.alert("成功录入数据：" + ret.data.success_num, function() {
                        window.open('/cpcsys/admin/contest_teamgen?cid=' + cid);
                    });
                }
            })
        } else {
            alertify.error("没有可以导入比赛的有效队伍");
        }
    });
}
button_import_contest.click(function() {
    alertify.prompt('导入抽签结果至比赛', ``,
        function(evt, value){
            Import2Contest(value);
        },
        function(){
            alertify.notify('什么也没有发生');
        }
    ).set({'closableByDimmer': false}); 
})
$('.button_clear').click(function() {
    alertify.confirm("确认", "确认清空缓存？", function() {
        ClearStorage();
        alertify.success("缓存已清");
    }, function() {
        alertify.notify("什么也没有发生");
    })
    
});
window.onkeydown = (event) => {
    if (!event || !event.isTrusted || !event.cancelable) {
        return;
    }
    const key = event.key;
    if (key === 's' || key === 'S') {
        DrawToggle();
    }
}
$(document).ready(function() {
    // alertify.set('notifier','position', 'top-center');
    GetStorage();
})
