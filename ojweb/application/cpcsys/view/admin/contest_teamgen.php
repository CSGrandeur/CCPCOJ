<div class="page-header">
    <h1>Team Generator
        <button type="button" class="btn btn-info" data-toggle="collapse" data-target="#teamgen_help_div" aria-expanded="false" aria-controls="navbar">
            Help
        </button>
    </h1>
</div>
<div class="container">
    <article id="teamgen_help_div" class="md_display_div alert alert-info collapse">
        <p>每行一个队伍，该队伍信息由制表符<code>\t</code>隔开（从Excel文档复制即可）。信息从左到右依次为：</p>
        <p>队号（纯数字）、队名、学校、队员、教练、房间（如果有的话）、队伍类型（0普通/1女队/2打星）、预设密码，例如：</p>
        <p><code>001[\t]XX大学一队[\t]XX大学[\t]队员一、队员二、队员三[\t]教练名[\t]机房A[\t]0[\t]123456</code></p>
        <p>队号、密码、房间可为空，由系统自动生成，但“<code>\t</code>”分隔符必须有，信息是以第几个分隔符来对应的。</p>
        <p>比如 <code>[\t][\t]测试[\t][\t]333[\t]</code>，即会自动生成一个这样的队伍：</p>
        <table class="md_display_div">
            <thead><tr><th>Team ID</th><th>Team Name</th><th>School</th><th>Member</th><th>Coach</th><th>Room</th><th>Team Kind</th><th>Password</th></tr></thead>
            <tbody>
            <tr><td>team0001</td><td>null</td><td>测试</td><td>null</td><td>333</td><td></td><td>0</td><td>V395JKQB</td></tr>
            </tbody>
        </table>
        <p>与报名系统导出的表格对应，可从excel表格复制对应的列直接粘贴在这里生成队伍。</p>
        <p><strong>如果只想预生成 n 个随机密码的“空”队伍，仅输入 <code>n seed(随机种子，可选)</code> 即可，例如 <code>100</code> 或 <code>100 1024</code></strong></p>
    </article>
    
    {if $contestStatus != 2}
    <form id="contest_teamgen_form" method='post' action="__CPC__/admin/contest_teamgen_ajax?cid={$contest['contest_id']}">
        <div class="form-group">
            <label for="reset_team">Regenerate All Teams：</label>
            <input type="checkbox" id="reset_team" name='reset_team' class="switch_ctr">
        </div>
        <div class="form-group">
            <label for="team_description">Team Description: </label>
            <textarea id="team_description" class="form-control" placeholder="Description..." rows="20" cols="50" name="team_description" ></textarea>
        </div>
        <br/>
        <button type="submit" id="submit_button" class="btn btn-primary">Generate!</button>
    </form>
    {else /}
    <h3 class='text-danger'>Contest ended, team generation not allowed.</h3>
    {/if}

