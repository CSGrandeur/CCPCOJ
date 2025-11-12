{css href="__STATIC__/csgoj/news/news.css" /}

<div class="page-header">
    <div class="bg-white border border-primary border-opacity-25 rounded-3 px-3 py-2 mb-3 shadow-sm">
        <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <div class="bg-primary bg-gradient rounded-circle p-1">
                    <i class="bi bi-newspaper text-white" style="font-size: 0.9rem;"></i>
                </div>
                <h1 class="page-title mb-0 text-dark fs-4">
                    团队文章<span class="en-text text-muted fs-6">Team Articles</span>
                </h1>
            </div>
            <div class="d-flex align-items-center gap-1">
                <a href="__HOME__/index/news_list" class="btn btn-outline-primary btn-sm">
                    更多<span class="en-text">More</span> <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<div class="news-list-container">
    {if $news != null}
    {foreach $news as $new }
    <div class="news-item">
        <a href="__HOME__/news/detail?nid={$new['news_id']}" class="news-title">
            {$new['title']}
        </a>
        <div class="news-meta">
            <i class="bi bi-clock me-1"></i>更新时间：{$new['time']}
            <span class="ms-3"><i class="bi bi-pencil me-1"></i>编辑：{$new['user_id']}</span>
        </div>
        <div class="news-content mt-2">
            {$new['content']|htmlspecialchars_decode|stripslashes|mb_substr=0,200,'utf-8'}...
        </div>
    </div>
    {/foreach}
    {else/}
    <div class="news-empty">
        <i class="bi bi-inbox"></i>
        <p>暂无文章<span class="en-text">No Articles</span></p>
    </div>
    {/if}
</div>

{js href="__STATIC__/csgoj/news/news.js" /}