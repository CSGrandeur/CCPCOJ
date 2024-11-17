<div class="page-header" style="display:flex;">
    <div style="display:flex; flex-direction: column;">
        <h1 style="margin-top: 0;">获奖数据/award</h1>
        <strong class="text-danger danger-tip">务必确认：比赛是否已结束</strong>
        <strong class="text-danger danger-tip">务必确认：<a href="/{$module}/contest/status?cid=${contest['contest_id']}">评测队列</a>是否已完成</strong>
    </div>
    <div style="margin-left: 10px;">
        <article id="teamgen_help_div" class="alert alert-info">
            <p>当正式参赛女队（3名队员皆为女生）数目大于等于3时，可以设置最佳女队奖，排名最高且获得铜奖或以上奖项的正式参赛女队获得。</p>
            <p>可以设置顽强拼搏奖，未获得金奖、银奖或铜奖的正式队伍中最晚解出题目的1或2支参赛队获得顽强拼搏奖。</p>
            <p>默认发奖模式：金奖按比例向上取整，金银按比例向上取整后减金奖个数，金银铜按比例向上取整后减去金银个数。</p>
        </article>
    </div>
</div>
<div id="award_toobar">
    <div class="form-inline" role="form">
        <div class="form-group">
            <div style="display:flex;">
                <button type="button" class="btn btn-success" id="self_define_template">
                    <span class="glyphicon glyphicon-pencil" aria-hidden="true"></span>
                    Custom
                </button>
                <div style="display:flex; flex-direction: column; margin-left: 5px;">
                    <span class="glyphicon glyphicon-question-sign" title="help" id="help_icon_span" style="cursor: pointer;"></span>
                    <span class="glyphicon glyphicon-question-sign" title="help"></span>
                </div>
            </div>
        </div>
        <div class="form-group">
            &nbsp;&nbsp;
            <label for="one_two_three" title="显示为一二三等奖，而不是金银铜">一二三: </label>
            <input type="checkbox" id="switch_one_two_three" data-size="small" name="one_two_three">
            &nbsp;&nbsp;
            <label for="with_star_team">包含打星: </label>
            <input type="checkbox" id="switch_with_star_team" data-size="small" name="with_star_team">
            &nbsp;&nbsp;
            <label for="all_team_based" title="否则以过题队为基数">以总数为基数: </label>
            <input type="checkbox" id="switch_all_team_based" data-size="small" name="all_team_based">
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
        data-export-options='{"fileName":"{$contest[\"contest_id\"]}-{$contest[\"title\"]}-获奖名单"}'>
        <thead>
            <tr>
                <th data-field="rank" data-align="center" data-valign="middle" data-sortable="false" data-width="60">排名</th>
                <th data-field="award" data-align="center" data-valign="middle" data-sortable="false" data-width="100" data-cell-style="CellStyleAward">获奖</th>
                <th data-field="nick" data-align="left" data-valign="middle" data-sortable="false" data-cell-style="CellStyleName">队名</th>
                <th data-field="tkind" data-align="left" data-valign="middle" data-sortable="false" data-width="50" data-formatter="TkindAwardFormatter">类型</th>
                <th data-field="solved" data-align="center" data-valign="middle" data-sortable="false" data-width="60">解题</th>
                <th data-field="penalty" data-align="center" data-valign="middle" data-sortable="false" data-width="80">罚时</th>
                <th data-field="school" data-align="left" data-valign="middle" data-sortable="false" data-cell-style="schoolCellStyle">学校</th>
                <th data-field="tmember" data-align="left" data-valign="middle" data-sortable="false" data-cell-style="memberCellStyle">选手</th>
                <th data-field="coach" data-align="left" data-valign="middle" data-sortable="false" data-width="80" data-cell-style="coachCellStyle">教练</th>
                <th data-field="user_id" data-align="left" data-valign="middle" data-sortable="false" data-width="80">ID</th>
            </tr>
        </thead>
    </table>
</div>
<div class="modal fade" id="helpModal" tabindex="-1" role="dialog" aria-labelledby="helpModalLabel">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title" id="helpModalLabel">帮助</h4>
      </div>
      <div class="modal-body">
        <p>编辑配置json后提交，导出的获奖xlsx文件将增加根据该配置生成的子表，可用模板信息引用系统信息。</p>
        <table class="award_custom_explain_table">
            <tr><td>{{team_id}}:</td>  <td>队伍账号</td>    <td>{{year}}:</td>     <td>比赛开始年</td>  </tr>
            <tr><td>{{rank}}:</td>     <td>队伍排名</td>    <td>{{month}}:</td>    <td>比赛开始月</td> </tr>
            <tr><td>{{school}}:</td>   <td>队伍校排</td>    <td>{{day}}:</td>      <td>比赛开始日</td>  </tr>
            <tr><td>{{member}}:</td>   <td>成员姓名</td>    <td>{{start_h}}:</td>  <td>比赛开始时</td>  </tr>
            <tr><td>{{coach}}:</td>    <td>教练</td>        <td>{{start_m}}:</td>  <td>比赛开始分</td>  </tr>
            <tr><td>{{tkind}}:</td>    <td>队伍类型</td>    <td>{{start_s}}:</td>  <td>比赛开始秒</td>  </tr>
            <tr><td>{{solved}}:</td>   <td>解题数</td>      <td>{{end_h}}:</td>    <td>比赛结束时</td>  </tr>
            <tr><td>{{penalty}}:</td>  <td>罚时</td>        <td>{{end_m}}:</td>    <td>比赛结束分</td>  </tr>
            <tr><td>{{award}}:</td>    <td>获奖</td>        <td>{{end_s}}:</td>    <td>比赛结束秒</td>  </tr>
        </table>
        <p>参考模板：</p>
        <div style="position: relative;">
            <pre><code id="template_code">${JSON.stringify(templateContent, null, 4)}</code></pre>
            <button id="copy_template" class="btn btn-secondary" style="position: absolute; top: 0; right: 0;">复制 / Copy</button>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">关闭</button>
      </div>
    </div>
  </div>
</div>

{include file="../../csgoj/view/public/js_exceljs" /}

<input type="hidden" id="award_ratio"
    gold="<?php echo isset($ratio_gold) ? $ratio_gold : 10; ?>"
    silver="<?php echo isset($ratio_silver) ? $ratio_silver : 15; ?>"
    bronze="<?php echo isset($ratio_bronze) ? $ratio_bronze : 20; ?>"
    module="{$module}"
    cid="{$contest['contest_id']}"
    contest_title="{$contest['title']|htmlspecialchars}" />

{js href="__STATIC__/csgoj/rank_common.js" /}
{js href="__STATIC__/csgoj/rank_pub.js" /}
{js href="__STATIC__/csgoj/awardgen.js" /}
</script>
<style>
    .danger-tip {
        font-size: 16px;
        margin-top: 0;
    }
</style>