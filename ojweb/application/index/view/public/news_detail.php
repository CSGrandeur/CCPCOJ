{css href="__STATIC__/csgoj/news/news.css" /}

<h1 class="page-title news-title">
    #{$news['news_id']} {$news['title']}
</h1>

<div class="problem-header-row">
    <div class="problem-info-inline">
        <span class="info-item">
            <span class="info-label">分类<span class="en-text">Category</span></span>
            <span class="info-value">
                {if $news['category'] == 'news'}
                    <span class="badge bg-info">团队新闻<span class="en-text">Team News</span></span>
                {elseif $news['category'] == 'notification' /}
                    <span class="badge bg-warning">通知公告<span class="en-text">Notification</span></span>
                {elseif $news['category'] == 'answer' /}
                    <span class="badge bg-success">解题报告<span class="en-text">Solution</span></span>
                {elseif $news['category'] == 'cpcinfo' /}
                    <span class="badge bg-primary">竞赛周边<span class="en-text">Contest Info</span></span>
                {else /}
                    <span class="badge bg-secondary">{$news['category']}<span class="en-text">{$news['category']}</span></span>
                {/if}
            </span>
        </span>
        {if isset($news['tags']) && !empty($news['tags'])}
        <span class="info-item">
            <span class="info-label">标签<span class="en-text">Tags</span></span>
            <span class="info-value">
                <?php 
                $tags = explode(';', $news['tags']);
                foreach($tags as $tag) {
                    $tag = trim($tag);
                    if(!empty($tag)) {
                        echo '<span class="badge bg-secondary me-1">' . htmlspecialchars($tag) . '</span>';
                    }
                }
                ?>
            </span>
        </span>
        {/if}
        <span class="info-item">
            <span class="info-label">创建时间<span class="en-text">Create Time</span></span>
            <span class="info-value text-muted">{$news['time']}</span>
        </span>
        <span class="info-item">
            <span class="info-label">更新时间<span class="en-text">Update Time</span></span>
            <span class="info-value text-danger">{if isset($news['modify_time']) && $news['modify_time']}{$news['modify_time']}{else/}{$news['time']}{/if}</span>
        </span>
        <span class="info-item">
            <span class="info-label">创建者<span class="en-text">Creator</span></span>
            <span class="info-value">
                <a href="/csgoj/user/userinfo?user_id={$news['user_id']}" class="text-decoration-none text-info">{$news['user_id']}</a>
            </span>
        </span>
        {if isset($news['modify_user_id']) && $news['modify_user_id'] != $news['user_id']}
        <span class="info-item">
            <span class="info-label">最近编辑<span class="en-text">Recent Editor</span></span>
            <span class="info-value">
                <a href="/csgoj/user/userinfo?user_id={$news['modify_user_id']}" class="text-decoration-none text-info">{$news['modify_user_id']}</a>
            </span>
        </span>
        {/if}
    </div>
</div>
<hr/>

<div class="md_display_div">
    {$news['content']}
</div>

{include file="../../csgoj/view/public/code_highlight" /}
{include file="../../csgoj/view/public/mathjax_js" /}
