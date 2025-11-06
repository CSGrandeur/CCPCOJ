<div class="row">
    <div class="col-12 col-lg-8">
        <table
            class="bootstraptable_refresh_local"
            id="contest_problem_table"
            data-toggle="table"
            data-url="/{$module}/{$controller}/problemset_ajax?cid={$contest['contest_id']}"
            data-pagination="false"
            data-side-pagination="client"
            data-method="get"
            data-search="false"
            data-classes="table table-no-bordered table-hover table-striped"
        >
            <thead>
            <tr>
                <th data-field="ac" data-align="center" data-valign="middle"  data-sortable="false" data-width="30"></th>
                <th data-field="problem_id_show" data-align="right" data-valign="middle"  data-sortable="false" data-width="70">题号<span class="en-text">ID</span></span></th>
                <th data-field="title" data-align="left" data-valign="middle"  data-sortable="false" data-formatter="FormatterContestProTitle" data-width="*">标题<span class="en-text">Title</span></th>
                <th data-field="accepted" data-align="right" data-valign="middle"  data-sortable="false" data-width="80" data-formatter="FormatterProAc" title="AC提交总数（非AC队伍数）/ AC Total (Not AC Team Count)">通过<span class="en-text">AC</span></th>
                <th data-field="submit" data-align="right" data-valign="middle"  data-sortable="false" data-width="100" data-formatter="FormatterProSubmit" title="提交总数（非提交队伍数）/ Submit Total (Not Team Count)">提交<span class="en-text">Submit</span></th>
                <th data-field="color" data-align="right" data-valign="middle"  data-sortable="false" data-width="32" data-formatter="FormatterProBal">气球<span class="en-text">Bal</span></th>
            </tr>
            </thead>
        </table>
    </div>
    
    <!-- 公告栏模板 -->
    {include file="../../csgoj/view/contest/contest_notification" /}
</div>
<input id="pro_page_info" type="hidden" module="{$module}" controller="{$controller}" cid="{$contest['contest_id']}">
<style>
.contest_problem_title {
    display: inline-block;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
</style>
{css href="__STATIC__/csgoj/contest_problemset.js" /}
{include file="../../csgoj/view/public/mathjax_js" /}