<div class="row">
    <div class="col-md-8 col-sm-8">
        <table
            class="bootstraptable_refresh_local"
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
                <th data-field="problem_id_show" data-align="center" data-valign="middle"  data-sortable="false" data-width="70">ID</th>
                <th data-field="title" data-align="left" data-valign="middle"  data-sortable="false" data-width="500" data-formatter="FormatterProTitle">Title</th>
                <th data-field="accepted" data-align="center" data-valign="middle"  data-sortable="false" data-width="80" data-formatter="FormatterProAc">AC</th>
                <th data-field="submit" data-align="right" data-valign="middle"  data-sortable="false" data-width="100">Submit</th>
            </tr>
            </thead>
        </table>
    </div>
    <div class="col-md-4 col-sm-4">
        <div class="panel panel-primary">
            <div class="panel-heading">
                <h3 class="panel-title">公告(Notification):
                {if $isContestAdmin || isset($proctorAdmin) && $proctorAdmin }
                    &nbsp;&nbsp;&nbsp;&nbsp;
                    <button class="btn btn-sm btn-default" data-toggle="modal" data-target="#page_modal">Change</button>
                {/if}
                </h3>
            </div>
            <div class="panel-body" style="min-height:400px; max-height:450px; overflow-y: auto;">
                <article class="md_display_div" id="contest_notification_div">
                {$contest['description']}
                </article>
            </div>
        </div>
    </div>
</div>
<input id="pro_page_info" type="hidden" module="{$module}" controller="{$controller}" cid="{$contest['contest_id']}">
<style>
.contest_problem_title {
    display: inline-block;
    width: 22vw;
    max-width: 530px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
</style>
{css href="__STATIC__/csgoj/contest_problemset.js" /}
{if $isContestAdmin || isset($proctorAdmin) && $proctorAdmin }
    {include file="../../csgoj/view/contest/change_notification" /}
{/if}
{include file="../../csgoj/view/public/mathjax_js" /}