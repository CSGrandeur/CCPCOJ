<div class="p-3">
    <!-- 编程语言配置数据存储 -->
    <input type="hidden" id="oj_language_data" value='{$ojLanguage|json_encode}'>

    <div id="judger_toolbar" class="table-toolbar">
        <div class="d-flex align-items-center gap-1 flex-wrap" role="form">
            <button id="judger_refresh" type="submit" class="btn btn-outline-secondary toolbar-btn" title="刷新 (Refresh)">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
            <button id="judger_clear" type="submit" class="btn btn-outline-secondary toolbar-btn" title="清空筛选条件 (Clear)">
                <i class="bi bi-eraser"></i>
            </button>
            <div class="toolbar-group">
                <select name="flg_white" class="form-select toolbar-select judger_filter" style="width: 80px;" title="题目限制模式 (Problem Limit Mode)" default-value="-1">
                    <option value="-1">全部</option>
                    <option value="1">白名单</option>
                    <option value="0">黑名单</option>
                </select>
            </div>
            <div class="toolbar-group">
                <select name="langlist" id="judger_language_filter" class="form-select toolbar-select judger_filter" style="width: 80px;" title="编程语言 (Programming Language)" default-value="-1">
                    <option value="-1">全部</option>
                </select>
            </div>
            <div class="toolbar-group">
                <select name="defunct" class="form-select toolbar-select judger_filter" style="width: 80px;" title="启用状态 (Enable Status)" default-value="-1">
                    <option value="-1">全部</option>
                    <option value="0">启用</option>
                    <option value="1">禁用</option>
                </select>
            </div>
            <div class="toolbar-group">
                <input id="judger_search_input" name="search" class="form-control toolbar-input judger_filter" type="text" placeholder="搜索..." style="width: 120px;">
            </div>
        </div>
    </div>

    <table
        class="bootstraptable_refresh_local"
        id="judger_list_table"
        data-toggle="table"
        data-url="__ADMIN__/judger/judger_list_ajax"
        data-pagination="true"
        data-page-list="[15,50,100]"
        data-page-size="15"
        data-side-pagination="client"
        data-method="get"
        data-search="false"
        data-pagination-v-align="bottom"
        data-pagination-h-align="left"
        data-pagination-detail-h-align="right"
        data-toolbar="#judger_toolbar"
        data-sortable="false"
        data-unique-id="user_id"
        data-classes="table table-hover table-striped">
        <thead>
            <tr>
                <th data-field="serial" data-align="center" data-valign="middle" data-width="40" data-formatter="FormatterJudgerIndex">#<span class="en-text">#</span></th>
                <th data-field="user_id" data-align="left" data-valign="middle" data-formatter="FormatterJudgerId" title="评测机ID JudgerID">ID<span class="en-text">ID</span></th>
                <th data-field="pro_list" data-align="center" data-valign="middle" data-width="40" data-formatter="FormatterJudgerProList"
                    title="限制评测的题号，由黑白标记决定作为黑名单还是白名单 List of problems to be restricted, determined by white/black flag as a blacklist or whitelist">题目<span class="en-text">Problem</span></th>
                <th data-field="flg_white" data-align="center" data-valign="middle" data-width="40" data-formatter="FormatterJudgerFlgWhite"
                    title="题号限制列表作为黑名单还是白名单 Flag of language limit as a blacklist or whitelist">白<span class="en-text">White</span></th>
                <th data-field="langlist" data-align="center" data-valign="middle" data-width="60" data-formatter="FormatterJudgerLanguage">语言<span class="en-text">Language</span></th>
                <th data-field="accesstime" data-align="center" data-valign="middle" data-width="40" data-formatter="FormatterJudgerAccessTime" title="连接状况 Connection Status">连接<span class="en-text">Connection</span></th>
                <th data-field="defunct" data-align="center" data-valign="middle" data-width="40" data-formatter="FormatterJudgerDefunct" title="启停情况 Enable Status">启停<span class="en-text">On/Off</span></th>
                <th data-field="delete" data-align="center" data-valign="middle" data-width="50" data-formatter="FormatterJudgerDelete">删除<span class="en-text">Delete</span></th>
            </tr>
        </thead>
    </table>
</div>

{js href="__STATIC__/csgoj/general_formatter.js" /}
{js href="__JS__/refresh_in_table.js" /}

<!-- 引入评测机配置面板 -->
{include file="judger/judger_config_panel" /}


<style type="text/css">

</style>