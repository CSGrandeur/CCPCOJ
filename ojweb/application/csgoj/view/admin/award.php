<div class="page-header" style="display:flex;">
    <div style="display:flex; flex-direction: column;">
        <h1>获奖数据/award</h1>
        <strong class="text-danger danger-tip">务必确认：比赛已结束</strong>
        <strong class="text-danger danger-tip">务必确认：<a href="/{$module}/contest/status?cid=${contest['contest_id']}">评测队列</a>已完成</strong>
    </div>
    <div style="margin-left: 10px;">
        <article id="teamgen_help_div" class="alert alert-info">
            <p>当正式参赛女队（3名队员皆为女生）数目大于等于3时，可以设置最佳女队奖，排名最高且获得铜奖或以上奖项的正式参赛女队获得。</p>
            <p>可以设置顽强拼搏奖，未获得金奖、银奖或铜奖的正式队伍中最晚解出题目的1或2支参赛队获得顽强拼搏奖。</p>
            <p>默认发奖模式：金奖按比例向上取整，金银按比例向上取证后减金奖个数，金银铜按比例向上取整后减去金银个数。</p>
        </article>
    </div>
</div>
<div id="award_toobar">
    <div class="form-inline" role="form">
        <div class="form-group">
            &nbsp;&nbsp;
            <label for="one_two_three" title="显示为一二三等奖，而不是金银铜">一二三: </label>
            <input type="checkbox" id="switch_one_two_three" data-size="small" name="one_two_three" >
            &nbsp;&nbsp;
            <label for="with_star_team">包含打星: </label>
            <input type="checkbox" id="switch_with_star_team" data-size="small" name="with_star_team" >
            &nbsp;&nbsp;
            <label for="all_team_based" title="否则以过题队为基数">以总数为基数: </label>
            <input type="checkbox" id="switch_all_team_based" data-size="small" name="all_team_based" >
            &nbsp;&nbsp;
            <button type="button" class="btn btn-primary" id="export_award_csv_btn">
                <span class="glyphicon glyphicon-download-alt" aria-hidden="true"></span>
                CSV
            </button>
            <button type="button" class="btn btn-primary" id="export_award_xlsx_btn">
                <span class="glyphicon glyphicon-download-alt" aria-hidden="true"></span>
                XLSX
            </button>
        </div>
    </div>
</div>
<div id="award_table_div">
    <table
            id="award_table"
            data-toggle="table"
            data-side-pagination="client"
            data-striped="true"
            data-show-refresh="false"
            data-silent-sort="false"
            data-buttons-align="left"
            data-toolbar-align="right"
            data-sort-stable="true"
            data-show-export="true"
            data-toolbar="#award_toobar"
            data-export-types="['excel', 'csv', 'json', 'png']"
            data-export-options='{"fileName":"{$contest[\"contest_id\"]}-{$contest[\"title\"]}-获奖名单"}'
    >
        <thead>
        <tr>
            <th data-field="rank"    data-align="center" data-valign="middle"  data-sortable="false" data-width="60">排名</th>
            <th data-field="award"   data-align="center" data-valign="middle"  data-sortable="false" data-width="100" data-cell-style="CellStyleAward">获奖</th>
            <th data-field="nick"    data-align="left"   data-valign="middle"  data-sortable="false" data-cell-style="CellStyleName">队名</th>
            <th data-field="tkind"   data-align="left"   data-valign="middle"  data-sortable="false" data-width="50"  data-formatter="TkindAwardFormatter">类型</th>
            <th data-field="solved"  data-align="center" data-valign="middle"  data-sortable="false" data-width="60">解题</th>
            <th data-field="penalty" data-align="center" data-valign="middle"  data-sortable="false" data-width="80">罚时</th>
            <th data-field="school"  data-align="left"   data-valign="middle"  data-sortable="false" data-cell-style="schoolCellStyle">学校</th>
            <th data-field="tmember" data-align="left"   data-valign="middle"  data-sortable="false" data-cell-style="memberCellStyle">选手</th>
            <th data-field="coach"   data-align="left"   data-valign="middle"  data-sortable="false" data-width="80" data-cell-style="coachCellStyle">教练</th>
            <th data-field="user_id" data-align="left"   data-valign="middle"  data-sortable="false" data-width="80">ID</th>
        </tr>
        </thead>
    </table>
