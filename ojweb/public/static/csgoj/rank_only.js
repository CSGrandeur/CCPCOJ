var ranklist_toobar = $('#ranklist_toobar');
var ranktable = $('#ranklist_table');
var school_filter = $('#school_filter');
var schoolDict = [];
var schoolFilterCookieName = 'contest_school_filter_' + cid;
var school_filter_all = $('#school_filter_all'), school_filter_none = $('#school_filter_none');

if(ckind == 'cpcsys') {
    var tkind_filter = $('#tkind_filter');
    var tkindDict = {
        "Common": true,
        "Girls": true,
        "Star": true
    };
    var tkindList = ["Common", "Girls", "Star"];
    var tkindFilterCookieName = 'contest_tkind_filter_' + cid;
    var tkind_filter_all = $('#tkind_filter_all'), tkind_filter_none = $('#tkind_filter_none');
}

var valid_team_num; // 做出题的队数统计
var star_team_num, star_valid_team_num;  // 打星队统计
let formalValidTeamNum;
let ratio_gold;
let ratio_silver;
let ratio_bronze;
let rank_gold;
let rank_silver;
let rank_bronze;
function UpdateAwardInfo() {
    ratio_gold = parseFloat($("#award_ratio").attr("gold"));
    ratio_silver = parseFloat($("#award_ratio").attr("silver"));
    ratio_bronze = parseFloat($("#award_ratio").attr("bronze"));
    if(ratio_gold < 100) {
        ratio_gold = ratio_gold / 100.0 - 0.0000001;
    }
    if(ratio_silver < 100) {
        ratio_silver = ratio_silver / 100.0;
    }
    if(ratio_bronze < 100) {
        ratio_bronze = ratio_bronze / 100.0;
    }

    cnt_base = formalValidTeamNum;
    let award_rank = GetAwardRank(cnt_base, ratio_gold, ratio_silver, ratio_bronze);
    rank_gold = award_rank[0];
    rank_silver = award_rank[1];
    rank_bronze = award_rank[2];
}
function RankLoadSuccessCallback(data) {
    if(typeof(data) != 'undefined') {
        RankDataPreprocess(data);
        schoolDict = [];
        valid_team_num = 0;
        star_team_num = 0;
        star_valid_team_num = 0;
        for(var i = 0; i < data.length; i ++) {
            if(data[i].solved > 0) {
                valid_team_num ++;;
                if(data[i]['tkind'] == 2) {
                    star_valid_team_num ++;
                }
            }
            if(data[i]['tkind'] == 2) {
                star_team_num ++;
            }
            if(typeof(data[i]['school']) != 'undefined') {
                var school = $.trim(data[i]['school']);
                if(school != '')
                    schoolDict[data[i]['school']] = true;
            }
        }
        formalValidTeamNum = valid_team_num - star_valid_team_num;
        UpdateAwardInfo();
        UpdateSchoollistFromCookie();
        UpdateSchoolFilter();
        if(ckind == 'cpcsys') {
            UpdateTkindlistFromCookie();
            UpdateTkindFilter();
        }
        UpdateFilterRank();
    }
};
function UpdateSchoollistFromCookie() {
    //从cookie更新school表的选中情况
    var schoolCookie = localStorage.getItem(schoolFilterCookieName);
    if(typeof(schoolCookie) != 'undefined') {
        try {
            var newList = JSON.parse(schoolCookie);
            for(var school in schoolDict) {
                schoolDict[school] = newList.indexOf(school) > -1;
            }
        }
        catch(e){}
    }
}
function UpdateSchoollistCookie(){
    //更新cookie
    localStorage.setItem(schoolFilterCookieName, JSON.stringify(school_filter.val()));
}
function UpdateTkindlistFromCookie() {
    //从cookie更新tkind表的选中情况
    var tkindCookie = localStorage.getItem(tkindFilterCookieName);
    if(typeof(tkindCookie) != 'undefined') {
        try {
            var newList = JSON.parse(tkindCookie);
            for(var nl = 0; nl < tkindList.length; nl ++){
                tkindDict[tkindList[nl]] = newList.indexOf(nl.toString()) > -1;
            }
        }
        catch(e){}
    }
}
function UpdateTkindlistCookie(){
    //更新tkind cookie
    localStorage.setItem(tkindFilterCookieName, JSON.stringify(tkind_filter.val()));
}
school_filter_all.on('click', function(){
    school_filter.selectpicker('selectAll');
});
school_filter_none.on('click', function(){
    school_filter.selectpicker('deselectAll');
});
school_filter.on('change', function(e){
    //select变更触发
    UpdateFilterRank();
    UpdateSchoollistCookie();
});
if(ckind == 'cpcsys') {
    tkind_filter_all.on('click', function(){
        tkind_filter.selectpicker('selectAll');
    });
    tkind_filter_none.on('click', function(){
        tkind_filter.selectpicker('deselectAll');
    });
    tkind_filter.on('change', function(e){
        // tkind select变更触发
        UpdateFilterRank();
        UpdateTkindlistCookie();
    });
}
function UpdateSchoolFilter() {
    //更新filter的DOM中select列表
    school_filter.empty();
    var setSelect = [];
    var selectOptionList = [];
    for (var school in schoolDict)
    {
        if (schoolDict[school] == true) {
            setSelect.push(school);
        }
        selectOptionList.push(school);
    }
    //对学校列表按字母序排序
    selectOptionList.sort();
    for(var i = 0; i < selectOptionList.length; i ++)
        school_filter.append("<option>" + selectOptionList[i] + "</option>");

    school_filter.selectpicker('val', setSelect);
    school_filter.selectpicker('refresh');
}
function UpdateTkindFilter() {
    var setSelect = [];
    var selectOptionList = [];
    for (var nl = 0; nl < tkindList.length; nl ++) {
        if (tkindDict[tkindList[nl]] == true) {
            setSelect.push(nl.toString());
        }
        selectOptionList.push(tkindList[nl]);
    }
    for(var i = 0; i < selectOptionList.length; i ++) {
        tkind_filter.append("<option value="+i+">" + selectOptionList[i] + "</option>");
    }
    tkind_filter.selectpicker('val', setSelect);
    tkind_filter.selectpicker('refresh');
}
function UpdateFilterRank() {
    //更新rank显示
    let selectedSchoolList = school_filter.val();
    for(let school in schoolDict) {
        schoolDict[school] = selectedSchoolList.indexOf(school) > -1;
    }
    if(ckind == 'cpcsys' && tkind_filter.length > 0) {
        let selectedTkindList = tkind_filter.val();
        for(let i = 0; i < selectedTkindList.length; i ++) {
            selectedTkindList[i] = parseInt(selectedTkindList[i]);
        }
        for(var nl in tkindDict) {
            tkindDict[tkindList[parseInt(nl)]] = true;
        }
        ranktable.bootstrapTable('filterBy', {
            'school': selectedSchoolList,
            'tkind': selectedTkindList
        });
    }
    else {
        ranktable.bootstrapTable('filterBy', {
            'school': selectedSchoolList
        });
    }
}

