let table_award_table = $("#award_table");
let page_info = $("#award_ratio");
let contest_title = page_info.attr("contest_title");
let switch_with_star_team = $("#switch_with_star_team");
let switch_all_team_based = $("#switch_all_team_based");
let switch_one_two_three = $("#switch_one_two_three");
let flag_with_star_team = false;
let flag_all_team_based = false;
let flag_one_two_three = false;
let data_table = [];
let data_show = [];
let page_module = page_info.attr("module");
let page_cid = page_info.attr("cid");

let ratio_gold = parseInt(page_info.attr("gold"));
let ratio_silver = parseInt(page_info.attr("silver"));
let ratio_bronze = parseInt(page_info.attr("bronze"));

let map_award = {};
let award_to_export = [];
if (ratio_gold < 100) {
    ratio_gold = ratio_gold / 100.0 - 0.0000001;
}
if (ratio_silver < 100) {
    ratio_silver = ratio_silver / 100.0;
}
if (ratio_bronze < 100) {
    ratio_bronze = ratio_bronze / 100.0;
}

let map_data_show = [];
let ac_order = [];
let team_struggle = null,
    team_best_girl = null;
let rank_gold;
let rank_silver;
let rank_bronze;
function CellStyleAward(value, row, index, field) {
    return {
        css: {
            "white-space": "nowrap",
        },
    };
}
$(document).ready(function () {
    switch_with_star_team.bootstrapSwitch();
    switch_all_team_based.bootstrapSwitch();
    switch_one_two_three.bootstrapSwitch();
    $.get(
        "/" + page_module + "/contest/ranklist_ajax",
        { cid: page_cid },
        function (ret) {
            RankDataPreprocess(ret);
            for (let i = 0; i < ret.length; i++) {
                let tmp = {
                    coach: ret[i]["coach"],
                    nick: ret[i]["nick"],
                    // "penalty": ret[i]['penalty'],
                    penalty: ret[i]["penalty_mi"],
                    rank: ret[i]["rank"],
                    school: ret[i]["school"],
                    solved: parseInt(ret[i]["solved"]),
                    tkind: ret[i]["tkind"],
                    tmember: ret[i]["tmember"],
                    user_id: ret[i]["user_id"],
                    fb: [],
                };
                for (let pro in ret[i]) {
                    if (/^[A-Z]$/.test(pro) && ret[i][pro]?.ac != null) {
                        // 从rank数据获取的必定是有效AC，不会是AC之后重复提交的AC
                        ac_order.push({
                            ac: ret[i][pro].ac,
                            user_id: ret[i].user_id,
                        });
                        if (ret[i][pro].pst == 3) {
                            tmp.fb.push(pro);
                        }
                    }
                }
                data_table.push(tmp);
            }
            ac_order.sort((a, b) => {
                return a.ac == b.ac ? 0 : a.ac < b.ac ? 1 : -1;
            });
            refresh_data_show();
        }
    );
});
function GetTeamStruggle() {
    // 顽强拼搏
    team_struggle = null;
    for (let i = 0; i < ac_order.length; i++) {
        if (
            ac_order[i].user_id in map_data_show &&
            map_data_show[ac_order[i].user_id].rank > rank_bronze
        ) {
            team_struggle = ac_order[i].user_id;
            break;
        }
    }
    return team_struggle;
}
function refresh_data_show() {
    // https://ccpc.io/rules/45
    // 3) 当正式参赛女队（3名队员皆为女生）数目大于等于3时，可以设置最佳女队奖，排名最高且获得铜奖或以上奖项的正式参赛女队获得最佳女队奖，颁发奖牌和获奖证书。
    // 4) 可以设置顽强拼搏奖，未获得金奖、银奖或铜奖的正式队伍中最晚解出题目的1或2支参赛队获得顽强拼搏奖，颁发奖牌。
    data_show = [];
    let rk_real = 0,
        rk_show = 0;
    let cnt_base = 0;
    let last_solved = -1,
        last_penalty = "";
    let last_solved_school = -1,
        last_penalty_school = "";
    map_data_show = {};
    team_best_girl = null;
    let team_girl_num = 0;
    let map_school_appear = {},
        rk_school_real = 0,
        rk_school = 0;
    for (let i = 0; i < data_table.length; i++) {
        if (data_table[i]["tkind"] < 2 || flag_with_star_team) {
            // 是否包含打星队
            let tmp = data_table[i];
            rk_real++;
            if (!(data_table[i].school in map_school_appear)) {
                rk_school_real++;
                if (
                    tmp.solved != last_solved_school ||
                    tmp.penalty != last_penalty_school
                ) {
                    rk_school = rk_school_real;
                    last_solved_school = tmp.solved;
                    last_penalty_school = tmp.penalty;
                }
                tmp.school_rank = rk_school;
                map_school_appear[data_table[i].school] = true;
            } else {
                tmp.school_rank = "NO SCHOOL RANK";
            }
            if (tmp["solved"] != last_solved || tmp["penalty"] != last_penalty) {
                rk_show = rk_real;
                last_solved = tmp["solved"];
                last_penalty = tmp["penalty"];
            }
            tmp["rank"] = rk_show;
            if (data_table[i]["solved"] > 0 || flag_all_team_based) {
                // 基数是否包含未过题队
                cnt_base++;
            }
            data_show.push(tmp);
            if (tmp.tkind == 1) {
                team_girl_num++;
            }
            map_data_show[tmp.user_id] = tmp;
        }
    }

    let award_rank = GetAwardRank(
        cnt_base,
        ratio_gold,
        ratio_silver,
        ratio_bronze
    );
    rank_gold = award_rank[0];
    rank_silver = award_rank[1];
    rank_bronze = award_rank[2];
    map_award = {};
    award_to_export = flag_one_two_three
        ? ["一等奖", "二等奖", "三等奖"]
        : ["金奖", "银奖", "铜奖"];
    award_to_export.push("最快解题奖");
    award_to_export.push("最佳女队/女生奖");
    award_to_export.push("顽强拼搏奖");
    award_to_export.push("冠亚季军");
    for (let i = 0; i < award_to_export.length; i++) {
        map_award[award_to_export[i]] = [];
    }
    for (let i = 0; i < data_show.length; i++) {
        if (data_show[i]["solved"] <= 0) {
            continue;
        }
        // 金银铜
        if (data_show[i]["rank"] <= rank_gold) {
            data_show[i].award = flag_one_two_three ? "一等奖" : "金奖";
        } else if (data_show[i]["rank"] <= rank_silver) {
            data_show[i].award = flag_one_two_three ? "二等奖" : "银奖";
        } else if (data_show[i]["rank"] <= rank_bronze) {
            data_show[i].award = flag_one_two_three ? "三等奖" : "铜奖";
        } else {
            data_show[i].award = "-";
        }
        if (data_show[i].award != "-") {
            map_award[data_show[i].award].push(data_show[i]);
        }
        // 冠亚季
        switch (data_show[i].school_rank) {
            case 1:
                data_show[i].award = "冠军学校<br/>" + data_show[i].award;
                map_award["冠亚季军"].push({ remark: "冠军学校", team: data_show[i] });
                break;
            case 2:
                data_show[i].award = "亚军学校<br/>" + data_show[i].award;
                map_award["冠亚季军"].push({ remark: "亚军学校", team: data_show[i] });
                break;
            case 3:
                data_show[i].award = "季军学校<br/>" + data_show[i].award;
                map_award["冠亚季军"].push({ remark: "季军学校", team: data_show[i] });
                break;
        }
        // 最佳女队/女生
        if (
            team_girl_num >= 3 &&
            team_best_girl === null &&
            data_show[i].tkind == 1 &&
            data_show[i].rank <= rank_bronze
        ) {
            team_best_girl = data_show[i].user_id;
            data_show[i].award +=
                typeof (data_show[i].tmember == "string") &&
                    (data_show[i].tmember.includes("、") ||
                        data_show[i].tmember.includes(",") ||
                        data_show[i].tmember.includes("，"))
                    ? "<br/>最佳女队奖"
                    : "<br/>最佳女生奖";
            map_award["最佳女队/女生奖"].push(data_show[i]);
        }
        // 最快解题
        for (let j = 0; j < data_show[i].fb.length; j++) {
            data_show[i].award += `<br/>最快解题奖(${data_show[i].fb[j]})`;
            map_award["最快解题奖"].push({
                remark: data_show[i].fb[j],
                team: data_show[i],
            });
        }
    }
    map_award["最快解题奖"].sort((a, b) => {
        return a.remark == b.remark ? 0 : a.remark < b.remark ? -1 : 1; // 26个字母范围内有效
    });
    // 顽强拼搏
    GetTeamStruggle();
    if (team_struggle !== null) {
        if (map_data_show[team_struggle].award == "-") {
            map_data_show[team_struggle].award = "";
        } else {
            map_data_show[team_struggle].award += "<br/>";
        }
        map_data_show[team_struggle].award += "顽强拼搏奖";
        map_award["顽强拼搏奖"].push(map_data_show[team_struggle]);
    }
    table_award_table.bootstrapTable("load", data_show);
}
switch_with_star_team.on(
    "switchChange.bootstrapSwitch",
    function (event, state) {
        if (state == true) {
            flag_with_star_team = true;
        } else {
            flag_with_star_team = false;
        }
        refresh_data_show();
    }
);
switch_all_team_based.on(
    "switchChange.bootstrapSwitch",
    function (event, state) {
        if (state == true) {
            flag_all_team_based = true;
        } else {
            flag_all_team_based = false;
        }
        refresh_data_show();
    }
);
switch_one_two_three.on(
    "switchChange.bootstrapSwitch",
    function (event, state) {
        if (state == true) {
            flag_one_two_three = true;
        } else {
            flag_one_two_three = false;
        }
        refresh_data_show();
    }
);
function TkindAwardFormatter(value, row, index) {
    let v = value === null ? 0 : value;
    if (v == 1) {
        return "女队";
    } else if (v == 2) {
        return "打星";
    }
    return "常规";
}
const award_title = ["排名", "学校", "队名", "选手", "教练", "账号", "备注"];
function MakeLineTeam(team) {
    let remark = "";
    if ("remark" in team) {
        remark = team.remark;
        team = team.team;
    }
    let line = [
        team.rank,
        team.school,
        team.nick,
        team.tmember,
        team.coach,
        team.user_id,
        remark,
    ];
    return line;
}
function ExportAwardCsv() {
    let res = [];
    res.push(`获奖名单-${contest_title}`);
    for (let i = 0; i < award_to_export.length; i++) {
        let award_name = award_to_export[i];
        res.push(`\n${award_name}\n`);
        res.push(award_title.join(",") + "\n");
        for (let i = 0; i < map_award[award_name].length; i++) {
            res.push(MakeLineTeam(map_award[award_name][i]).join(",") + "\n");
        }
    }
    let team_all = [];
    var blob = new Blob(["\uFEFF" + res.join("")], {
        type: "text/plain;charset=utf-8",
    });
    var downloadLink = document.createElement("a");
    downloadLink.href = URL.createObjectURL(blob);
    downloadLink.download = `获奖名单-cid${page_cid}.csv`;
    downloadLink.click();
}

