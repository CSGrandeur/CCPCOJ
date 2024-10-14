<h1>{$contest['contest_id']}: {$contest['title']}</h1>
<p class="help-block">
    <span class="inline_span">Start Time：<span class="inline_span text-danger" id="start_time_span">{$contest['start_time']}</span></span>&nbsp;&nbsp;
    <span class="inline_span">End Time：<span class="inline_span text-danger" id="end_time_span">{$contest['end_time']}</span></span>&nbsp;&nbsp;
    <span class="inline_span">Current Time：<span class="inline_span text-info" id="current_time_div" time_stamp=<?php echo microtime(true); ?> >{$now}</span></span>&nbsp;&nbsp;
    {if $contest['private'] % 10 == 1}
        <label class="label label-primary">Private</label>
    {elseif $contest['private'] % 10 == 2}
        <span class="label label-info">Standard</span>
    {elseif $contest['password'] != null && strlen($contest['password']) > 0 /}
        <span class="label label-warning">Encrypted</span>
    {else /}
        <span class="label label-success">Public</span>
    {/if}&nbsp;&nbsp;
    {switch name="contestStatus" }
    {case value="-1"}<strong class="text-success">Not Started</strong>{/case}
    {case value="1"}<strong class="text-danger">Running</strong>{/case}
    {default /}    <strong class="text-info">Ended</strong>
    {/switch}
    {if($rankFrozen == true)}
    &nbsp;&nbsp;<strong class="text text-info">Rank Frozen</strong>&nbsp;&nbsp;&nbsp;&nbsp;
    {/if}

</p>
<ul class="nav nav-tabs">
    <li role="presentation" {if $action == 'contest' } class="active" {/if}><a href="/{$module}/{$contest_controller}/contest?cid={$contest['contest_id']}">比赛首页<br/>Index</a></li>
    {if $isContestAdmin || !isset($balloonSender) || !$balloonSender }
        {if $canJoin==true && $contestStatus > -1 || $isContestAdmin }
        <li role="presentation" {if strpos($action, 'problem') === 0} class="active" {/if}><a href="/{$module}/{$contest_controller}/problemset?cid={$contest['contest_id']}">题目<br/>Problems</a></li>
        {/if}
        {if $contestStatus > -1}
            {if $canJoin}
            <li role="presentation" {if strpos($action, 'status') === 0} class="active" {/if}><a href="/{$module}/{$contest_controller}/status?cid={$contest['contest_id']}{if !$isContestAdmin && !IsAdmin('source_browser') }#user_id={$contest_user}{/if}">评测状态<br/>Status</a></li>
            {/if}
        <li role="presentation" {if $action == 'ranklist'} class="active" {/if}><a href="/{$module}/{$contest_controller}/ranklist?cid={$contest['contest_id']}">排名<br/>Ranklist</a></li>
            {if $OJ_OPEN_OI && ($OJ_STATUS != 'exp' || $contest['private'] % 10 == 2)  }
            <li role="presentation" {if $action == 'scorerank'} class="active" {/if}><a href="/{$module}/{$contest_controller}/scorerank?cid={$contest['contest_id']}">分数<br/>Score Rank</a></li>
            {else /}
            <li role="presentation" {if $action == 'schoolrank'} class="active" {/if}><a href="/{$module}/{$contest_controller}/schoolrank?cid={$contest['contest_id']}">学校排名<br/>School Rank</a></li>
            {/if}
            {if $canJoin==true}
            {if $controller == 'contest'}
            <li role="presentation" {if strpos($action, 'statistic') === 0 } class="active" {/if}><a href="#" id="show_msg_btn">通知(<span id="msg_num">0</span>)<br/>Message</a></li>
            {/if}
            <li role="presentation" {if strpos($action, 'statistic') === 0 } class="active" {/if}><a href="/{$module}/{$contest_controller}/statistics?cid={$contest['contest_id']}">统计<br/>Statistics</a></li>
            {if $isContestAdmin || $contest_user }
                {include file="../../csgoj/view/contest/topic_menu" /}
            {/if}
            {/if}
        {/if}
    {/if}
    {if $module=='cpcsys' && ($isContestAdmin || $contest_user) }
        {if $contestStatus > -1 || $isContestAdmin || $printManager }
            {include file="../../cpcsys/view/contest/print_menu" /}
        {/if}
        {if $balloonManager || $isContestAdmin }
            <li role="presentation" {if $action == 'balloon'} class="active" {/if}><a href="/{$module}/{$contest_controller}/balloon?cid={$contest['contest_id']}">气球管理<br/>Balloon</a></li>
        {elseif $balloonSender}
            <li role="presentation" {if $action == 'balloon_queue'} class="active" {/if}><a href="/{$module}/{$contest_controller}/balloon_queue?cid={$contest['contest_id']}" target="_blank">气球任务<br/>BalloonQue</a></li>
        {/if}
        {if isset($watcherUser) && $watcherUser }
        <li role="presentation"><a href="/ojtool/contestlive/ctrl?cid={$contest['contest_id']}" target="_blank">直播<br/>Live</a></li>
        {/if}
    {/if}
    {if $isContestAdmin || isset($proctorAdmin) && $proctorAdmin }
        <li role="presentation" id="contest_admin" cid="{$contest['contest_id']}" {if $controller == 'admin'}class="active" {/if}><a href="/{$module}/admin?cid={$contest['contest_id']}">比赛管理<br/>Admin</a></li>
    {/if}
    
</ul>

{if $canJoin }
{include file="../../csgoj/view/contest/contest_msg_timing" /}
{/if}

<script type="text/javascript">
    var diff = new Date($('#current_time_div').attr('time_stamp') * 1000).getTime()-new Date().getTime();
    function str0(a)
    {
        if(a<10) return "0" + a;
        else return a;
    }
    function clock()
    {
        var h,m,s,n,y,mon,d;
        var x = new Date(new Date().getTime()+diff);
        y     =    x.getFullYear();
        mon    =    str0(x.getMonth()+1);
        d    =    str0(x.getDate());
        h    =    str0(x.getHours());
        m    =    str0(x.getMinutes());
        s    =    str0(x.getSeconds());
        n    =    y + '-' + mon + '-' + d + ' ' + h + ':' + m + ':' + s;
        $('#current_time_div').text(n);
        setTimeout("clock()",1000);
    }
    $(document).ready(function(){
        clock();
    });
</script>

{if $isContestAdmin}
<script type="text/javascript">
$(document).ready(function(){
    let contest_export = $('#contest_export');
    contest_export.on('click', function(){
        alertify.confirm("Confirm to export?",
            function(){
                $.ajax({
                    url: '/{$module}/{$contest_controller}/contest_export',
                    data: {
                        'cid': contest_export.attr('cid')
                    },
                    success: function(ret)
                    {
                        let blob = new Blob([ret['data']], {
                            type: "text/plain;charset=utf-8"
                        });
                        let reader = new FileReader();
                        reader.readAsDataURL(blob);
                        reader.onload = function(e) {
                            let a = document.createElement('a');
                            a.download = contest_export.attr('cid') + "_exported.md";
                            a.href = e.target.result;
                            document.body.appendChild(a);
                            a.click();
                            document.body.removeChild(a);
                        }
                    },
                    dataType: 'json',
                    type: 'post'
                });
            },
            function(){
                alertify.message("Canceled");
            }
        );
        
    });
})
</script>
{/if}