<script type="text/javascript">
    TextAllowTab('team_description');
    function CheckTeamData() {
        // 简要检查队伍数据
        let team_description = $('#team_description').val();
        let team_list = team_description.split('\n');
        for (let i = 0; i < team_list.length; i++) {
            let line = team_list[i].trim();
            if (line === '') continue;
            let elements = line.split('\t');
            let team_id = elements[0].trim();
            if (!/^[a-zA-Z0-9]+$/.test(team_id)) {
                alertify.alert("格式错误", `队伍ID只能包含数字和字母. 错误行： <br/>${line}`);
                return false;
            }
        }
        return true;
    }
    $(document).ready(function()
    {
        $('.switch_ctr').each(function() {
            var switch_ctr = $(this);
            var switch_name = switch_ctr.attr('name');
            switch_ctr.bootstrapSwitch();
            if (localStorage.getItem(switch_name)) {
                var switch_cookie = $.trim(localStorage.getItem(switch_name));
                if (switch_cookie == 'true') {
					if(switch_name === 'reset_team') {
						alertify.alert('Be careful to clear all teams to regenerate!<br/>"Off" to directly add teams; "On" to clear before add.')
					}
                    switch_ctr.bootstrapSwitch('state', true, true);
                }
                else {
                    switch_ctr.bootstrapSwitch('state', false, true);
                }
            }
            switch_ctr.unbind('switchChange.bootstrapSwitch').on('switchChange.bootstrapSwitch', function(event, state) {
                if(state == true) {
					if($(this).attr('name') === 'reset_team') {
						alertify.alert('Be careful to clear all teams to regenerate!<br/>Off to directly add teams; On to clear all teams before add.')
					}
                    localStorage.setItem(switch_name, 'true');
                }
                else{
                    localStorage.setItem(switch_name, 'false');
                }
            });
        });
        $('#contest_teamgen_form').validate({
            rules:{
                team_description: {
                    maxlength: 65536
                }
            },
            submitHandler: function(form) {
                if(!CheckTeamData()) {
                    return false;
                }
                $(form).ajaxSubmit(
                    {
                        success: function(ret)
                        {
                            var submit_button = $('#submit_button');
                            if(ret["code"] == 1)
                            {
                                alertify.alert(ret['msg']);
                                button_delay(submit_button, 5, 'Submit');
                                $('#teamgen_table').bootstrapTable('load', ret['data']['rows']);
                            }
                            else
                            {
                                alertify.alert(ret['msg']);
                                button_delay(submit_button, 3, 'Submit');
                            }
                            return false;
                        }
                    });
                return false;
            }
        });
    });
//    $(window).keydown(function(e) {
//        if (e.keyCode == 83 && e.ctrlKey) {
//            e.preventDefault();
//            var a=document.createEvent("MouseEvents");
//            a.initEvent("click", true, true);
//            $('#submit_button')[0].dispatchEvent(a);
//        }
//    });
</script>

<div id="teamgen_toolbar">
    <button type="button" class="btn btn-primary" id="export_teamgen_pageteam_btn">
        <span class="glyphicon glyphicon-download-alt" aria-hidden="true"></span>
        XLSX
    </button>
</div>
<table
    id="teamgen_table"
    data-toggle="table"
    data-buttons-align="left"
    data-sort-name="team_id"
    data-sort-order="asc"
    data-show-export="true"
    data-unique-id="team_id"
    data-url="__CPC__/admin/teamgen_list_ajax?cid={$contest['contest_id']}&ttype=0"
    data-toolbar="#teamgen_toolbar"
    data-toolbar-align="right"
    data-pagination="false"
    data-method="get"
    data-export-types="['excel', 'csv', 'json', 'png']"
    data-export-options='{"fileName": "Team_Generated"}'
>
    <thead>
    <tr>
        <th data-field="team_id" data-align="center" data-valign="middle" data-sortable="true" data-width="55">Team ID</th>
        <th data-field="name" data-align="left" data-valign="middle" data-width="160" >Team Name</th>
        <th data-field="school" data-align="center" data-valign="middle"  data-width="200">School</th>
        <th data-field="tmember" data-align="center" data-valign="middle" >Member</th>
        <th data-field="coach" data-align="center" data-valign="middle"  data-width="150">Coach</th>
        <th data-field="room" data-align="center" data-valign="middle"  >Room</th>
        <th data-field="tkind" data-align="center" data-valign="middle"  >Tkind</th>
        <th data-field="password" data-align="center" data-valign="middle"  data-width="60" >Password</th>
        <th data-field="delete" data-align="center" data-valign="middle"  data-width="60" data-formatter="FormatterDel">Del(Dbl Click)</th>
    </tr>
    </thead>
</table>
</div>
<input type="hidden" id="page_info" cid="{$contest['contest_id']}" ctitle="{$contest['title']|htmlspecialchars}">

{include file="../../csgoj/view/public/js_exceljs" /}