function FormatterRank(value, row, index, field) {
    return `<span acforprize='${row.solved}'>${value}</span>`;
}
function FormatterPenalty(value, row, index, field) {
    let time_mi = Math.floor(Timestr2Sec(value) / 60 + 0.00000001);
    return `<span class="penalty_span d-inline-block" time_str="${value}" time_mi="${time_mi}" style="min-width:60px;">${time_mi}</span>`
}

document.addEventListener('mouseover', function(event) {
    if (event.target.classList.contains('penalty_span')) {
        event.target.textContent = event.target.getAttribute('time_str');
        event.stopPropagation();

    }
});

document.addEventListener('mouseout', function(event) {
    if (event.target.classList.contains('penalty_span')) {
        event.target.textContent = event.target.getAttribute('time_mi');
        event.stopPropagation();

    }
});
function rankCellStyle(value, row, index) {
    if(row.solved == 0) {
        return {
            css: {
                'font-weight': 'bold',
                'font-size': '1.5rem'
            }
        };
    }
    var rankIndex = row.rank;
    var bakcolor = '', font_color;
    if(rankIndex <= rank_gold) {
        bakcolor = 'gold';
        font_color = 'black';
    }
    else if(rankIndex <= rank_silver) {
        bakcolor = 'slategray';
        font_color = 'white';
    }
    else if(rankIndex <= rank_bronze) {
        bakcolor = 'Peru';
        font_color = 'white';
    } else {
        bakcolor = 'transparent';
        font_color = 'black';
    }
    return {
        css: {
            'background-color': bakcolor,
            'color': font_color,
            'font-weight': 'bold',
            'font-size': '1.5rem'
        }
    };
}
function sec2str(sec) {
    // 训练类比赛可能超过100小时，多于二位数了。
    let h = Math.floor(sec / 3600 + 1e-6), m = Math.floor(sec % 3600 / 60 + 1e-6), s = sec % 60;
    if(sec < 360000) {
        return `${pad0left(h, 2, 0)}:${pad0left(m, 2, 0)}:${pad0left(s, 2, 0)}`;
    } else {
        return `${h}:${pad0left(m, 2, 0)}:${pad0left(s, 2, 0)}`;
    }
}
function acCellStyle(value, row, index) {
    let pstatus = typeof(value) != 'undefined' && value ? value.pst : 0;
    return {
        css: {
            'background-color': rank_cell_color_list[pstatus],
            'min-width': '75px'
        }
    };
}
function FormatterIdName(value, row, index, field) {
    let team_url = FormatterRankUserId(row.user_id, row, index, field);
    let team_school = `<div class='text-truncate text-warning' style='max-width:280px;'>${row.school}</div>`;
    let team_name = `<div class='text-truncate' style='max-width:280px;'>${value}</div>`;
    return `<div title="${row.nick} | ${row.tmember} | ${row.coach} @ ${row.school}"><div>${team_url}</div>${team_school}${team_name}</div>`;
}
function FormatterRankPro(value, row, index, field) {
    function AC(res) {
        return typeof(res) == 'undefined' || res === null ? '&nbsp;' : sec2str(res);
    }
    function TR(res) {
        return typeof(res) == 'undefined' || res === null || value.ac ? '' : `? ${res}`;
    }
    function WA(res) {
        return typeof(res) == 'undefined' || res === null ? '&nbsp;' : `(- ${res})`;
    }
    return `<span pstatus="${row[field + '_pstatus']}">${AC(value.ac)}${TR(value.tr)}<br/>${WA(value.wa)}</span>`;
}
function FormatterRankProSchool(value, row, index, field) {
    function AC(res) {
        return typeof(res) == 'undefined' || res === null ? '' : sec2str(res);
    }
    function ACN(res) {
        return typeof(res) == 'undefined' || res === null ? '' : res;
    }
    function TR(res) {
        return typeof(res) == 'undefined' || res === null ? '' : `<strong>[? ${res}]</strong>`;
    }
    function WA(res) {
        return typeof(res) == 'undefined' || res === null ? '' : `(- ${res})`;
    }
    return `<span pstatus="${value.pst}">${AC(value.ac)}<br/>${ACN(value.acn)}${WA(value.wan)}<br/>${TR(value.trn)}</span>`;
}
function FormatterSchoolLogo(value, row, index, field) {
    // return `<img school="${value}" src="${SchoolLogoUri(value)}" onerror="SchoolLogoUriOnError(this)" width=48/>`;
    return `<img school="${value}" src="/static/image/global/badge.png" class="school_logo" school="${value}" title="${value}" loading="lazy" onerror="SchoolLogoUriOnError(this)" width=48/>`;
}
ranktable.on('post-body.bs.table', function() {
    BatchProcessSchoolLogo();
});
