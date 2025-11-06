<div class="page-header">
    <div class="bg-white border border-primary border-opacity-25 rounded-3 px-3 py-2 mb-3 shadow-sm">
        <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <div class="bg-primary bg-gradient rounded-circle p-1">
                    <i class="bi bi-newspaper text-white" style="font-size: 0.9rem;"></i>
                </div>
                <h1 class="page-title mb-0 text-dark fs-4">
                    公告列表<span class="en-text text-muted fs-6">Announcement List</span>
                </h1>
            </div>
        </div>
    </div>
</div>

<table data-toggle="table"
        data-url="__HOME__/index/news_list_ajax"
        data-pagination="true"
        data-page-list="[10, 20, 50]"
        data-page-size="10"
        data-side-pagination="server"
        data-method="get"
        data-search="true"
        data-sort-name="news_id"
        data-sort-order="desc"
        data-pagination-v-align="both"
        data-pagination-h-align="left"
        data-pagination-detail-h-align="right"
        data-search-align="right"
        data-cookie="true"
        data-cookie-id-table="{$OJ_SESSION_PREFIX}news-set"
        data-classes="table table-striped table-hover"
    >
    <thead class="table-light">
    <tr>
        <th data-field="news_id" data-align="center" data-valign="middle" data-checkbox="false">ID<span class="en-text">ID</span></th>
        <th data-field="title" data-align="left" data-valign="middle" data-sortable="false">公告标题<span class="en-text">Announcement Title</span></th>
        <th data-field="time" data-align="center" data-valign="middle" data-sortable="false">更新时间<span class="en-text">Update Time</span></th>
        <th data-field="user_id" data-align="center" data-valign="middle" data-sortable="false">编辑<span class="en-text">Editor</span></th>
    </tr>
    </thead>
</table>