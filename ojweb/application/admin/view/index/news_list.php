<div class="container mt-4">
	<div class="page-header mb-4">
		<h1 class="page-title">
			<span class="cn-text"><i class="bi bi-list-ul me-2"></i>公告列表</span><span class="en-text">News List</span>
		</h1>
	</div>
	
	<div class="card">
		<div class="card-body">
			<table	data-toggle="table"
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
					class="table table-striped table-hover"
				>
				<thead class="table-light">
				<tr>
					<th data-field="news_id" data-align="center" data-valign="middle" data-checkbox="false">
						ID<span class="en-text">ID</span>
					</th>
					<th data-field="title" data-align="left" data-valign="middle" data-sortable="false">
						公告标题<span class="en-text">Title</span>
					</th>
					<th data-field="time" data-align="center" data-valign="middle" data-sortable="false">
						更新时间<span class="en-text">Update Time</span>
					</th>
					<th data-field="user_id" data-align="center" data-valign="middle" data-sortable="false">
						编辑<span class="en-text">Editor</span>
					</th>
				</tr>
				</thead>
			</table>
		</div>
	</div>
</div>