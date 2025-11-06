{include file="../../csgoj/view/public/code_highlight_base" /}
{include file="../../csgoj/view/public/base_csg_switch" /}
{js href="__STATIC__/csgoj/general_formatter.js"}

{if IsAdmin('contest', $contest['contest_id']) || $printManager }
<div class="alert alert-info d-flex align-items-start" role="alert">
    <i class="bi bi-info-circle me-2 mt-1"></i>
    <div class="d-flex flex-column gap-2">

        <div>打印前需<strong><a href="__IMG__/tutorial/set_default_print.gif" target="_blank">参考链接</a></strong>设置默认打印机为目标打印机。第一次使用时，<strong>页面上方</strong>会提示安装打印控件，用来对接页面打印逻辑和系统打印机，打印前需下载安装。<span class="en-text">Before printing, please <strong><a href="__IMG__/tutorial/set_default_print.gif" target="_blank">refer to the link</a></strong> to set the default printer as the target printer. On first use, you will be prompted to install the print control at the <strong>top of the page</strong> to connect the page printing logic with the system printer. Please download and install it before printing.</span></div>
        <div><strong>请使用最新浏览器，建议使用Chrome。</strong><span class="en-text"><strong>Please use the latest browser, Chrome is recommended.</strong></span></div>
        <div class="mb-0">Chrome94之后禁止了本地网络请求设置，需<strong><a href="__IMG__/tutorial/lodop_chrome_cors.png" target="_blank">关闭该功能</a></strong>：在地址栏输入"chrome://flags"，找到"Block insecure private network requests."改为"Disabled"。<span class="en-text">Chrome 94+ blocks insecure private network requests. Please <strong><a href="__IMG__/tutorial/lodop_chrome_cors.png" target="_blank">disable this feature</a></strong>: Enter "chrome://flags" in the address bar, find "Block insecure private network requests." and set it to "Disabled".</span></div>

    </div>
</div>
{/if}
{js href="__STATIC__/csgoj/contest/print_manager.js"}

<div id="print_status_toolbar" class="table-toolbar">
    <div class="d-flex align-items-center gap-2" role="form">
        <style>
        /* 规范比赛页工具栏的刷新/清空/过滤按钮颜色为与后台一致的 Bootstrap secondary 方案 */
        #print_status_toolbar .btn.btn-outline-secondary {
            --bs-btn-color: var(--bs-secondary);
            --bs-btn-border-color: var(--bs-secondary);
            --bs-btn-hover-bg: var(--bs-secondary);
            --bs-btn-hover-border-color: var(--bs-secondary);
            --bs-btn-active-bg: var(--bs-secondary);
            --bs-btn-active-border-color: var(--bs-secondary);
        }
        </style>
        <button id="print_status_refresh" type="submit" class="btn btn-outline-secondary toolbar-btn" title="刷新 (Refresh)">
            <i class="bi bi-arrow-clockwise"></i>
        </button>
        <button id="print_status_clear" type="submit" class="btn btn-outline-secondary toolbar-btn" title="清空筛选条件 (Clear)">
            <i class="bi bi-eraser"></i>
        </button>
        <div class="toolbar-group">
            <span class="toolbar-label-inline"><span>队伍ID</span><span class="toolbar-label en-text">Team ID</span></span>
            <input id="team_id_input" name="team_id" class="form-control toolbar-input print_status_filter" type="text"
                   value="{if !$printManager /} {$contest_user} {/if}"
            style="max-width:120px;" placeholder="Team ID" title="筛选队伍ID (Filter Team ID)">
        </div>
        <div class="toolbar-group">
            <span class="toolbar-label-inline"><span>分区</span><span class="toolbar-label en-text">Zone</span></span>
            {if $printManager && $login_teaminfo['room'] !== null && $login_teaminfo['room'] != '' }
            <input id="room_limit" class="form-control toolbar-input task_filter" title="{$login_teaminfo['room']}" value="{$login_teaminfo['room']}" disabled >
            <input id="room_ids" name="room_ids" type="hidden" class="form-control toolbar-input task_filter" value="{$login_teaminfo['room']}">
            {else /}
            <input id="room_ids" name="room_ids" class="form-control toolbar-input print_status_filter" placeholder="房间1,房间2,房间3..." type="text" {if isset($room_ids)}value="{$room_ids}" {/if} style="width:220px;" title="半角逗号&quot;,&quot;隔开的不同区域 (Different zones separated by comma &quot;,&quot;)">
            {/if}
        </div>
        <div class="toolbar-group">
            <select name="print_status" class="form-select toolbar-select print_status_filter">
                <option value="-1" selected="true">
                    All
                </option>
            </select>
        </div>
        {if $isContestAdmin || $printManager }
        <div class="toolbar-group">
            <div class="toolbar-btn-group d-flex align-items-center gap-2">
                <div class="csg-switch">
                    <input type="checkbox" 
                           class="csg-switch-input" 
                           id="auto_print_box"
                           data-csg-size="md"
                           data-csg-theme="primary"
                           data-csg-animate="true"
                           data-csg-text-on="自动"
                           data-csg-text-on-en="Auto Print"
                           data-csg-text-off="手动"
                           data-csg-text-off-en="Manual Print">
                </div>
                <span class="text-info">(<strong id="auto_print_interval_span">20</strong>s)</span>
            </div>
        </div>
        {/if}
        {if $printManager && !$isContestAdmin }
        <div class="toolbar-group">
            <div class="toolbar-btn-group d-flex align-items-center gap-2">
                <div class="csg-switch">
                    <input type="checkbox" 
                           class="csg-switch-input" 
                           id="print_color_mode_box"
                           data-csg-size="md"
                           data-csg-theme="primary"
                           data-csg-animate="true"
                           data-csg-storage="true"
                           data-csg-storage-key="print_color_mode"
                           data-csg-text-on="彩色模式"
                           data-csg-text-on-en="Color Mode"
                           data-csg-text-off="黑白模式"
                           data-csg-text-off-en="B&W Mode"
                           checked>
                </div>
            </div>
        </div>
        {/if}
    </div>
