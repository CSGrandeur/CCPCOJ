<?php $controller = strtolower(request()->controller()); ?>
{if isset($contest) }
<div class="contest-problem-nav">
    <ul class="nav nav-pills contest-problem-pills">
        {foreach($problemIdMap['abc2id'] as $apid__=>$pid__)}
        <li class="nav-item" role="presentation">
            <a class="nav-link contest-problem-link {if $problem["problem_id_show"] == $apid__}active{/if}" href="problem?cid={$contest['contest_id']}&pid={$apid__}">
                <span class="problem-letter">{$apid__}</span>
            </a>
        </li>
        {/foreach}
    </ul>
</div>
    <h2 class="problem-title-container">
        <span class="problem-id">{$problem['problem_id_show']}</span>
        {if array_key_exists("show_real_id", $problem) }
            <span class="problem-real-id">(<a href="__OJ__/problemset/problem?pid={$problem['problem_id']}" class="text-decoration-none">{$problem['problem_id']}</a>)</span>
        {/if}
        <span class="problem-separator">:</span>
        <span class="problem-title">{$problem['title']}</span>
    </h2>
{else /}
    <h1 class="page-title problem-title-container">
        <a href="problem?pid={$problem['problem_id']}" class="text-decoration-none">
            <span class="problem-id">{$problem['problem_id_show']}</span>
            {if array_key_exists("show_real_id", $problem) }
            <span class="problem-real-id">(<a href="__OJ__/problemset/problem?pid={$problem['problem_id']}" class="text-decoration-none">{$problem['problem_id']}</a>)</span>
            {/if}
            <span class="problem-separator">:</span>
            <span class="problem-title {if $problem['spj'] == 1}text-warning{elseif $problem['spj'] == 2}text-success{else}text-primary{/if}">{$problem['title']}</span>
        </a>
    </h1>
{/if}

<div class="problem-header-row">
    <div class="problem-buttons">
        {include file="../../csgoj/view/problemset/submit_button" /}
    </div>
    <div class="problem-info-inline">
        <span class="info-item">
            <span class="info-label">时间限制<span class="en-text">Time Limit</span></span>
            <span class="info-value">{$problem['time_limit']}</span>
            <span class="info-unit">秒<span class="en-text">Sec</span></span>
        </span>
        <span class="info-item">
            <span class="info-label">内存限制<span class="en-text">Memory Limit</span></span>
            <span class="info-value">{$problem['memory_limit']}</span>
            <span class="info-unit">兆<span class="en-text">MB</span></span>
        </span>
        <span class="info-item">
            <span class="info-label">提交次数<span class="en-text">Submitted</span></span>
            <span class="info-value">{$problem['submit']}</span>
            <span class="info-unit">次<span class="en-text">Times</span></span>
        </span>
        <span class="info-item">
            <span class="info-label">通过次数<span class="en-text">Solved</span></span>
            <span class="info-value">{$problem['accepted']}</span>
            <span class="info-unit">次<span class="en-text">Times</span></span>
        </span>
        <span class="info-item judge-type-item">
            {if $problem['spj'] == 0 }
            <span class="text-primary"><span class="cn-text"><i class="bi bi-check-circle"></i> 标准评测</span><span class="en-text">Standard Judge</span></span>
            {elseif $problem['spj'] == 1 }
            <span class="text-warning"><span class="cn-text"><i class="bi bi-gear"></i> 特判评测</span><span class="en-text">Special Judge</span></span>
            {elseif $problem['spj'] == 2 }
            <span class="text-success"><span class="cn-text"><i class="bi bi-chat-dots"></i> 交互评测</span><span class="en-text">Interactive Judge</span></span>
            {/if}
        </span>
    </div>
</div>
<hr/>