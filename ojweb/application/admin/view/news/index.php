<div id="news_toolbar" class="table-toolbar">
    <div class="d-flex align-items-center gap-2" role="form">
        <button id="news_refresh" type="button" class="btn btn-outline-secondary toolbar-btn" title="刷新 (Refresh)">
            <i class="bi bi-arrow-clockwise"></i>
        </button>
        <button id="news_clear" type="button" class="btn btn-outline-secondary toolbar-btn" title="清空筛选条件 (Clear)">
            <i class="bi bi-eraser"></i>
        </button>

        <div class="toolbar-group">
            <span class="toolbar-label-inline"><span>分类</span><span class="toolbar-label en-text">Category</span></span>
            <select name="category" class="form-select toolbar-select news_filter" id="category_filter_select">
                <!-- 分类选项将通过 JavaScript 动态生成 -->
            </select>
        </div>
        <div class="toolbar-group">
            <span class="toolbar-label-inline"><span>状态</span><span class="toolbar-label en-text">Status</span></span>
            <select name="defunct" class="form-select toolbar-select news_filter">
                <option value="-1" selected>
                    All
                </option>
                <option value="0">
                    公开 <span class="en-text">Public</span>
                </option>
                <option value="1">
                    隐藏 <span class="en-text">Hidden</span>
                </option>
            </select>
        </div>
        <div class="toolbar-group">
            <span class="toolbar-label-inline"><span>搜索</span><span class="toolbar-label en-text">Search</span></span>
            <input id="news_search_input" name="search" class="form-control toolbar-input news_filter" type="text" placeholder="标题/内容/标签" style="width: 200px;">
        </div>
    </div>
</div>

<table
        class="bootstraptable_refresh_local"
        id="admin_newslist_table"
        data-toggle="table"
        data-url="__ADMIN__/news/news_list_ajax"
        data-pagination="true"
        data-page-list="[25, 50, 100]"
        data-page-size="50"
        data-side-pagination="server"
        data-method="get"
        data-search="false"
        data-sort-name="news_id"
        data-sort-order="desc"
        data-pagination-v-align="both"
        data-pagination-h-align="left"
        data-pagination-detail-h-align="right"
        data-toolbar="#news_toolbar"
        data-query-params="queryParams"
        data-classes="table table-no-bordered table-hover table-striped"
>
    <thead>
    <tr>
        <th data-field="news_id" data-align="center" data-valign="middle" data-sortable="true" data-width="55">ID<span class="en-text">ID</span></th>
        <th data-field="title" data-align="left" data-valign="middle" data-sortable="false" data-formatter="FormatterNewsTitle">标题<span class="en-text">Title</span></th>
        <th data-field="category" data-align="center" data-valign="middle" data-sortable="false" data-formatter="FormatterNewsCategory">分类<span class="en-text">Category</span></th>
        <th data-field="tags" data-align="left" data-valign="middle" data-sortable="false" data-formatter="FormatterNewsTags">标签<span class="en-text">Tags</span></th>
        <th data-field="defunct" data-align="center" data-valign="middle" data-sortable="false" data-width="80" data-formatter="FormatterNewsStatus">状态<span class="en-text">Status</span></th>
        <th data-field="edit" data-align="center" data-valign="middle" data-sortable="false" data-width="70" data-formatter="FormatterNewsEdit">编辑<span class="en-text">Edit</span></th>
        <th data-field="user_id" data-align="left" data-valign="middle" data-sortable="false" data-width="80">创建者<span class="en-text">Creator</span></th>
        <th data-field="time" data-align="center" data-valign="middle" data-sortable="false" data-width="120" data-formatter="FormatterDate">时间<span class="en-text">Time</span></th>
    </tr>
    </thead>
</table>

{include file="admin/js_changestatus" /}
{js href="__JS__/refresh_in_table.js" /}
{js href="__STATIC__/csgoj/general_formatter.js" /}
{js href="__STATIC__/csgoj/news/index.js" /}
{js href="__STATIC__/csgoj/news/news.js" /}

<script>
// 使用工厂函数生成queryParams处理器
window.queryParams = window.makeQueryParams('news', 'news_search_input', function(params) {
    // 处理筛选条件
    ['category', 'defunct'].forEach(function(selector) {
        var value = $('select[name="' + selector + '"]').val();
        if (value != -1) {
            params[selector] = value;
        }
    });
    return params;
});

// 初始化管理后台新闻列表工具栏（客户端筛选）
initBootstrapTableClientToolbar({
    tableId: 'admin_newslist_table',
    prefix: 'news',
    filterSelectors: ['category', 'defunct'],
    searchInputId: 'news_search_input',
    searchFields: {
        title: 'title',
        news_id: 'news_id',
        user_id: 'user_id',
        category: 'category'
    }
});

// 初始化分类筛选选项
document.addEventListener('DOMContentLoaded', function() {
    const categorySelect = document.getElementById('category_filter_select');
    if (categorySelect && window.generateCategoryOptions) {
        categorySelect.innerHTML = generateCategoryOptions();
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
        $('#admin_newslist_table').bootstrapTable('refresh');
    }
});
</script>