<?php $edit_mode = isset($news); ?>
<script type="text/javascript" src="__STATIC__/js/form_validate_tip.js"></script>

<div class="admin-page-header">
    <div class="admin-page-header-left">
        <div class="admin-page-header-icon">
            <i class="bi bi-newspaper"></i>
        </div>
        <h1 class="admin-page-header-title">
            <div class="admin-page-header-title-main">
                {if $edit_mode }
                    {if $copy_mode}复制文章{else /}编辑文章{/if}
                {else /}
                    添加文章
                {/if}
            </div>
            <div class="admin-page-header-title-right">
                {if $edit_mode }
                <a href="__HOME__/{$news['category']}/detail?nid={$news['news_id']}" target="_blank" class="admin-page-header-id">
                    <i class="bi bi-hash"></i> {$news['news_id']}
                </a>
                {/if}
                {if $edit_mode }
                    {if $copy_mode}<span class="en-text">Copy Article</span>{else /}<span class="en-text">Edit Article</span>{/if}
                {else /}
                    <span class="en-text">Add Article</span>
                {/if}
            </div>
        </h1>
    </div>
    
    {if $edit_mode }
    <div class="admin-page-header-actions">
        <button type="button" class="btn btn-success btn-sm" 
                data-modal-url="__ADMIN__/filemanager/filemanager?item={$controller}&id={$news['news_id']}" 
                data-modal-title="附件管理 - 文章 #{$news['news_id']} - {$news['title']|mb_substr=0,150,'utf-8'}..."
                title="附件管理 (File Manager)">
            <span class="cn-text"><i class="bi bi-paperclip"></i> 附件</span><span class="en-text">Attach</span>
        </button>
        <?php $defunct = $news['defunct']; $item_id = $news['news_id']; ?>
        {include file="admin/changestatus_button" /}
    </div>
    {/if}
</div>

{if(!isset($special_page))}
{include file="news/category_explain" /}
{/if}

<div class="container">
    {if(!isset($special_page))}
    <form id="news_edit_form" method='post' action="__ADMIN__/news/{$action}_ajax">
    {else /}
    <form id="news_edit_form" method='post' action="__ADMIN__/news/news_edit_ajax">
    {/if}

        {if !isset($special_page)}
        {include file="admin/co_editor_input" /}
        {/if}
        
        <div class="form-group">
            <label for="title" class="bilingual-label">文章标题<span class="en-text">Article Title</span></label>
            {if(isset($special_page))}
            <input type="hidden" name="title" value="{if $edit_mode}{$news['title']}{/if}">
            <input type="text" class="form-control" id="title" placeholder="文章标题..." value="{if $edit_mode}{$news['title']}{/if}" disabled>
            {else/}
            <input type="text" class="form-control" id="title" placeholder="文章标题..." name="title" value="{if $edit_mode}{$news['title']}{/if}">
            {/if}
        </div>
        
        {if(!isset($special_page))}
        <div class="form-group">
            <label for="category" class="bilingual-label">分类<span class="en-text">Category</span></label>
            <select name="category" class="form-select" id="category_select">
                <option value="news" {if $edit_mode && $news['category'] == 'news'} selected {/if}>团队新闻</option>
                <option value="notification" {if $edit_mode && $news['category'] == 'notification'} selected {/if}>通知公告</option>
                <option value="answer" {if $edit_mode && $news['category'] == 'answer'} selected {/if}>解题报告</option>
                <option value="cpcinfo" {if $edit_mode && $news['category'] == 'cpcinfo'} selected {/if}>竞赛周边</option>
            </select>
        </div>
        
        <div class="form-group">
            <label for="tags" class="bilingual-label">标签<span class="en-text">Tags</span></label>
            <input type="text" class="form-control" id="tags" placeholder="Contest;Solution;Changsha..." name="tags" value="{if $edit_mode}{$news['tags']}{/if}">
            <div class="form-text">
                用分号分隔，最多5个标签，每个不超过32个字符
                <span class="en-text">Separated by semicolons, max 5 tags, each no more than 32 characters</span>
            </div>
        </div>
        {/if}
        
        <div class="form-group">
            <label for="content" class="bilingual-label">内容 (Markdown)<span class="en-text">Content (Markdown)</span> </label>
            <textarea id="news_content" class="form-control" placeholder="内容..." rows="15" name="content">{if $edit_mode}{$news['content']|htmlspecialchars}{/if}</textarea>
        </div>
        
        <input type="hidden" id='id_input' value="{if $edit_mode}{$news['news_id']}{/if}" name="news_id">
        
        <div class="form-group">
            <button type="submit" id="submit_button" class="btn btn-primary bilingual-button">
                <span><i class="bi bi-check-circle"></i>
                {if $edit_mode}
                修改文章</span><span class="en-text">Modify Article</span>
                {else}
                添加文章</span><span class="en-text">Add Article</span>
                {/if}
            </button>
        </div>
    </form>
</div>

<input type="hidden" id='page_info' edit_mode="{if $edit_mode}1{else/}0{/if}">

{css href="__STATIC__/csgoj/news/news.css" /}
{js href="__STATIC__/csgoj/news/news.js" /}