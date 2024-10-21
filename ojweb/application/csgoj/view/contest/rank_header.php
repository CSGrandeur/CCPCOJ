{include file="../../csgoj/view/public/bootstrap_select" /}
{include file="../../csgoj/view/public/js_identicon" /}
{js href='__STATIC__/ojtool/js/rank_tool.js' /}
<div id="rank_div">
<div id="ranklist_toobar">
    <div class="form-inline" role="form">
        {if $action == "balloon" }
        <button class="btn btn-default" type="button" aria-label="Refresh" id="balloon_refresh"><i class="glyphicon glyphicon-refresh icon-refresh"></i> </button>
        {/if}

        <button class="btn btn-warning" type="button" id="rank_fullscreen_btn" title="网页全屏(Full Window)"><span id="rank_fullscreen_span" class="glyphicon glyphicon-fullscreen"></span></button>
        {if $action == 'ranklist' || $action == 'schoolrank' }
        <div class="form-group">
            <a class="btn btn-info" href="/ojtool/rankdynamic/{if $action == 'ranklist'}rank{else /}schoolrank{/if}?cid={$contest['contest_id']}" target="_blank" title="动态榜(Dynamic Rank)"><i class="glyphicon glyphicon-th-list"></i></a>
        </div>
        {/if}
        <div class="form-group">
            <label for="auto_refresh_box">Auto(<strong class="text-info" id="auto_refresh_time">20</strong>s): </label>
            <input type="checkbox" id="auto_refresh_box" data-size="small" name="auto_refresh" >
            &nbsp;&nbsp;
        </div>
        {if $action == 'ranklist' || $action == 'schoolrank' }
        <div class="form-group">
            <label for="school_filter">School Filter: </label>
            <select id="school_filter" name="school_filter" class="selectpicker" data-live-search="true" multiple="multiple">

            </select>
        </div>
        <div class="btn-group btn-group-sm" role="group" aria-label="test">
            <button type="button" class="btn btn-success" id="school_filter_all">A</button>
            <button type="button" class="btn btn-warning" id="school_filter_none">N</button>
        </div>
        {/if}
        {if $action == 'ranklist' && $module == 'cpcsys'}
        <div class="form-group">
            <label for="tkind_filter">Tkind Filter: </label>
            <select id="tkind_filter" name="tkind_filter" class="selectpicker" data-live-search="true" multiple="multiple">
     
            </select>
        </div>
        <div class="btn-group btn-group-sm" role="group" aria-label="test">
            <button type="button" class="btn btn-success" id="tkind_filter_all">A</button>
            <button type="button" class="btn btn-warning" id="tkind_filter_none">N</button>
        </div>
        {/if}
        {if $action == "balloon" }
        <span>Wating: <strong class="text-danger" id="balloon_waiting_num_span" style="font-size: 18px;">1</strong>&nbsp;&nbsp;</span>
        <span>Assigned: <strong class="text-danger" id="balloon_assign_num_span" style="font-size: 18px;">0</strong>&nbsp;&nbsp;</span>
        <strong class="text-warning task_assign">Task Assign Mode</strong>
        <strong class="text-primary task_finish" style="display:none">Task Finish Mode</strong>
        {/if}
        <!-- <div class="form-group">
            <label for="fb_include_star_box">FB with Star：</label>
            <input type="checkbox" id="fb_include_star_box" data-size="small" name="fb_include_star" >
            &nbsp;&nbsp;
        </div> -->
    </div>
</div>
<input type="hidden" id="page_info_input" cid="{$contest['contest_id']}" ckind="{$module}" contest_user="{$contest_user}" controller="{$controller}" module="{$module}" />
<script type="text/javascript">
    let page_info_input = $('#page_info_input');
    let cid = parseInt(page_info_input.attr('cid'));
    let ckind = page_info_input.attr('ckind');  // OJ目前所处模式
    let oj_controller = page_info_input.attr('controller');
    let oj_module = page_info_input.attr('module');
    let contest_user = page_info_input.attr('contest_user');
</script>