</div>

{include file="../../csgoj/view/public/js_exceljs" /}

<input type="hidden" id="award_ratio"
gold="<?php echo isset($ratio_gold) ? $ratio_gold : 10;?>"
silver="<?php echo isset($ratio_silver) ? $ratio_silver : 15;?>"
bronze="<?php echo isset($ratio_bronze) ? $ratio_bronze : 20;?>"
module="{$module}"
cid="{$contest['contest_id']}"
contest_title="{$contest['title']|htmlspecialchars}"
/>

{js href="__STATIC__/csgoj/rank_common.js" /}
{js href="__STATIC__/csgoj/rank_pub.js" /}
<script>
    let table_award_table = $('#award_table');
    let page_info = $('#award_ratio');
    let contest_title = page_info.attr('contest_title');
    let switch_with_star_team = $('#switch_with_star_team');
    let switch_all_team_based = $('#switch_all_team_based');
    let switch_one_two_three = $('#switch_one_two_three');
    let flag_with_star_team = false;
    let flag_all_team_based = false;
    let flag_one_two_three = false;
    let data_table = [];
    let data_show = [];
    let page_module = page_info.attr('module');
    let page_cid = page_info.attr('cid');

    let ratio_gold = parseInt(page_info.attr("gold"));
    let ratio_silver = parseInt(page_info.attr("silver"));
    let ratio_bronze = parseInt(page_info.attr("bronze"));

    let map_award = {};
    let award_to_export = [];
    if(ratio_gold < 100) {
        ratio_gold = ratio_gold / 100.0 - 0.0000001;
    }
    if(ratio_silver < 100) {
        ratio_silver = ratio_silver / 100.0;
    }
    if(ratio_bronze < 100) {
        ratio_bronze = ratio_bronze / 100.0;
    }

    let map_data_show = [];
    let ac_order = [];
    let team_struggle = null, team_best_girl = null;
    let rank_gold;
    let rank_silver;
    let rank_bronze;
    function CellStyleAward(value, row, index, field) {
        return {
            css: {
                'white-space': 'nowrap'
            }
        }
    }
    $(document).ready(function() {
        switch_with_star_team.bootstrapSwitch();
        switch_all_team_based.bootstrapSwitch();
        switch_one_two_three.bootstrapSwitch();
        $.get(
            
            '/' + page_module + '/contest/ranklist_ajax',{
                'cid': page_cid,
            },
            function(ret) {
                RankDataPreprocess(ret);
                for(let i = 0; i < ret.length; i ++) {
                    let tmp = {
                        "coach": ret[i]['coach'],
                        "nick": ret[i]['nick'],
                        // "penalty": ret[i]['penalty'],
                        "penalty": ret[i]['penalty_mi'],
                        "rank": ret[i]['rank'],
                        "school": ret[i]['school'],
                        "solved": parseInt(ret[i]['solved']),
                        "tkind": ret[i]['tkind'],
                        "tmember": ret[i]['tmember'],
                        "user_id": ret[i]['user_id'],
                        "fb": []
                    };
                    for(let pro in ret[i]) {
                        if(/^[A-Z]$/.test(pro) && ret[i][pro]?.ac != null) {
                            // 从rank数据获取的必定是有效AC，不会是AC之后重复提交的AC
                            ac_order.push({
                                'ac': ret[i][pro].ac,
                                'user_id': ret[i].user_id
                            });
                            if(ret[i][pro].pst == 3) {
                                tmp.fb.push(pro);
                            }
                        }
                    }
                    data_table.push(tmp);
                }
                ac_order.sort((a, b) => {
                    return a.ac == b.ac ? 0 : (a.ac < b.ac ? 1 : -1);
                })
                refresh_data_show();
            }
        );
    });
    function GetTeamStruggle() {
        team_struggle = null;
        for(let i = 0; i < ac_order.length; i ++) {
            if(ac_order[i].user_id in map_data_show && map_data_show[ac_order[i].user_id].rank > rank_bronze) {
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
        let rk_real = 0, rk_show = 0;
        let cnt_base = 0;
        let last_solved = -1, last_penalty = "";
        let last_solved_school = -1, last_penalty_school = "";
        map_data_show = {};
        team_best_girl = null;
        let team_girl_num = 0;
        let map_school_appear = {}, rk_school_real = 0, rk_school = 0;
        for(let i = 0; i < data_table.length; i ++) {
            if((data_table[i]['tkind'] < 2 || flag_with_star_team)) {   // 是否包含打星队
                let tmp = data_table[i]
                rk_real ++;
                if(!(data_table[i].school in map_school_appear)) {
                    rk_school_real ++;
                    if(tmp.solved != last_solved_school || tmp.penalty != last_penalty_school) {
                        rk_school = rk_school_real;
                        last_solved_school = tmp.solved;
                        last_penalty_school = tmp.penalty;
                    }
                    tmp.school_rank = rk_school
                    map_school_appear[data_table[i].school] = true;
                } else {
                    tmp.school_rank = 'NO SCHOOL RANK';
                }
                if(tmp['solved'] != last_solved || tmp['penalty'] != last_penalty) {
                    rk_show = rk_real;
                    last_solved = tmp['solved'];
                    last_penalty = tmp['penalty'];
                }
                tmp['rank'] = rk_show; 
                if(data_table[i]['solved'] > 0 || flag_all_team_based) { // 基数是否包含未过题队
                    cnt_base ++;
                }
                data_show.push(tmp);
                if(tmp.tkind == 1) {
                    team_girl_num ++;
                }
                map_data_show[tmp.user_id] = tmp;
            }
        }
        
        let award_rank = GetAwardRank(cnt_base, ratio_gold, ratio_silver, ratio_bronze);
        rank_gold = award_rank[0];
        rank_silver = award_rank[1];
        rank_bronze = award_rank[2];
        map_award = {};
        award_to_export = flag_one_two_three ? ["一等奖", "二等奖", "三等奖"] : ["金奖", "银奖", "铜奖"];
        award_to_export.push("最快解题奖");
        award_to_export.push("最佳女队/女生奖");
        award_to_export.push("顽强拼搏奖");
        award_to_export.push("冠亚季军");
        for(let i = 0; i < award_to_export.length; i ++) {
            map_award[award_to_export[i]] = [];
        }
        for(let i = 0; i < data_show.length; i ++) {
            if(data_show[i]['solved'] <= 0) {
                continue;
            }
            // 金银铜
            if(data_show[i]['rank'] <= rank_gold) {
                data_show[i].award = flag_one_two_three ? "一等奖" : "金奖";
            } else if(data_show[i]['rank'] <= rank_silver) {
                data_show[i].award = flag_one_two_three ? "二等奖" : "银奖";
            } else if(data_show[i]['rank'] <= rank_bronze) {
                data_show[i].award = flag_one_two_three ? "三等奖" : "铜奖";
            } else {
                data_show[i].award = "-"
            }
            if(data_show[i].award != "-") {
                map_award[data_show[i].award].push(data_show[i]);
            }
            // 冠亚季
            switch(data_show[i].school_rank) {
                case 1: data_show[i].award = "冠军学校<br/>" + data_show[i].award; map_award["冠亚季军"].push({'remark': '冠军学校', 'team': data_show[i]}); break;
                case 2: data_show[i].award = "亚军学校<br/>" + data_show[i].award; map_award["冠亚季军"].push({'remark': '亚军学校', 'team': data_show[i]}); break;
                case 3: data_show[i].award = "季军学校<br/>" + data_show[i].award; map_award["冠亚季军"].push({'remark': '季军学校', 'team': data_show[i]}); break;
            }
            // 最佳女队/女生
            if(team_girl_num >= 3 && team_best_girl === null && data_show[i].tkind == 1 && data_show[i].rank <= rank_bronze) {
                team_best_girl = data_show[i].user_id;
                data_show[i].award += typeof(data_show[i].tmember == 'string') && 
                    (data_show[i].tmember.includes("、") || 
                    data_show[i].tmember.includes(",") || 
                    data_show[i].tmember.includes("，")) ? '<br/>最佳女队奖' : '<br/>最佳女生奖';
                map_award["最佳女队/女生奖"].push(data_show[i]);
            }
            // 最快解题
            for(let j = 0; j < data_show[i].fb.length; j ++) {
                data_show[i].award += `<br/>最快解题奖(${data_show[i].fb[j]})`;
                map_award['最快解题奖'].push({
                    'remark': data_show[i].fb[j],
                    'team': data_show[i]
                })
            }
        }
        map_award['最快解题奖'].sort((a, b) => {
            return a.remark == b.remark ? 0 : (a.remark < b.remark ? -1 : 1);   // 26个字母范围内有效
        });
        // 顽强拼搏
        GetTeamStruggle();
        if(team_struggle !== null) {
            if(map_data_show[team_struggle].award == '-') {
                map_data_show[team_struggle].award = '';
            } else {
                map_data_show[team_struggle].award += '<br/>';
            }
            map_data_show[team_struggle].award += '顽强拼搏奖';
            map_award['顽强拼搏奖'].push(map_data_show[team_struggle]);

        }
        table_award_table.bootstrapTable('load', data_show);
    }
    switch_with_star_team.on('switchChange.bootstrapSwitch', function(event, state) {
        if(state == true) {
            flag_with_star_team = true;
        }
        else{
            flag_with_star_team = false;
        }
        refresh_data_show();
    });
    switch_all_team_based.on('switchChange.bootstrapSwitch', function(event, state) {
        if(state == true) {
            flag_all_team_based = true;
        }
        else{
            flag_all_team_based = false;
        }
        refresh_data_show();
    });
    switch_one_two_three.on('switchChange.bootstrapSwitch', function(event, state) {
        if(state == true) {
            flag_one_two_three = true;
        }
        else{
            flag_one_two_three = false;
        }
        refresh_data_show();
    });
    function TkindAwardFormatter(value, row, index)  {
        let v = value === null ? 0 : value;
        if(v == 1) {
            return "女队";
        } else if(v == 2) {
            return "打星";
        }
        return "常规";
    }
const award_title = ["排名", "学校", "队名", "选手", "教练", "账号", "备注"];
function MakeLineTeam(team) {
    let remark = '';
    if('remark' in team) {
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
        remark
    ];
    return line;
}
function ExportAwardCsv() {
    let res = [];
    res.push(`获奖名单-${contest_title}`);
    for(let i = 0; i < award_to_export.length; i ++) {
        let award_name = award_to_export[i]
        res.push(`\n${award_name}\n`);
        res.push(award_title.join(',') + '\n');
        for(let i = 0; i < map_award[award_name].length; i ++) {
            res.push(MakeLineTeam(map_award[award_name][i]).join(",") + "\n");
        }
    }
    let team_all = [];
    var blob = new Blob(['\uFEFF' + res.join('')], {
        type: 'text/plain;charset=utf-8',
    });
    var downloadLink = document.createElement("a");
    downloadLink.href = URL.createObjectURL(blob);
    downloadLink.download = `获奖名单-cid${page_cid}.csv`;
    downloadLink.click();
}


async function ExportAwardXlsx() {
    const alignment_title = { vertical: 'middle', horizontal: 'center', wrapText: true};
    const columns_width = [{width: 5}, {width: 16}, {width: 16}, {width: 25}, {width: 8}, {width: 8}, {width: 9}, {width: 22}];
    // 设置字体为加粗，大小为16，居中对齐
    const title_row_style = {font: {bold: true, size: 16}, alignment: {horizontal: 'center'}};
    const header_row_style = {font: {bold: true}};

    var wb = new ExcelJS.Workbook();
    wb.creator = 'Me';
    wb.lastModifiedBy = 'Me';
    wb.created = new Date();
    wb.modified = new Date();

    let allAwardsSplitSheetData = [];
    let allAwardsMergedSheetData = [];
    let ws_data_list = [];
    for(let i = 0; i < award_to_export.length; i ++) {
        let award_name = award_to_export[i];
        let ws_data = [];
        ws_data.push([`获奖名单-${contest_title}`]);
        ws_data.push([award_name]);
        ws_data.push(award_title);
        for(let i = 0; i < map_award[award_name].length; i ++) {
            let team_line = MakeLineTeam(map_award[award_name][i]);
            ws_data.push(team_line);
        }
        // 分项数据
        allAwardsSplitSheetData.push(ws_data);
        // 合并数据
        for(let j = i == 0 ? 2 : 3; j < ws_data.length; j ++) {
            allAwardsMergedSheetData.push(ws_data[j].concat([j == 2 ? "奖项" : award_name]));
        }
        ws_data_list.push(ws_data)
    }
    // 添加所有奖项-分项 sheet
    let allAwardsSplitSheet = wb.addWorksheet('所有奖项-分项', {views:[{xSplit: 1, ySplit:1}]});
    allAwardsSplitSheet.columns = columns_width;
    let allAwardsSplitSheetTitle = allAwardsSplitSheet.addRow([`获奖名单-${contest_title}`])
    allAwardsSplitSheetTitle.height = 40;
    allAwardsSplitSheetTitle.eachCell(cell => {
        Object.assign(cell.style, title_row_style);
    });
    allAwardsSplitSheetTitle.alignment = alignment_title;
    allAwardsSplitSheetData.forEach((ws_data, index) => {        
        for(let i = 1; i < ws_data.length; i ++) {
            let row = ws_data[i];
            let excelRow = allAwardsSplitSheet.addRow(row);
            if (i == 1) {
                excelRow.eachCell(cell => {
                    Object.assign(cell.style, title_row_style);
                });
                excelRow.alignment = alignment_title;
                let rowNumber = excelRow.number;
                let mergeRange = `A${rowNumber}:G${rowNumber}`;
                allAwardsSplitSheet.mergeCells(mergeRange);
                excelRow.height = 30;
            } else {
                if(i == 2) {
                    excelRow.eachCell(cell => {
                        Object.assign(cell.style, header_row_style);
                    });
                } 
                excelRow.eachCell(cell => {
                    cell.border = {top: {style:'thin'}, left: {style:'thin'}, bottom: {style:'thin'}, right: {style:'thin'}};
                });
            }
        }
    });
    allAwardsSplitSheet.mergeCells('A1:G1');

    // 添加所有奖项-合并 sheet
    let allAwardsMergedSheet = wb.addWorksheet('所有奖项-合并', {views:[{xSplit: 1, ySplit:1}]});
    allAwardsMergedSheet.columns = columns_width;
    let allAwardsMergedSheetTitle = allAwardsMergedSheet.addRow([`获奖名单-${contest_title}`])
    allAwardsMergedSheetTitle.height = 40;
    allAwardsMergedSheetTitle.eachCell(cell => {
        Object.assign(cell.style, title_row_style);
    });
    allAwardsMergedSheetTitle.alignment = alignment_title;
    allAwardsMergedSheetData.forEach((row, index) => {
        let excelRow = allAwardsMergedSheet.addRow(row);
        if(index == 0) {
            excelRow.eachCell(cell => {
                Object.assign(cell.style, header_row_style);
            });
        }
        excelRow.eachCell(cell => {
            cell.border = {top: {style:'thin'}, left: {style:'thin'}, bottom: {style:'thin'}, right: {style:'thin'}};
            cell.alignment = 'right'
        });
    });
    allAwardsMergedSheet.mergeCells('A1:H1');
    // 各奖项sheet
    for(let i = 0; i < ws_data_list.length; i ++) {
        let award_name = award_to_export[i];
        let ws_data = ws_data_list[i];
        let ws = wb.addWorksheet(award_name.replace(/[:\\/?*[\]]/g, ''));
        ws_data.forEach((row, index) => {
            let excelRow = ws.addRow(row);
            if(index == 0) {
                excelRow.height = 40;
            } else if(index == 1) {
                excelRow.height = 30;
            }
            if (index < 2) {
                excelRow.eachCell(cell => {
                    Object.assign(cell.style, title_row_style);
                });
                excelRow.alignment = alignment_title;
            } else {
                if(index == 2) {
                    excelRow.eachCell(cell => {
                        Object.assign(cell.style, header_row_style);
                    });
                }
                excelRow.eachCell(cell => {
                    cell.border = {top: {style:'thin'}, left: {style:'thin'}, bottom: {style:'thin'}, right: {style:'thin'}};
                });
            }
        });
        ws.mergeCells('A1:G1');
        ws.mergeCells('A2:G2');
        ws.columns = columns_width;
    }

    // 完整排名sheet
    let allRankSheet = wb.addWorksheet("完整排名");
    allRankSheet.columns = [{width: 5}, {width: 20}, {width: 20}, {width: 25}, {width: 6}, {width: 6}, {width: 6}, {width: 6}, {width: 6}, {width: 8}];
    let allRankSheetTitle = allRankSheet.addRow([`完整排名-${contest_title}`])
    allRankSheetTitle.height = 40;
    allRankSheetTitle.eachCell(cell => {
        Object.assign(cell.style, title_row_style);
    });
    allRankSheetTitle.alignment = alignment_title;
    let excelRow = allRankSheet.addRow(["排名", "学校", "队名", "选手", "教练", "类型", "题数", "罚时", "校排", "队号", "备注"]);
    excelRow.eachCell(cell => {
        Object.assign(cell.style, header_row_style);
        cell.border = {top: {style:'thin'}, left: {style:'thin'}, bottom: {style:'thin'}, right: {style:'thin'}};
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
            ""
        ])
        // excelRow.eachCell(cell => {
        //     Object.assign(cell.style, header_row_style);
        // });
        excelRow.eachCell(cell => {
            cell.border = {top: {style:'thin'}, left: {style:'thin'}, bottom: {style:'thin'}, right: {style:'thin'}};
        });
    });
    allRankSheet.mergeCells('A1:K1');

    // 导出文件
    const buffer = await wb.xlsx.writeBuffer();
    const blob = new Blob([buffer], {type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `获奖名单-cid${page_cid}.xlsx`;
    a.click();
}


let award_confirm_info = `
    <strong>务必确认：</strong><br/>
    <strong class="text-danger">1. 比赛已结束</strong><br/>
    <strong class="text-danger">2. <a href="/${page_module}/contest/status?cid=${page_cid}">评测队列</a>已全部完成</strong><br/>
    <strong class="text-danger">3. 确认后，刷新本页确保获取最新数据，再导出最终结果</strong><br/><br/>
    确认现在导出？
    `;
$('#export_award_csv_btn').click(function() {
    alertify.confirm("确认", award_confirm_info, function() {ExportAwardCsv();}, function(){});
    
});
$('#export_award_xlsx_btn').click(function() {
    alertify.confirm("确认", award_confirm_info, function() {ExportAwardXlsx();}, function(){});
});
</script>
<style>
    .danger-tip {
        font-size: 16px;
        margin-top: 0;
    }
</style>