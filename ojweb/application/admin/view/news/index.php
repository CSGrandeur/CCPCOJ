<div id="news_toolbar">
    <div class="d-flex align-items-center gap-2" role="form">
        <button id="news_refresh" type="submit" class="btn btn-outline-secondary" title="刷新 (Refresh)">
            <i class="bi bi-arrow-clockwise"></i>
        </button>
        <button id="news_clear" type="submit" class="btn btn-outline-secondary" title="清空筛选条件 (Clear)">
            <i class="bi bi-eraser"></i>
        </button>
        <button id="news_filter" type="submit" class="btn btn-outline-secondary" title="应用筛选 (Apply Filter)">
            <i class="bi bi-funnel"></i>
        </button>
        <div class="mb-2">
            <span class="inline-dlang"><span>分类</span><span class="label-subtext">Category</span></span>
        </div>
        <div class="mb-2">
            <select name="category" class="form-select news_filter" id="category_filter_select">
                <!-- 分类选项将通过 JavaScript 动态生成 -->
            </select>
        </div>
        <div class="mb-2">
            <span class="inline-dlang"><span>状态</span><span class="label-subtext">Status</span></span>
        </div>
        <div class="mb-2">
            <select name="defunct" class="form-select news_filter">
                <option value="-1" selected>
                    All
                </option>
                <option value="0">
                    公开<span class="en-text">Public</span>
                </option>
                <option value="1">
                    隐藏<span class="en-text">Hidden</span>
                </option>
            </select>
        </div>
        <div class="mb-2">
            <span class="inline-dlang"><span>搜索</span><span class="label-subtext">Search</span></span>
        </div>
        <div class="mb-2">
            <input id="news_search_input" name="search" class="form-control news_filter" type="text" placeholder="标题/内容/标签" style="width: 200px;">
        </div>
        <div class="mb-2">
            <button id="news_add" type="button" class="btn btn-primary" title="添加文章 (Add Article)">
                <span class="cn-text"><i class="bi bi-plus-circle"></i> 添加</span><span class="en-text">Add</span>
            </button>
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
          data-page-size="100"
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
          data-cookie="true"
          data-cookie-id-table="{$OJ_SESSION_PREFIX}admin-newslist"
          data-cookie-expire="5mi"
>
    <thead>
    <tr>
        <th data-field="news_id" data-align="center" data-valign="middle" data-sortable="true" data-width="55">ID<span class="en-text">ID</span></th>
        <th data-field="title" data-align="left" data-valign="middle" data-sortable="false" data-formatter="FormatterNewsTitle">标题<span class="en-text">Title</span></th>
        <th data-field="category" data-align="left" data-valign="middle" data-sortable="false" data-formatter="FormatterNewsCategory">分类<span class="en-text">Category</span></th>
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
window.queryParams = window.makeQueryParams('news', 'news_search_input');

// 初始化管理后台新闻列表工具栏
initBootstrapTableToolbar({
    tableId: 'admin_newslist_table',
    prefix: 'news',
    filterSelectors: ['category', 'defunct'],
    searchInputId: 'news_search_input'
});

// 添加新闻按钮事件
$('#news_add').click(function() {
    location.href = '__ADMIN__/news/news_add';
});

// 初始化分类筛选选项
document.addEventListener('DOMContentLoaded', function() {
    const categorySelect = document.getElementById('category_filter_select');
    if (categorySelect && window.generateCategoryOptions) {
        categorySelect.innerHTML = generateCategoryOptions();
    }
});
</script>