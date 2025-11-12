<div id="status_toolbar" class="table-toolbar">
    <div class="d-flex align-items-center gap-2" role="form">
        <button id="status_refresh" type="submit" class="btn btn-outline-secondary toolbar-btn" title="刷新 (Refresh)">
            <i class="bi bi-arrow-clockwise"></i>
        </button>
        <button id="status_clear" type="submit" class="btn btn-outline-secondary toolbar-btn" title="清空筛选条件 (Clear)">
            <i class="bi bi-eraser"></i>
        </button>
        <div class="toolbar-group">
            <input id="problem_id_input" name="problem_id" class="form-control toolbar-input status_filter" type="text" value="{$search_problem_id}" style="max-width:100px;" placeholder="Pro ID" title="题目ID (Problem ID)">
        </div>
        <div class="toolbar-group">
            <input id="user_id_input" name="user_id" class="form-control toolbar-input status_filter" type="text" value="{$search_user_id}" style="max-width:120px;" placeholder="User" title="用户ID (User ID)">
        </div>
        <div class="toolbar-group">
            <input id="solution_id_input" name="solution_id" class="form-control toolbar-input status_filter" type="text" value="{$search_solution_id}" style="max-width:100px;" placeholder="Run ID" title="提交ID (Solution ID)">
        </div>
        <div class="toolbar-group">
            <span class="toolbar-label-inline"><span>编程语言</span><span class="toolbar-label en-text">Language</span></span>
            <select name="language" class="form-select toolbar-select status_filter" title="编程语言 (Programming Language)">
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
        <div class="toolbar-group">
            <span class="toolbar-label-inline"><span>评测结果</span><span class="toolbar-label en-text">Result</span></span>
            <select name="result" class="form-select toolbar-select status_filter" title="评测结果 (Result)">
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
        <!-- <input id="similar_input" name="similar" placeholder="Sim" class="form-control" type="text" style="max-width:50px;"> -->
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
        data-sort-name="solution_id_show"
        data-sort-order="desc"
        data-pagination-v-align="bottom"
        data-pagination-h-align="left"
        data-pagination-detail-h-align="right"
        data-toolbar-align="left"
        data-toolbar="#status_toolbar"
        data-query-params="queryParams"
        data-classes="table table-hover table-striped table-bordered"
    >
        <thead>
        <tr>
            <th                             data-field="solution_id"    data-align="center" data-valign="middle"  data-sortable="false" data-width="70" data-formatter="FormatterSolutionId">ID<span class="en-text">RunID</span></th>
            <th class='status_user_id'      data-field="user_id"        data-align="center" data-valign="middle"  data-sortable="false" data-formatter="FormatterStatusUser">账号<span class="en-text">User</span></th>
            <th class='status_problem_id'   data-field="problem_id"     data-align="center" data-valign="middle"  data-sortable="false" data-width="70" {if $module!='expsys' || $controller != 'contest' }data-formatter="FormatterProblemId"{/if}>题号<span class="en-text">Problem</span></th>
            <th class='status_result'       data-field="result"         data-align="center" data-valign="middle"  data-sortable="false" data-width="100" data-formatter="FormatterStatusResult">结果<span class="en-text">Result</span></th>
            {if($OJ_OPEN_OI) }
            <th class='status_pass_rate'    data-field="pass_rate"      data-align="center" data-valign="middle"  data-sortable="false" data-width="70" data-formatter="FormatterPassRate">通过率<span class="en-text">Pass Rate</span></th>
            {/if}
            <th class='status_memory'       data-field="memory"         data-align="right" data-valign="middle"  data-sortable="false" data-width="80">内存(kB)<span class="en-text">Memory(kB)</span></th>
            <th class='status_time'         data-field="time"           data-align="right" data-valign="middle"  data-sortable="false" data-width="80">时间(ms)<span class="en-text">Time(ms)</span></th>
            <th class='status_language'     data-field="language"       data-align="center" data-valign="middle"  data-sortable="false" data-width="80" data-formatter="FormatterLanguage">语言<span class="en-text">Language</span></th>
            <th class='status_code_length'  data-field="code_length"    data-align="right" data-valign="middle"  data-sortable="false" data-width="80">代码长度<span class="en-text">Code Length</span></th>
            <th class='status_in_date'      data-field="in_date"        data-align="center" data-valign="middle"  data-sortable="false"  data-width="70" data-formatter="{if $controller == 'contest'}FormatterTime{else/}FormatterDate{/if}">提交时间<span class="en-text">Submit Time</span></th>
            {if IsAdmin() || isset($contest) && IsAdmin('contest', $contest['contest_id']) }
                <th data-field="judger" data-align="center" data-valign="middle"  data-sortable="false" >评测机<span class="en-text">Judger</span></th>
                <th data-field="rejudge" data-align="center" data-valign="middle"  data-sortable="false" data-formatter="FormatterRejudge" >重测<span class="en-text">Rejudge</span></th>
            {/if}
            {if(isset($contest) && (IsAdmin('contest', $contest['contest_id']) || IsAdmin('source_browser'))) }
                <!-- <th data-field="sim" data-align="center" data-valign="middle"  data-sortable="false" data-formatter="FormatterSim" >相似度<span class="en-text">Similar</span></th> -->
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
{include file="../../csgoj/view/public/js_diff2html" /}

{include file="../../csgoj/view/public/code_highlight_base" /}

{include file="../../csgoj/view/status/code_show" /}
{include file="../../csgoj/view/status/runinfo_show" /}

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
    

    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }
        100% {
            -webkit-transform: rotate(360deg);
            transform: rotate(360deg);
        }
    }
    
    .btn-subtext {
        display: block;
        font-size: 0.75em;
        line-height: 1em;
        color: #6c757d; /* Bootstrap 5 secondary color */
    }
    
    /* Result 列等宽样式 */
    .result-btn, .result-span {
        width: 60px;
        text-align: center;
        display: inline-block;
        font-weight: bold;
    }
    
    /* 加载状态叠加样式 */
    .loading-overlay {
        position: relative;
        cursor: default;
    }
    
    .loading-text {
        opacity: 0.6;
        transition: opacity 0.3s ease;
    }
    
    .spinner-overlay {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        z-index: 1;
    }
    
    .spinner-overlay .spinner-border-sm {
        width: 2rem;
        height: 2rem;
        border-width: 0.15em;
        border-color: #6c757d transparent #6c757d transparent;
        opacity: 0.8;
        animation: spinner-border 2s linear infinite;
    }
    
    @keyframes spinner-border {
        to {
            transform: rotate(360deg);
        }
    }
    .lang-strong, .lang-btn {
        width: 60px;
        text-align: center;
        display: inline-block;
        font-weight: bold;
    }
</style>
