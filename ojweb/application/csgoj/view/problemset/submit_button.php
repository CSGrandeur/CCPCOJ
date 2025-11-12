{if isset($contest) }
    {if $contest_user && $running == 1 && (in_array($contest['private'] % 10, [0, 1]) || !IsAdmin('source_browser') && !$isContestAdmin && (!isset($isContestStaff) || !$isContestStaff)) }
    <a href="/{$module}/{$controller}/submit?cid={$contest['contest_id']}&pid={$apid}" class="a_noline">
        <button type="button" class="btn btn-outline-primary">提交<span class="en-text">Submit Page</span></button>
    </a>
    {else /}
    <a href="javascript:void(null)" class='disabled_problem_submit_button a_noline' 
    info_str="{if IsAdmin('source_browser')} You are source browser! {elseif isset($isContestStaff) && $isContestStaff /} You are contest staff! {else /}Please login before submit!{/if}"
    >
        <button type="button" class="btn btn-outline-secondary" title="无法提交 (Could Not Submit)" >提交<span class="en-text">Submit Page</span></button>
    </a>
    {/if}
    {if ($contestStatus == 2) && ($OJ_MODE == 'online' || IsAdmin('administrator')) }
    &nbsp;&nbsp;
    <a href="__OJ__/problemset/summary?pid={$problem['problem_id']}" class="a_noline">
        <button type="button" class="btn btn-outline-info">统计<span class="en-text">Summary</span></button>
    </a>
    &nbsp;&nbsp;
    <a href="{$GIT_DISCUSSION}?discussions_q={$problem['problem_id']}" class="a_noline" target="_blank">
        <button type="button" class="btn btn-outline-info">讨论<span class="en-text">Discussion</span></button>
    </a>
    {/if}
    {if $OJ_STATUS=='exp' && ($ALLOW_TEST_DOWNLOAD || IsAdmin()) }
    &nbsp;&nbsp;
    <a href="/{$module}/{$controller}/testdata?cid={$contest['contest_id']}&pid={$apid}" class="a_noline">
        <button type="button" class="btn btn-outline-warning">测试数据<span class="en-text">TestData</span></button>
    </a>
    {/if}
{else/}
    {if session('?user_id') }
        <a href="__OJ__/problemset/submit?pid={$problem['problem_id']}" class="a_noline">
            <button type="button" class="btn btn-outline-primary">提交<span class="en-text">Submit Page</span></button>
        </a>
        {else /}
        <a href="javascript:void(null)" class='disabled_problem_submit_button a_noline'>
            <button type="button" class="btn btn-outline-secondary" title="Please login before submit!" >提交<span class="en-text">Submit Page</span></button>
        </a>
    {/if}
    &nbsp;&nbsp;
    <a href="__OJ__/problemset/summary?pid={$problem['problem_id']}" class="a_noline">
        <button type="button" class="btn btn-outline-info">统计<span class="en-text">Summary</span></button>
    </a>
    &nbsp;&nbsp;
    <a href="{$GIT_DISCUSSION}?discussions_q={$problem['problem_id']}" class="a_noline" target="_blank">
        <button type="button" class="btn btn-outline-info">讨论<span class="en-text">Discussion</span></button>
    </a>
{/if}
