

<h3 class="bilingual-inline">公告<span class="en-text">Announcement</span></h3>
<div class="contest-announcement-content">
    {if $contestStatus == -1}
        <div class="alert alert-info">比赛尚未开始<span class="en-text">This contest is not started yet.</span></div>
    {elseif !session('?user_id') && (!isset($contest_user) || !$contest_user) }
        <div class="alert alert-info">请先登录后再参加比赛<span class="en-text">Please login before joining the contest.</span></div>
    {/if}
    
    {if $module == 'cpcsys'}
        {include file="../../cpcsys/view/contest/contest_login" /}
    {else /}
        {include file="../../csgoj/view/contest/contest_login" /}
    {/if}
    
    {if !$canJoin /}
        <div class="alert alert-danger">未登录时无权限参与，只能观看排行榜<span class="en-text">Not permitted to participate, you can only watch the ranklist.</span></div>
    {/if}
    
    <?php if(strlen($contest['description']) == 0): ?>
        <div class="text-muted">暂无更多信息<span class="en-text">Nothing more.</span></div>
    <?php else: ?>
        <div class="contest-description">
            {$contest['description']}
        </div>
    <?php endif; ?>
</div>
{include file="../../csgoj/view/public/mathjax_js" /}

<style>
.contest-announcement-content {
    margin-bottom: 1rem;
}

.contest-announcement-content .alert {
    margin-bottom: 0.75rem;
    padding: 0.5rem 0.75rem;
    font-size: 0.9rem;
}

.contest-announcement-content .row.g-3 {
    margin-bottom: 0.75rem;
}

.contest-announcement-content .form-control {
    font-size: 0.9rem;
    padding: 0.375rem 0.75rem;
}

.contest-announcement-content .btn {
    font-size: 0.9rem;
    padding: 0.375rem 0.75rem;
}

.contest-description {
    margin-top: 1rem;
    line-height: 1.5;
}

.contest-description h1,
.contest-description h2,
.contest-description h3,
.contest-description h4,
.contest-description h5,
.contest-description h6 {
    margin-top: 1rem;
    margin-bottom: 0.5rem;
}

.contest-description p {
    margin-bottom: 0.75rem;
}

.contest-description ul,
.contest-description ol {
    margin-bottom: 0.75rem;
    padding-left: 1.5rem;
}

.contest-description code {
    background: #f8f9fa;
    padding: 0.125rem 0.25rem;
    border-radius: 3px;
    font-family: 'Courier New', monospace;
    color: #e83e8c;
    font-size: 0.9em;
}

.contest-description pre {
    background: #f8f9fa;
    padding: 0.75rem;
    border-radius: 4px;
    overflow-x: auto;
    margin-bottom: 0.75rem;
    font-size: 0.9em;
}
</style>