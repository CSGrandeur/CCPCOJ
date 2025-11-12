{css href="__STATIC__/csgoj/news/news.css" /}

<div class="page-header">
    <div class="bg-white border border-primary border-opacity-25 rounded-3 px-3 py-2 mb-3 shadow-sm">
        <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <div class="bg-primary bg-gradient rounded-circle p-1">
                    <i class="bi bi-newspaper text-white" style="font-size: 0.9rem;"></i>
                </div>
                <h1 class="page-title mb-0 text-dark fs-4" title="{$news['title']}">
                    {$news['title']}
                </h1>
            </div>
        </div>
    </div>
</div>

<div class="news-detail-container">
    <div class="news-detail-meta">
        <div class="row g-3 text-muted small">
            <div class="col-md-6">
                <span class="cn-text"><i class="bi bi-clock me-1"></i>更新时间</span><span class="en-text">Update Time</span>：<span class="text-danger">{$news['time']}</span>
            </div>
            <div class="col-md-6">
                <span class="cn-text"><i class="bi bi-pencil me-1"></i>最近编辑</span><span class="en-text">Recent Edit</span>：<span class="text-info">{$news['user_id']}</span>
            </div>
        </div>
    </div>

    <div class="news-detail-content md_display_div">
        {$news['content']}
    </div>
</div>

{include file="../../csgoj/view/public/code_highlight" /}
{include file="../../csgoj/view/public/mathjax_js" /}
{js href="__STATIC__/csgoj/news/news.js" /}