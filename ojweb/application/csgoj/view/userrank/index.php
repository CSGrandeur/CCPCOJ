<h1 class="page-title bilingual-inline">刷题榜<span class="en-text">Training Rank</span></h1>
<div id="userrank_toolbar" class="table-toolbar">
    <div class="d-flex align-items-center gap-2" role="form">
        <button id="userrank_refresh" type="submit" class="btn btn-outline-secondary toolbar-btn" title="刷新 (Refresh)">
            <i class="bi bi-arrow-clockwise"></i>
        </button>
        <div class="toolbar-group">
            <span class="toolbar-label-inline"><span>搜索</span><span class="toolbar-label en-text">Search</span></span>
            <input id="userrank_search_input" name="search" class="form-control toolbar-input userrank_filter" type="text" placeholder="用户ID/昵称/学校" style="width: 200px;">
        </div>
        <div class="ms-auto toolbar-btn-group">
            <span class="toolbar-label-inline"><span>页码</span><span class="toolbar-label en-text">Page</span></span>
            <input id="page_jump_input" name="page_jump" class="form-control toolbar-input" type="text" value="1" style="width: 80px;" placeholder="页码">
            <button id="userrank_jump" type="button" class="btn btn-outline-secondary toolbar-btn" title="跳转到指定页码 (Jump to Page)">
                <i class="bi bi-arrow-right-circle"></i>
            </button>
        </div>
    </div>
</div>
<table
        class="bootstraptable_refresh_local"
        id="userrank_table"
        data-toggle="table"
        data-url="__OJ__/userrank/userrank_ajax"
        data-pagination="true"
        data-page-list="[25, 50, 100]"
        data-page-size="50"
        data-side-pagination="server"
        data-method="get"
        data-query-params="queryParams"
        data-search="false"
        data-sort-name="rank"
        data-sort-order="asc"
        data-pagination-v-align="both"
        data-pagination-h-align="left"
        data-pagination-detail-h-align="right"
        data-toolbar="#userrank_toolbar"
        data-cookie="true"
        data-cookie-id-table="{$OJ_SESSION_PREFIX}userrank"
        data-cookie-expire="5mi"
        data-classes="table table-borderless table-hover table-striped"
    >
    <thead>
    <tr>
        <th data-field="rank"       data-align="center" data-valign="middle"  data-sortable="false" data-width="55" data-formatter="FormatterRank">排名<span class="en-text">Rank</span></th>
        <th data-field="user_id"    data-align="left" data-valign="middle"  data-sortable="false"   data-width="200" data-formatter="FormatterUserId">用户<span class="en-text">User ID</span></th>
        <th data-field="nick"       data-align="left" data-valign="middle"  data-sortable="false"   data-formatter="FormatterDomSantize">昵称<span class="en-text">Nick Name</span></th>
        <th data-field="school"     data-align="left" data-valign="middle"  data-sortable="false"   data-formatter="FormatterDomSantize">学校<span class="en-text">School</span></th>
        <th data-field="solved"     data-align="left" data-valign="middle"  data-sortable="false"   data-width="80">通过<span class="en-text">Solved</span></th>
        <th data-field="submit"     data-align="left" data-valign="middle"  data-sortable="false"   data-width="100">提交<span class="en-text">Submit</span></th>
        <th data-field="ratio"      data-align="left" data-valign="middle"  data-sortable="false"   data-width="100" data-formatter="FormatterRatio">通过率<span class="en-text">Ratio</span></th>
    </tr>
    </thead>
</table>
{js href="__STATIC__/csgoj/general_formatter.js" /}
{js href="__JS__/refresh_in_table.js" /}

<script type="text/javascript">
let query_params = {};

function FormatterUserId(value, row, index, field) {
    return `<a href='/csgoj/user/userinfo?user_id=${value}' class='rank_userid'>${value}</a>`;
}

function FormatterRatio(value, row, index, field) {
    const submit_num = parseInt(row.submit);
    const solved_num = parseInt(row.solved);
    if(!isNaN(submit_num) && !isNaN(solved_num) && submit_num > 0) {
        return `${(solved_num * 100 / submit_num).toFixed(3)}%`;
    }
    return '-';
}

function FormatterRank(value, row, index, field) {
    return query_params.offset + index + 1;
}

// 使用工厂函数生成queryParams处理器，支持自定义处理
window.queryParams = window.makeQueryParams('userrank', 'userrank_search_input', function(params) {
    // 保存query_params供FormatterRank使用
    query_params = params;
    return params;
});

// 初始化管理后台用户排名列表工具栏
initBootstrapTableToolbar({
    tableId: 'userrank_table',
    prefix: 'userrank',
    filterSelectors: [],
    searchInputId: 'userrank_search_input'
});

// 页码跳转功能
initPageJump('userrank_table', 'userrank_jump', 'page_jump_input');

$(window).keydown(function(e) {
    if (e.keyCode == 116 && !e.ctrlKey) {
        if(window.event){
            try{e.keyCode = 0;}catch(e){}
            e.returnValue = false;
        }
        e.preventDefault();
        $('#userrank_table').bootstrapTable('refresh');
    }
});
</script>
<style type="text/css">
    .rank_userid {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
</style>