<script>
let page_info = $('#page_info');
let cid = page_info.attr('cid');
let ctitle = page_info.attr('ctitle');
let teamgen_table = $('#teamgen_table');
let delete_infoed = false;
function FormatterDel(value, row, index, field) {
    return "<button class='btn btn-danger'>Delete</button>";
}
teamgen_table.on('click-cell.bs.table', function(e, field, td, row){
    if(field == 'delete') {
        if(!delete_infoed) {
            alertify.message("Double click to delete.")
            delete_infoed = true;
        }
        
    }
});
teamgen_table.on('dbl-click-cell.bs.table', function(e, field, td, row){
    if(field == 'delete') {
        $.post('team_del_ajax?cid=' + cid, {'team_id': row.team_id}, function(ret) {
            if(ret.code == 1) {
                teamgen_table.bootstrapTable('removeByUniqueId', row.team_id);
                alertify.success(ret.msg);
            } else {
                alertify.error(ret.msg);
            }
        });
    }
});

function SheetSimple(team_list, ctitle, worksheet) {
    const per_subtable = 15;
    const per_page = per_subtable * 2;
    const font_size = 14;
    // 简化密码表
    worksheet.columns = [
        // { width: 8 },
        { width: 16 },
        { width: 25 },
        { width: 5 }
    ];
    worksheet.columns = [...worksheet.columns, ...worksheet.columns];

    // 添加数据
    let totalRows = Math.ceil(team_list.length / per_page);  // 总页数
    for (let i = 0; i < team_list.length; i++) {
        let team = team_list[i];
        let rowNumber = i % per_page % per_subtable + 4;  // 当前行号
        let columnOffset = i % per_page < per_subtable ? 0 : 3;  // 列偏移量
        let currentPage = Math.floor(i / per_page) + 1;  // 当前页数
        
        let rowOffset = Math.floor(i / per_page) * (per_subtable + 3)

        // 如果是新的一页，更新页码
        if (i % per_page === 0) {
            
            // 前两行横向合并单元格并居中
            let titleRow = worksheet.addRow([`${ctitle}`]);
            worksheet.mergeCells(`A${titleRow.number}:E${titleRow.number}`);
            titleRow.height = 60;
            titleRow.alignment = { wrapText: true, vertical: 'middle', horizontal: 'center'  }; 
            titleRow.font = {size: font_size};

            let headerRow = worksheet.addRow([`账号表 ${currentPage}/${totalRows}`]);
            worksheet.mergeCells(`A${headerRow.number}:E${headerRow.number}`);
            headerRow.alignment = { horizontal: 'center' };
            headerRow.font = {size: font_size};
            
            // 添加表头
            // let tableHeaderRow = worksheet.addRow(['序号', '账号', '密码', '', '序号', '账号', '密码']);
            let tableHeaderRow = worksheet.addRow(['账号', '密码', '', '账号', '密码']);
            tableHeaderRow.font = { bold: true, size: font_size };
            tableHeaderRow.eachCell(cell => cell.border = {
                top: { style: 'thin' },
                left: { style: 'thin' },
                bottom: { style: 'thin' },
                right: { style: 'thin' }
            });
            tableHeaderRow.font = {size: font_size};
        }

        // 添加数据
        let row = worksheet.getRow(rowOffset + rowNumber);
        // row.getCell(columnOffset + 1).value = i + 1;
        row.getCell(columnOffset + 1).value = team.team_id;
        row.getCell(columnOffset + 2).value = team.password;
        if(columnOffset == 0) {
            row.getCell(columnOffset + 3).value = '';
        }

        // 设置行高和文本自动换行
        row.height = 38;  // 设置行高
        row.alignment = { wrapText: true, vertical: 'middle', horizontal: 'center'  };  // 设置文本自动换行

        // 设置单元格边框
        for (let j = 1; j <= 5; j++) {
            if(j == 3) {
                continue
            }
            let cell = row.getCell(j);
            cell.font = {};
            if(j == 2 || j == 5) {
                cell.font = { name: 'Courier New', bold: true }; 
            }
            cell.font.size = font_size;
            cell.border = {
                top: { style: 'thin' },
                left: { style: 'thin' },
                bottom: { style: 'thin' },
                right: { style: 'thin' }
            };
        }
    }
}

