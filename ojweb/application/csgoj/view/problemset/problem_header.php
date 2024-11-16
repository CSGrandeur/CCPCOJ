<?php $controller = strtolower(request()->controller()); ?>
{if isset($contest) }
<ul class="nav nav-pills">
    {foreach($problemIdMap['abc2id'] as $apid__=>$pid__)}
    <li role="presentation" {if $problem["problem_id_show"] == $apid__}class="active"{/if}><a href="problem?cid={$contest['contest_id']}&pid={$apid__}">{$apid__}</a></li>
    {/foreach}
</ul>
    <h2>
        {$problem['problem_id_show']}
        {if array_key_exists("show_real_id", $problem) }
            (<a href="__OJ__/problemset/problem?pid={$problem['problem_id']}">{$problem['problem_id']}</a>)
        {/if}
        : {$problem['title']}
    </h2>
{else /}
    <h1>
        <a href="problem?pid={$problem['problem_id']}">
            {$problem['problem_id_show']}
            {if array_key_exists("show_real_id", $problem) }
            (<a href="__OJ__/problemset/problem?pid={$problem['problem_id']}">{$problem['problem_id']}</a>)
            {/if}
            : {$problem['title']}
        </a>
    </h1>
{/if}

{include file="../../csgoj/view/problemset/submit_button" /}
&nbsp;&nbsp;
<span class="inline_span">Time Limit:     <span class="inline_span text-warning">{$problem['time_limit']} Sec</span>&nbsp;&nbsp;&nbsp;&nbsp;</span>
<span class="inline_span">Memory Limit: <span class="inline_span text-warning">{$problem['memory_limit']} MB</span>&nbsp;&nbsp;&nbsp;&nbsp;</span>
<span class="inline_span">Submitted:     <span class="inline_span text-warning">{$problem['submit']}</span>&nbsp;&nbsp;&nbsp;&nbsp;</span>
<span class="inline_span">Solved:         <span class="inline_span text-warning">{$problem['accepted']}</span>&nbsp;&nbsp;&nbsp;&nbsp;</span>

{if $problem['spj'] == 1 }
<span class="text-red">SpecialJudge</span>
{/if}
<hr/>