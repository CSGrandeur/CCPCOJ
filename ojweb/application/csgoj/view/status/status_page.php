<div id="status_toolbar">
    <div class="form-inline fake-form" role="form">
        <button id="status_refresh" type="submit" class="btn btn-default" title="刷新 (Refresh)">
            <i class="glyphicon glyphicon-refresh icon-refresh"></i>
        </button>
        <button id="status_clear" type="submit" class="btn btn-default" title="清空 (Clear)">
            <i class="glyphicon glyphicon-trash"></i>
        </button>
        <button id="status_ok" type="submit" class="btn btn-default" title="过滤 (Filter)">
            <i class="glyphicon glyphicon-filter"></i>
        </button>
        <div class="form-group">
            <input id="problem_id_input" name="problem_id" class="form-control status_filter" type="text" value="{$search_problem_id}" style="max-width:100px;" placeholder="Problem ID">
        </div>
        <div class="form-group">
            <input id="user_id_input" name="user_id" class="form-control status_filter" type="text" value="{$search_user_id}" style="max-width:120px;" placeholder="User">
        </div>
        <div class="form-group">
            <input id="solution_id_input" name="solution_id" class="form-control status_filter" type="text" value="{$search_solution_id}" style="max-width:100px;" placeholder="RunID">
        </div>
        <div class="form-group">
        <span class="inline-dlang"><span>编程语言</span><span class="label-subtext">Language</span></span>
        </div>
        <div class="form-group">
            <select name="language" class="form-control status_filter">
                <option value="-1" selected="true">
                    All
                </option>
                {foreach($allowLanguage as $key=>$value)}
                <option value="{$key}">
                    {$value}
                </option>
                {/foreach}
            </select>
        </div>
        <div class="form-group">
            <span class="inline-dlang"><span>评测结果</span><span class="label-subtext">Result</span></span>
        </div>
        <div class="form-group">
            <select name="result" class="form-control status_filter">
                <option value="-1" {if $search_result == -1}selected="true"{/if}>
                    All
                </option>
                {foreach($ojResultsHtml as $key=>$value)}
                {if($key != 13 && $key != 100)}
                <option value="{$key}"  {if $search_result == $key}selected="true"{/if}>
                    {$value[1]}
                </option>
                {/if}
                {/foreach}
            </select>
        </div>
        {if(isset($contest) && (IsAdmin('contest', $contest['contest_id']) || IsAdmin('source_browser'))) }
        <input id="similar_input" name="similar" placeholder="Sim" class="form-control" type="text" style="max-width:50px;">
        {/if}
    </div>
</div>
<div id="status_table_div">
    <table id="status_table"
        data-toggle="table"
        data-unique-id="solution_id"
        data-url="/{$module}/{$controller}/status_ajax{if isset($contest)}?cid={$contest['contest_id']}{/if}"
        data-pagination="true"
        data-page-list="[20]"
        data-page-size="20"
        data-side-pagination="server"
        data-method="get"
        data-striped="true"
        data-sort-name="solution_id_show"
        data-sort-order="desc"
        data-pagination-v-align="bottom"
        data-pagination-h-align="left"
        data-pagination-detail-h-align="right"
        data-toolbar-align="left"
        data-toolbar="#status_toolbar"
        data-query-params="queryParams"
    >
        <thead>
        <tr>
            <th                             data-field="solution_id"    data-align="center" data-valign="middle"  data-sortable="false" data-width="70" data-formatter="FormatterSolutionId">RunID</th>
            <th class='status_user_id'      data-field="user_id"        data-align="center" data-valign="middle"  data-sortable="false" data-formatter="FormatterStatusUser">User</th>
            <th class='status_problem_id'   data-field="problem_id"     data-align="center" data-valign="middle"  data-sortable="false" data-width="70" {if $module!='expsys' || $controller != 'contest' }data-formatter="FormatterProblemId"{/if}>Problem</th>
            <th class='status_result'       data-field="result"         data-align="center" data-valign="middle"  data-sortable="false" data-width="200" data-formatter="FormatterStatusResult">Result</th>
            {if($OJ_OPEN_OI) }
            <th class='status_pass_rate'    data-field="pass_rate"      data-align="center" data-valign="middle"  data-sortable="false" data-width="70" data-formatter="FormatterPassRate">Pass Rate</th>
            {/if}
            <th class='status_memory'       data-field="memory"         data-align="right" data-valign="middle"  data-sortable="false" data-width="80">Memory(kB)</th>
            <th class='status_time'         data-field="time"           data-align="right" data-valign="middle"  data-sortable="false" data-width="80">Time(ms)</th>
            <th class='status_language'     data-field="language"       data-align="center" data-valign="middle"  data-sortable="false" data-width="80" data-formatter="FormatterLanguage">Language</th>
            <th class='status_code_length'  data-field="code_length"    data-align="right" data-valign="middle"  data-sortable="false" data-width="80">Code Length</th>
            <th class='status_in_date'      data-field="in_date"        data-align="center" data-valign="middle"  data-sortable="false"  data-width="160">Submit Time</th>
            {if IsAdmin() || isset($contest) && IsAdmin('contest', $contest['contest_id']) }
                <th data-field="judger" data-align="center" data-valign="middle"  data-sortable="false" >Judger</th>
                <th data-field="rejudge" data-align="center" data-valign="middle"  data-sortable="false" data-formatter="FormatterRejudge" >Rejudge</th>
            {/if}
            {if(isset($contest) && (IsAdmin('contest', $contest['contest_id']) || IsAdmin('source_browser'))) }
                <th data-field="sim" data-align="center" data-valign="middle"  data-sortable="false" data-formatter="FormatterSim" >Similar</th>
            {/if}
        </tr>
        </thead>
    </table>
