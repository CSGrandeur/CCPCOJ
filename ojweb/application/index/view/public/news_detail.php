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

<div class="news_detail_div">
    <div class="row g-3 text-muted small mb-3">
        <div class="col-md-3">
            <span class="cn-text"><i class="bi bi-clock me-1"></i>更新时间</span><span class="en-text">Update Time</span>：<span class="text-danger">{$news['modify_time']}</span>
        </div>
        <div class="col-md-3">
            <span class="cn-text"><i class="bi bi-pencil me-1"></i>最近编辑</span><span class="en-text">Recent Editor</span>：<span class="text-info"><a href="/csgoj/user/userinfo?user_id={$news['modify_user_id']}" class="text-decoration-none">{$news['modify_user_id']}</a></span>
        </div>
        <div class="col-md-3">
            <span class="cn-text"><i class="bi bi-calendar me-1"></i>创建时间</span><span class="en-text">Create Time</span>：<span class="text-muted">{$news['time']}</span>
        </div>
        <div class="col-md-3">
            <span class="cn-text"><i class="bi bi-person me-1"></i>创建者</span><span class="en-text">Creator</span>：<span class="text-muted"><a href="/csgoj/user/userinfo?user_id={$news['user_id']}" class="text-decoration-none">{$news['user_id']}</a></span>
        </div>
    </div>
    
    <article class="md_display_div news_display_div">
        {$news['content']}
    </article>
</div>

{include file="../../csgoj/view/public/code_highlight" /}
{include file="../../csgoj/view/public/mathjax_js" /}
{css href="__STATIC__/csgoj/news/news.css" /}