async function ExportAwardXlsx() {
    const alignment_title = {
        vertical: "middle",
        horizontal: "center",
        wrapText: true,
    };
    const columns_width = [
        { width: 5 },
        { width: 16 },
        { width: 16 },
        { width: 25 },
        { width: 8 },
        { width: 8 },
        { width: 9 },
        { width: 22 },
    ];
    // 设置字体为加粗，大小为16，居中对齐
    const title_row_style = {
        font: { bold: true, size: 16 },
        alignment: { horizontal: "center" },
    };
    const header_row_style = { font: { bold: true } };

    var wb = new ExcelJS.Workbook();
    wb.creator = "Me";
    wb.lastModifiedBy = "Me";
    wb.created = new Date();
    wb.modified = new Date();

    let allAwardsSplitSheetData = [];
    let allAwardsMergedSheetData = [];
    let ws_data_list = [];
    for (let i = 0; i < award_to_export.length; i++) {
        let award_name = award_to_export[i];
        let ws_data = [];
        ws_data.push([`获奖名单-${contest_title}`]);
        ws_data.push([award_name]);
        ws_data.push(award_title);
        for (let i = 0; i < map_award[award_name].length; i++) {
            let team_line = MakeLineTeam(map_award[award_name][i]);
            ws_data.push(team_line);
        }
        // 分项数据
        allAwardsSplitSheetData.push(ws_data);
        // 合并数据
        for (let j = i == 0 ? 2 : 3; j < ws_data.length; j++) {
            allAwardsMergedSheetData.push(
                ws_data[j].concat([j == 2 ? "奖项" : award_name])
            );
        }
        ws_data_list.push(ws_data);
    }
    // 添加所有奖项-分项 sheet
    let allAwardsSplitSheet = wb.addWorksheet("所有奖项-分项", {
        views: [{ xSplit: 1, ySplit: 1 }],
    });
    allAwardsSplitSheet.columns = columns_width;
    let allAwardsSplitSheetTitle = allAwardsSplitSheet.addRow([
        `获奖名单-${contest_title}`,
    ]);
    allAwardsSplitSheetTitle.height = 40;
    allAwardsSplitSheetTitle.eachCell((cell) => {
        Object.assign(cell.style, title_row_style);
    });
    allAwardsSplitSheetTitle.alignment = alignment_title;
    allAwardsSplitSheetData.forEach((ws_data, index) => {
        for (let i = 1; i < ws_data.length; i++) {
            let row = ws_data[i];
            let excelRow = allAwardsSplitSheet.addRow(row);
            if (i == 1) {
                excelRow.eachCell((cell) => {
                    Object.assign(cell.style, title_row_style);
                });
                excelRow.alignment = alignment_title;
                let rowNumber = excelRow.number;
                let mergeRange = `A${rowNumber}:G${rowNumber}`;
                allAwardsSplitSheet.mergeCells(mergeRange);
                excelRow.height = 30;
            } else {
                if (i == 2) {
                    excelRow.eachCell((cell) => {
                        Object.assign(cell.style, header_row_style);
                    });
                }
                excelRow.eachCell((cell) => {
                    cell.border = {
                        top: { style: "thin" },
                        left: { style: "thin" },
                        bottom: { style: "thin" },
                        right: { style: "thin" },
                    };
                });
            }
        }
    });
    allAwardsSplitSheet.mergeCells("A1:G1");

    // 添加所有奖项-合并 sheet
    let allAwardsMergedSheet = wb.addWorksheet("所有奖项-合并", {
        views: [{ xSplit: 1, ySplit: 1 }],
    });
    allAwardsMergedSheet.columns = columns_width;
    let allAwardsMergedSheetTitle = allAwardsMergedSheet.addRow([
        `获奖名单-${contest_title}`,
    ]);
    allAwardsMergedSheetTitle.height = 40;
    allAwardsMergedSheetTitle.eachCell((cell) => {
        Object.assign(cell.style, title_row_style);
    });
    allAwardsMergedSheetTitle.alignment = alignment_title;
    allAwardsMergedSheetData.forEach((row, index) => {
        let excelRow = allAwardsMergedSheet.addRow(row);
        if (index == 0) {
            excelRow.eachCell((cell) => {
                Object.assign(cell.style, header_row_style);
            });
        }
        excelRow.eachCell((cell) => {
            cell.border = {
                top: { style: "thin" },
                left: { style: "thin" },
                bottom: { style: "thin" },
                right: { style: "thin" },
            };
            cell.alignment = "right";
        });
    });
    allAwardsMergedSheet.mergeCells("A1:H1");
    // 各奖项sheet
    for (let i = 0; i < ws_data_list.length; i++) {
        let award_name = award_to_export[i];
        let ws_data = ws_data_list[i];
        let ws = wb.addWorksheet(award_name.replace(/[:\\/?*[\]]/g, ""));
        ws_data.forEach((row, index) => {
            let excelRow = ws.addRow(row);
            if (index == 0) {
                excelRow.height = 40;
            } else if (index == 1) {
                excelRow.height = 30;
            }
            if (index < 2) {
                excelRow.eachCell((cell) => {
                    Object.assign(cell.style, title_row_style);
                });
                excelRow.alignment = alignment_title;
            } else {
                if (index == 2) {
                    excelRow.eachCell((cell) => {
                        Object.assign(cell.style, header_row_style);
                    });
                }
                excelRow.eachCell((cell) => {
                    cell.border = {
                        top: { style: "thin" },
                        left: { style: "thin" },
                        bottom: { style: "thin" },
                        right: { style: "thin" },
                    };
                });
            }
        });
        ws.mergeCells("A1:G1");
        ws.mergeCells("A2:G2");
        ws.columns = columns_width;
    }

    // 完整排名sheet
    let allRankSheet = wb.addWorksheet("完整排名");
    allRankSheet.columns = [
        { width: 5 },
        { width: 20 },
        { width: 20 },
        { width: 25 },
        { width: 6 },
        { width: 6 },
        { width: 6 },
        { width: 6 },
        { width: 6 },
        { width: 8 },
    ];
    let allRankSheetTitle = allRankSheet.addRow([`完整排名-${contest_title}`]);
    allRankSheetTitle.height = 40;
    allRankSheetTitle.eachCell((cell) => {
        Object.assign(cell.style, title_row_style);
    });
    allRankSheetTitle.alignment = alignment_title;
    let excelRow = allRankSheet.addRow([
        "排名",
        "学校",
        "队名",
        "选手",
        "教练",
        "类型",
        "题数",
        "罚时",
        "校排",
        "队号",
        "备注",
    ]);
    excelRow.eachCell((cell) => {
        Object.assign(cell.style, header_row_style);
        cell.border = {
            top: { style: "thin" },
            left: { style: "thin" },
            bottom: { style: "thin" },
            right: { style: "thin" },
        };
    });
    data_show.forEach((row, index) => {
        excelRow = allRankSheet.addRow([
            row.rank,
            row.school,
            row.nick,
            row.tmember,
            row.coach,
            TkindAwardFormatter(row.tkind),
            row.solved,
            row.penalty,
            isNaN(parseInt(row.school_rank)) ? "" : row.school_rank,
            row.user_id,
            "",
        ]);
        // excelRow.eachCell(cell => {
        //     Object.assign(cell.style, header_row_style);
        // });
        excelRow.eachCell((cell) => {
            cell.border = {
                top: { style: "thin" },
                left: { style: "thin" },
                bottom: { style: "thin" },
                right: { style: "thin" },
            };
        });
    });
    allRankSheet.mergeCells("A1:K1");

    // 导出文件
    const buffer = await wb.xlsx.writeBuffer();
    const blob = new Blob([buffer], {
        type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `获奖名单-cid${page_cid}.xlsx`;
    a.click();
}

let award_confirm_info = `
<strong>务必确认：</strong><br/>
<span class="text-danger">1. 比赛是否已结束</span><br/>
<span class="text-danger">2. <a href="/${page_module}/contest/status?cid=${page_cid}">评测队列</a>是否已全部完成</span><br/>
<span class="text-danger">3. <strong class="text-info">确认后，刷新本页</strong>确保获取最新数据，再导出最终结果</span><br/><br/>
确认现在导出？
`;
$("#export_award_csv_btn").click(function () {
    alertify.confirm(
        "确认",
        award_confirm_info,
        function () {
            ExportAwardCsv();
        },
        function () { }
    );
});
$("#export_award_xlsx_btn").click(function () {
    alertify.confirm(
        "确认",
        award_confirm_info,
        function () {
            ExportAwardXlsx();
        },
        function () { }
    );
});

// **************************************************
// 设置 custom 配置，用于增加一个自定义内容的子表
// 定义模板内容
const templateContent = [ {
        "title": "序号",
        "template": "{{index}}",
        "width": 50,
    }, {
        "title": "排名",
        "template": "{{rank}}",
        "width": 50,
    }, {
        "title": "学校",
        "template": "{{school}}",
        "width": 50,
    }, {
        "title": "成员",
        "template": "{{member}}",
        "width": 50,
    }, {
        "title": "类型",
        "template": "{{tkind}}",
        "width": 50,
    }, {
        "title": "题数",
        "template": "{{solved}}",
        "width": 50,
    }, {
        "title": "罚时",
        "template": "{{penalty}}",
        "width": 50,
    }, {
        "title": "奖项",
        "template": "{{award}}",
        "width": 50,
    }, {
        "title": "编号",
        "template": "XCPC-{{year}}-{{month}}-{{day}}-{{rank}}-{{schoolrank}}",
        "width": 50,
    }, {
        "title": "开始时间",
        "template": "START: {{start_h}}:{{start_m}}:{{start_s}}",
        "width": 50,
    }
];

// 点击 help 图标时显示说明文字并提供下载模板按钮
document.getElementById('help_icon_span').addEventListener('click', function() {
    document.getElementById('template_code').innerText = JSON.stringify(templateContent, null, 4);
    // 显示 modal
    $('#helpModal').modal('show');

    // 添加复制功能
    const copyButton = $('#copy_template');
    if (copyButton.length) {
        copyButton.unbind('click').on('click', async ()=>{
            const codeElement = document.getElementById('template_code').innerText;
            console.log(121, codeElement)
            if(await ClipboardWrite(codeElement)) {
                alertify.success('Template Copied');
            } else {
                alertify.error('Template Copy Failed');
            }
        });
    }
});

// 点击 Custom 按钮时显示 textarea 输入框
document.getElementById('self_define_template').addEventListener('click', function() {
    alertify.prompt('自定义 JSON', '请输入自定义的 JSON:', '',
        function(evt, value) {
            try {
                const json = JSON.parse(value);
                const cid = 'your_contest_id'; // 替换为实际的 contest_id
                csg.store(`award_custom#cid${cid}`, json);
                alertify.success('JSON 格式正确，已保存。');
            } catch (e) {
                alertify.error('JSON 格式错误，请检查后重新输入。');
            }
        },
        function() {
            alertify.error('取消输入。');
        }
    );
});
