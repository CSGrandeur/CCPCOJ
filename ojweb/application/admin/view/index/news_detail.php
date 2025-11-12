<div class="container mt-4">
	<div class="page-header mb-4">
		<h1 class="page-title">
			<span class="cn-text"><i class="bi bi-newspaper me-2"></i>公告详情</span><span class="en-text">Announcement</span>
		</h1>
	</div>
	
	<div class="row">
		<div class="col-lg-8">
			<div class="card">
				<div class="card-header">
					<h2 class="card-title mb-0">{$news['title']}</h2>
				</div>
				<div class="card-body">
					<div class="news-meta mb-3">
						<div class="d-flex flex-wrap gap-3 text-muted small">
							<span>
								<span class="cn-text"><i class="bi bi-clock me-1"></i>更新时间</span><span class="en-text">Update Time</span>：{$news['time']}
							</span>
							<span>
								<span class="cn-text"><i class="bi bi-person me-1"></i>最近编辑</span><span class="en-text">Recent Edit</span>：{$news['user_id']}
							</span>
						</div>
					</div>
					<div class="md_display_div">
						{$news['content']}
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

{include file="../../csgoj/view/public/code_highlight" /}