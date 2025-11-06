<div id="contest_toolbar" class="table-toolbar">
    <div class="d-flex align-items-center gap-2" role="form">
        <button id="contest_refresh" type="button" class="btn btn-outline-secondary toolbar-btn" title="刷新 (Refresh)">
            <i class="bi bi-arrow-clockwise"></i>
        </button>
        <button id="contest_clear" type="button" class="btn btn-outline-secondary toolbar-btn" title="清空筛选条件 (Clear)">
            <i class="bi bi-eraser"></i>
        </button>
        {if $module != 'cpcsys'}
        <div class="toolbar-group">
            <span class="toolbar-label-inline"><span>类型</span><span class="toolbar-label en-text">Type</span></span>
            <select name="private" class="form-select toolbar-select contest_filter">
                <option value="-1">
                    全部 <span class="en-text">All</span>
                </option>
                <option value="0">
                    公开 <span class="en-text">Public</span>
                </option>
                <option value="5">
                    加密 <span class="en-text">Encrypted</span>
                </option>
                <option value="1">
                    私有 <span class="en-text">Private</span>
                </option>
                {if $module == 'admin'}
                <option value="2">
                    标准 <span class="en-text">Standard</span>
                </option>
                {/if}
            </select>
        </div>
        {/if}
        {if isset($is_admin) && $is_admin}
        <div class="toolbar-group">
            <span class="toolbar-label-inline"><span>公开状态</span><span class="toolbar-label en-text">Contest Status</span></span>
            <select name="defunct" class="form-select toolbar-select contest_filter">
                <option value="-1">
                    全部 <span class="en-text">All</span>
                </option>
                <option value="0">
                    公开 <span class="en-text">Public</span>
                </option>
                <option value="1">
                    隐藏 <span class="en-text">Hidden</span>
                </option>
            </select>
        </div>
        {/if}
        <div class="toolbar-group">
            <span class="toolbar-label-inline"><span>时间状态</span><span class="toolbar-label en-text">Time Status</span></span>
            <select name="status" class="form-select toolbar-select contest_filter">
                <option value="-1">
                    All
                </option>
                <option value="0">
                    未开始 <span class="en-text">Not Started</span>
                </option>
                <option value="1">
                    进行中 <span class="en-text">Running</span>
                </option>
                <option value="2">
                    已结束 <span class="en-text">Ended</span>
                </option>
            </select>
        </div>
        <div class="toolbar-group">
            <span class="toolbar-label-inline"><span>搜索</span><span class="toolbar-label en-text">Search</span></span>
            <input id="contest_search_input" name="search" class="form-control toolbar-input contest_filter" type="text" placeholder="标题/ID" style="width: 200px;">
        </div>
    </div>
</div>

<table
        id="contest_list_table"
        class="{if isset($is_admin) && $is_admin}bootstraptable_refresh_local{/if}"
        data-toggle="table"
          data-url="/{$module}/{$controller}/contest_list_ajax"
          data-pagination="true"
          data-page-list="[25,50,100]"
          data-page-size="25"
          data-side-pagination="client"
          data-method="get"
          {if !isset($is_admin) || !$is_admin}data-striped="true"{/if}
          data-search="false"
          data-search-align="left"
          data-sort-name="contest_id"
          data-sort-order="desc"
          data-pagination-v-align="bottom"
          data-pagination-h-align="left"
          data-pagination-detail-h-align="right"
          {if !isset($is_admin) || !$is_admin}data-classes="table-no-bordered table table-hover"{/if}
          data-toolbar="#contest_toolbar"
          data-filter-control="true"
          data-filter-show-clear="true"
          {if isset($is_admin) && $is_admin}
          data-cookie="true"
          data-cookie-id-table="{$OJ_SESSION_PREFIX}admin-contestlist"
          data-cookie-expire="5mi"
          {/if}
>
    <thead>
    <tr>
        <th data-field="contest_id" data-align="center" data-valign="middle"  data-sortable="{if isset($is_admin) && $is_admin}true{else}false{/if}" data-width="55">ID<span class="en-text">ID</span></th>
        <th data-field="title" data-align="left" data-valign="middle"  data-sortable="false" data-formatter="FormatterContestTitle">标题<span class="en-text">Title</span></th>
        {if isset($is_admin) && $is_admin}
        <th data-field="defunct" data-align="center" data-valign="middle"  data-formatter="FormatterDefunctContest"  data-width="60">状态<span class="en-text">Status</span></th>
        <th data-field="edit" data-align="center" data-valign="middle"  data-formatter="FormatterContestEdit"  data-width="60">编辑<span class="en-text">Edit</span></th>
        <th data-field="copy" data-align="center" data-valign="middle"  data-formatter="FormatterContestCopy"  data-width="60">复制<span class="en-text">Copy</span></th>
        <th data-field="attach" data-align="center" data-valign="middle"  data-formatter="FormatterContestAttach"  data-width="60">附件<span class="en-text">Attach</span></th>
        <th data-field="rejudge" data-align="center" data-valign="middle"  data-formatter="FormatterContestRejudge"  data-width="60">重判<span class="en-text">Rejudge</span></th>
        {/if}
        <th data-field="status" data-align="center" data-valign="middle"  data-sortable="true" data-width="60" data-formatter="FormatterContestTimeStatus">状态<span class="en-text">Status</span></th>
        <th data-field="start_time" data-align="center" data-valign="middle"  data-sortable="true" data-width="70" data-formatter="FormatterDateTimeBoth">开始<span class="en-text">Start</span></th>
        <th data-field="end_time" data-align="center" data-valign="middle"  data-sortable="true" data-width="70" data-formatter="FormatterDateTimeBoth">结束<span class="en-text">End</span></th>
        <th data-field="private" data-align="center" data-valign="middle"  data-sortable="true" data-width="60" data-formatter="FormatterContestType">类型<span class="en-text">Type</span></th>
    </tr>
    </thead>
</table>
<input type="hidden" id="page_info" page_module="{$module}"  >

{js href="__STATIC__/csgoj/oj_contest.js"}
{js href="__JS__/refresh_in_table.js" /}

<script type="text/javascript">
let page_info = $('#page_info');
var page_module = page_info.attr('page_module');

// 根据是否为管理后台决定筛选条件
var filterSelectors = ['private', 'status'];
<?php  if(isset($is_admin) && $is_admin){ ?>
filterSelectors.push('defunct');
<?php } ?>

// 初始化比赛列表工具栏（客户端筛选）
initBootstrapTableClientToolbar({
    tableId: 'contest_list_table',
    prefix: 'contest',
    filterSelectors: filterSelectors,
    searchInputId: 'contest_search_input',
    searchFields: {
        title: 'title',
        contest_id: 'contest_id'
    }
});

// F5刷新处理
$(window).keydown(function(e) {
    if (e.keyCode == 116 && !e.ctrlKey) {
        if(window.event){
            try{e.keyCode = 0;}catch(e){}
            e.returnValue = false;
        }
        e.preventDefault();
        $('#contest_list_table').bootstrapTable('refresh');
    }
});
</script>
