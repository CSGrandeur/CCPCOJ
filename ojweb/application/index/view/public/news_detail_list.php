<div class="page-header">
    <div class="bg-white border border-primary border-opacity-25 rounded-3 px-3 py-2 mb-3 shadow-sm">
        <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <div class="bg-primary bg-gradient rounded-circle p-1">
                    <i class="bi bi-newspaper text-white" style="font-size: 0.9rem;"></i>
                </div>
                <h1 class="page-title mb-0 text-dark fs-4">
                    文章详情列表<span class="en-text text-muted fs-6">Article Detail List</span>
                </h1>
            </div>
        </div>
    </div>
</div>

<table
        id="news_detail_list_table"
                class="bootstraptable_refresh_local"
        data-toggle="table"
          data-url="__HOME__/{$controller}/category_news_list_ajax"
          data-pagination="true"
          data-page-list="[10, 25, 50]"
          data-page-size="10"
          data-side-pagination="server"
          data-method="get"
          data-search="true"
          data-height="460"
        data-sort-name="news_id"
        data-sort-order="desc"
          data-detail-view="true"
          data-detail-formatter="detailFormatter"
          data-classes="table table-striped table-hover"
          data-pagination-h-align="left"
          data-pagination-detail-h-align="right"
          data-search-align="center"
>
    <thead class="table-light">
    <tr>
        <th data-field="title" data-align="left" data-valign="middle" data-sortable="true" data-cell-style="NewsCellStyle">标题<span class="en-text">Title</span></th>
        <th data-field="tags" data-align="center" data-valign="middle" data-sortable="false" data-cell-style="TagCellStyle">标签<span class="en-text">Tags</span></th>
        <th data-field="user_id" data-align="center" data-valign="middle" data-sortable="false" data-width="80">创建者<span class="en-text">Creator</span></th>
        <th data-field="modify_user_id" data-align="center" data-valign="middle" data-sortable="false" data-width="60">编辑<span class="en-text">Editor</span></th>
        <th data-field="modify_time" data-align="center" data-valign="middle" data-sortable="false" data-width="160">更新时间<span class="en-text">Update Time</span></th>
    </tr>
    </thead>
        </table>

        {css href="__STATIC__/csgoj/news/news.css" /}
        {js href="__STATIC__/csgoj/news/news.js" /}
        {js href="__JS__/refresh_in_table.js" /}
        