function SheetPage(team_list, ctitle, worksheet) {
    const per_subtable = 15;
    const per_page = per_subtable * 2;
    // 单页密码
    worksheet.columns = [
        // { width: 6 },
        { width: 15 },
        { width: 30 },
        { width: 30 },
        { width: 15 },
        { width: 15 },
        { width: 5 }
    ];

    for (let i = 0; i < team_list.length; i++) {
        // 添加数据
        let row = worksheet.getRow(i + 1);
        let team = team_list[i];
        // row.getCell(1).value = `${i + 1}`;
        row.getCell(1).value = ` | ${team.team_id}`;
        row.getCell(2).value = ` | ${team.school}`;
        row.getCell(3).value = ` | ${team.name}`;
        row.getCell(4).value = ` | ${team.room}`;
        row.getCell(5).value = ` | ${team.password}`;
        row.getCell(5).font = { name: 'Courier New', bold: true }; 
        row.height = 800;  // 设置行高
        row.alignment = { wrapText: true, vertical: 'top', horizontal: 'left'  }; 
    }
}
function SheetFull(team_list, ctitle, worksheet) {
    const per_subtable = 15;
    const per_page = per_subtable * 2;
    // 完整数据表
    worksheet.columns = [
        // { width: 6 },
        { width: 12 },
        { width: 20 },
        { width: 25 },
        { width: 10 },
        { width: 15 },
        { width: 5 }
    ];
    // 添加表头
    let tableHeaderRow = worksheet.addRow(['账号', '学校', '队名', '分区', '密码']);
    tableHeaderRow.font = { bold: true };
    tableHeaderRow.eachCell(cell => cell.border = {
        top: { style: 'thin' },
        left: { style: 'thin' },
        bottom: { style: 'thin' },
        right: { style: 'thin' }
    });
    for (let i = 0; i < team_list.length; i++) {
        let team = team_list[i];
        let row = worksheet.getRow(i + 2);
        // row.getCell(1).value = i + 1;
        row.getCell(1).value = team.team_id;
        row.getCell(2).value = team.school;
        row.getCell(3).value = team.name;
        row.getCell(4).value = team.room;
        row.getCell(5).value = team.password;
        row.getCell(5).font = { name: 'Courier New', bold: true }; 
        row.height = 42; 
        row.alignment = { wrapText: true };
        for (let j = 1; j <= 5; j++) {
            row.getCell(j).border = {
                top: { style: 'thin' },
                left: { style: 'thin' },
                bottom: { style: 'thin' },
                right: { style: 'thin' }
            };
        }
    }
}
async function ExportTeamgenTable(team_list, ctitle) {
    const workbook = new ExcelJS.Workbook();
    SheetSimple(team_list, ctitle, workbook.addWorksheet('密码条-表格'));
    SheetPage(team_list, ctitle, workbook.addWorksheet('密码条-分页（横向打印）'));
    SheetFull(team_list, ctitle, workbook.addWorksheet('完整数据'));

    // 导出文件
    const buffer = await workbook.xlsx.writeBuffer();
    const blob = new Blob([buffer], {type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `账号表-cid${cid}.xlsx`;
    a.click();
}


$('#export_teamgen_pageteam_btn').click(function() {
    let team_list = teamgen_table.bootstrapTable('getData', {includeHiddenRows: true});
    ExportTeamgenTable(team_list, ctitle);
});
</script>
<style type="text/css">
    #teamgen_table
    {
        font-family: 'Simsun', 'Microsoft Yahei Mono', 'Lato', "PingFang SC", "Microsoft YaHei", sans-serif;
    }
</style>