</div>
<input
        type="hidden"
        id="status_page_information"
        cid="{if(isset($contest))}{$contest['contest_id']}{else/}x{/if}"
        single_status_url="{$single_status_url}"
        show_code_url="{$show_code_url}"
        show_res_url="{$show_res_url}"
        user_id="{$user_id}"
        status_ajax_url="/{$module}/{$controller}/status_ajax{if isset($contest)}?cid={$contest['contest_id']}{/if}"
        rejudge_url="{if $controller=='contest'}/{$module}/admin/contest_rejudge_ajax?cid={$contest['contest_id']}{else /}/admin/problem/problem_rejudge_ajax{/if}"
        status_page_where="{if $controller=='contest'}contest{else /}problemset{/if}"
        module="{$module}"
        OJ_MODE="{$OJ_MODE}"
        OJ_STATUS="{$OJ_STATUS}"
>
{include file="../../csgoj/view/public/code_highlight_base" /}
{include file="../../csgoj/view/public/code_line_number" /}
{include file="../../csgoj/view/public/clipboard_js" /}
{include file="../../csgoj/view/public/js_diff2html" /}

<!-- Modal -->
<div class="modal fade" id="content_show_modal" tabindex="-1" role="dialog" aria-labelledby="content_show_modal_label">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="content_show_modal_label">
                    <span id="content_show_modal_label_span">Code Info</span>&nbsp;&nbsp;&nbsp;&nbsp;
                    <button type="button" class="btn btn-sm btn-success content_show_modal_copy" style="min-width: 100px;" data-clipboard-target="#content_show_to_copy">Copy</button>
                </h4>

            </div>
            <div class="modal-body" id="content_show_modal_content">
                ...
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-success content_show_modal_copy" data-clipboard-target="#content_show_to_copy">Copy</button>
                <button type="button" class="btn btn-info" data-dismiss="modal">Cool!</button>
            </div>
        </div>
    </div>
</div>

{js href="__STATIC__/csgoj/oj_status.js" /}
<style type="text/css">
    .inline-waiting
    {
        width: 100%;
        height: 28px;
        overflow: hidden;
        position: relative;
    }
    .res_running
    {
        margin-top: 3px;
        text-align: center;
        -moz-opacity:0.60;
        opacity: 0.60;
    }
    .loadingblock .loader {
        font-size: 3px;
        text-indent: -9999em;
        border-top:     3px solid rgba(66, 139, 202, 0.8);
        border-right:     3px solid rgba(66, 139, 202, 0.8);
        border-bottom:     3px solid rgba(66, 139, 202, 0.8);
        border-left:     3px solid #ffffff;
        -webkit-animation: loadingblock 1.0s infinite linear;
        animation: loadingblock 1.0s infinite linear;
        position: absolute;
        left: calc(50% - 14px);
        top: calc(50% - 14px);
    }
    .loadingblock .loader,
    .loadingblock .loader:after {
        border-radius: 50%;
        width: 28px;
        height: 28px;
    }
    @-webkit-keyframes loadingblock {
        0% {
            -webkit-transform: rotate(0deg);
            transform: rotate(0deg);
        }
        100% {
            -webkit-transform: rotate(360deg);
            transform: rotate(360deg);
        }
    }
    @keyframes loadingblock {
        0% {
            -webkit-transform: rotate(0deg);
            transform: rotate(0deg);
        }
        100% {
            -webkit-transform: rotate(360deg);
            transform: rotate(360deg);
        }
    }
    #content_show_modal_content pre
    {
        overflow-y: hidden;
    }
    .code_linenumber_div
    {
        overflow-x: auto;
    }

    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }
        100% {
            -webkit-transform: rotate(360deg);
            transform: rotate(360deg);
        }
    }
    #content_show_modal_content pre {
        overflow-y: hidden;
    }
    .code_linenumber_div {
        overflow-x: auto;
    }
    .btn-subtext {
        display: block;
        font-size: 0.75em;
        line-height: 1em;
        color: gray; /* 设置英文文本颜色为灰色 */
    }
    .label-subtext {
        display: inline-block;
        font-size: 0.75em;
        line-height: 1em;
        color: gray; /* 设置英文文本颜色为灰色 */
    }
    .inline-dlang {
        display:flex; 
        flex-direction: column;
    }
</style>