</div>
<div class="bootstrap_table_div">
<table
        class="bootstrap_table_table bootstraptable_refresh_local"
        id="print_status_table"
          data-url="__CPC__/contest/print_status_ajax?cid={$contest['contest_id']}"
          data-pagination="true"
          data-page-list="[20,50,100]"
          data-page-size="50"
          data-side-pagination="server"
          data-method="get"
          data-striped="true"
          data-sort-name="print_status"
          data-sort-order="asc"
          data-pagination-v-align="bottom"
          data-pagination-h-align="left"
          data-pagination-detail-h-align="right"
          data-toolbar="#print_status_toolbar"
          data-query-params="queryParams"
          data-response-handler="printStatusResponseHandler"
          data-classes="table table-hover table-striped table-bordered"
          data-cookie="true"
          data-cookie-id-table="{$OJ_SESSION_PREFIX}print-status-{$contest['contest_id']}-{$team_id}"
          data-cookie-expire="1m"
>
    <thead>
    <tr>
        <th data-field="print_id" data-align="center" data-valign="middle"  data-sortable="true" data-width="70">打印ID<span class="en-text">PrintID</span></th>
        {if $printManager || $isContestAdmin}
        <th data-field="school" data-align="left" data-valign="middle"  data-sortable="true">学校<span class="en-text">School</span></th>
        <th data-field="name" data-align="left" data-valign="middle"  data-sortable="true">队名<span class="en-text">Team Name</span></th>
        {/if}
        <th data-field="code_length" data-align="right" data-valign="middle"  data-sortable="true" data-width="80">代码长度<span class="en-text">Code Length</span></th>
        <th data-field="in_date" data-align="center" data-valign="middle"  data-sortable="true"  data-width="100" data-formatter="FormatterTime">提交时间<span class="en-text">Submit Time</span></th>
        <th data-field="print_status" data-align="center" data-valign="middle"  data-sortable="true" data-width="70" data-formatter="FormatterPrintStatus">状态<span class="en-text">Status</span></th>
        {if $printManager || $isContestAdmin}
        <th data-field="room" data-align="center" data-valign="middle"  data-sortable="false"  >房间/区域<span class="en-text">Room/Area</span></th>
        {/if}
        <th data-field="team_id" data-align="center" data-valign="middle"  data-sortable="false" data-width="100" data-formatter="FormatterTeamId">队伍ID<span class="en-text">Team ID</span></th>
        {if($printManager && !$isContestAdmin)}
        <th data-field="do_print" data-align="center" data-valign="middle"  data-sortable="false" data-width="70" data-formatter="FormatterPrintAction">打印<span class="en-text">Print</span></th>
        {/if}
        {if $printManager}
        <th data-field="do_deny" data-align="center" data-valign="middle"  data-sortable="false" data-width="70" data-formatter="FormatterPrintDenyAction">拒绝<span class="en-text">Deny</span></th>
        {/if}
    </tr>
    </thead>
</table>
</div>
<input
    type="hidden"
    id="print_status_page_information"
    cid="{if(isset($contest))}{$contest['contest_id']}{else/}x{/if}"
    team_id="{$contest_user}"
    show_code_url="{$show_code_url}"
>
{js href="__JS__/refresh_in_table.js"}
{js href="__STATIC__/csgoj/contest/rank_tool.js"}

<script>
    // 配置信息
    window.PRINT_STATUS_CONFIG = {
        cid: <?php echo intval($contest['contest_id']); ?>,
        print_status_ajax_url: '__CPC__/contest/print_status_ajax?cid=<?php echo intval($contest['contest_id']); ?>',
        contest_data_url: '__CPC__/contest/contest_data_ajax',
        is_print_manager: <?php echo ($printManager) ? 'true' : 'false'; ?>,
        is_contest_admin: <?php echo ($isContestAdmin) ? 'true' : 'false'; ?>
    };
</script>

{include file="contest/print_control" /}
