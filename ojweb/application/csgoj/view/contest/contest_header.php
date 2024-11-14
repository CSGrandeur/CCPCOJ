<div style="display:flex; flex-direction: row; width:100%;">
    <div style="flex-grow: 1;">
        <div>
            <h1>{$contest['contest_id']}: {$contest['title']}</h1>
            <p class="help-block">
                <span class="inline_span">Start Time：<span class="inline_span text-danger" id="start_time_span">{$contest['start_time']}</span></span>&nbsp;&nbsp;
                <span class="inline_span">End Time：<span class="inline_span text-danger" id="end_time_span">{$contest['end_time']}</span></span>&nbsp;&nbsp;
                <span class="inline_span">Current Time：<span class="inline_span text-info" id="current_time_div" time_stamp=<?php echo microtime(true); ?>>{$now}</span></span>&nbsp;&nbsp;
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
                {default /} <strong class="text-info">Ended</strong>
                {/switch}
                {if($rankFrozen == true)}
                &nbsp;&nbsp;<strong class="text text-info">Rank Frozen</strong>&nbsp;&nbsp;&nbsp;&nbsp;
                {/if}
            </p>
        </div>
        <ul class="nav nav-tabs" id="contest_menu">
            <li role="presentation" {if $action=='contest' } class="active" {/if}><a href="/{$module}/{$contest_controller}/contest?cid={$contest['contest_id']}">比赛首页<br /><span class="en-text">Index</span></a></li>
            {if $isContestAdmin || !isset($balloonSender) || !$balloonSender }
            {if $canJoin==true && $contestStatus > -1 || $isContestAdmin }
            <li role="presentation" {if strpos($action, 'problem' )===0} class="active" {/if}><a href="/{$module}/{$contest_controller}/problemset?cid={$contest['contest_id']}">题目<br /><span class="en-text">Problems</span></a></li>
            {/if}
            {if $contestStatus > -1}
            {if $canJoin}
            <li role="presentation" {if strpos($action, 'status' )===0} class="active" {/if}>
                <a href="/{$module}/{$contest_controller}/status?cid={$contest['contest_id']}{if !$isContestAdmin && !IsAdmin('source_browser') && (!isset($proctorAdmin) || !$proctorAdmin) }#user_id={$contest_user}{/if}">
                    评测状态<br /><span class="en-text">Status</span>
                </a>
            </li>
            {/if}
            <li role="presentation" {if $action=='ranklist' } class="active" {/if}><a href="/{$module}/{$contest_controller}/ranklist?cid={$contest['contest_id']}">排名<br /><span class="en-text">Ranklist</span></a></li>
            {if $OJ_OPEN_OI && ($OJ_STATUS != 'exp' || $contest['private'] % 10 == 2) }
            <li role="presentation" {if $action=='scorerank' } class="active" {/if}><a href="/{$module}/{$contest_controller}/scorerank?cid={$contest['contest_id']}">分数<br /><span class="en-text">Score Rank</span></a></li>
            {else /}
            <li role="presentation" {if $action=='schoolrank' } class="active" {/if}><a href="/{$module}/{$contest_controller}/schoolrank?cid={$contest['contest_id']}">学校排名<br /><span class="en-text">School Rank</span></a></li>
            {/if}
            {if $canJoin==true}
            {if $controller == 'contest' && !$isContestAdmin }
            <li role="presentation"><a href="#" id="show_msg_btn">通知(<span id="msg_num">0</span>)<br /><span class="en-text">Message</span></a></li>
            {/if}
            <li role="presentation" {if strpos($action, 'statistic' )===0 } class="active" {/if}><a href="/{$module}/{$contest_controller}/statistics?cid={$contest['contest_id']}">统计<br /><span class="en-text">Statistics</span></a></li>
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
            <li role="presentation" {if $action=='balloon' } class="active" {/if}><a href="/{$module}/{$contest_controller}/balloon?cid={$contest['contest_id']}">气球总览<br /><span class="en-text">Balloon</span></a></li>
            {elseif $balloonSender}
            <li role="presentation" {if $action=='balloon_queue' } class="active" {/if}><a href="/{$module}/{$contest_controller}/balloon_queue?cid={$contest['contest_id']}" target="_blank">气球任务<br /><span class="en-text">BalloonQue</span></a></li>
            {/if}
            {if isset($watcherUser) && $watcherUser }
            <li role="presentation"><a href="/ojtool/contestlive/ctrl?cid={$contest['contest_id']}" target="_blank">直播<br /><span class="en-text">Live</span></a></li>
            {/if}
            {/if}
            {if $isContestAdmin || isset($proctorAdmin) && $proctorAdmin }
            <li role="presentation" id="contest_admin" cid="{$contest['contest_id']}" {if $controller=='admin' }class="active" {/if}><a href="/{$module}/admin?cid={$contest['contest_id']}">比赛管理<br /><span class="en-text">Admin</span></a></li>
            {/if}
        </ul>
    </div>
    {include file="../../csgoj/view/contest/team_info_panel" /}
</div>

{if $canJoin && !$isContestAdmin }
{include file="../../csgoj/view/contest/contest_msg_timing" /}
{/if}


<script type="text/javascript">
    var diff = new Date($('#current_time_div').attr('time_stamp') * 1000).getTime() - new Date().getTime();

    function str0(a) {
        if (a < 10) return "0" + a;
        else return a;
    }

    function clock() {
        var h, m, s, n, y, mon, d;
        var x = new Date(new Date().getTime() + diff);
        y = x.getFullYear();
        mon = str0(x.getMonth() + 1);
        d = str0(x.getDate());
        h = str0(x.getHours());
        m = str0(x.getMinutes());
        s = str0(x.getSeconds());
        n = y + '-' + mon + '-' + d + ' ' + h + ':' + m + ':' + s;
        $('#current_time_div').text(n);
        setTimeout("clock()", 1000);
    }
    $(document).ready(function() {
        clock();
    });
</script>

{if $isContestAdmin || isset($proctorAdmin) && $proctorAdmin}
<script type="text/javascript">
    function GetTopicNum() {
        if (document.visibilityState !== 'visible') {
            return;
        }
        let tmp_module = "<?php echo $module ?>";
        let tmp_cid = "<?php echo $contest['contest_id'] ?>";
        let action_page = "<?php echo $action ?>";
        $.get(`/${tmp_module}/contest/topic_num_ajax?cid=${tmp_cid}`, function(rep) {
            if (rep.code == 1) {
                const topic_num_key = `topic_num#cid${tmp_cid}`;
                const reply_num_key = `topic_reply#cid${tmp_cid}`;
                const topic_num_cache = csg.store(topic_num_key);
                const reply_num_cache = csg.store(reply_num_key);
                const flg_new_topic = topic_num_cache === null && rep.data?.count || topic_num_cache !== null && rep.data?.count && rep.data.count > topic_num_cache;
                const flg_new_reply = reply_num_cache === null && rep.data?.reply_sum || reply_num_cache !== null && rep.data?.reply_sum && rep.data.reply_sum > reply_num_cache;
                if (flg_new_topic || flg_new_reply) {
                    // alertify.warning("有新提问 / 回复");
                    $('#topic_num').html(`(<strong style="color:red;" 
                            title="${flg_new_topic ? '有新提问 / New Topic' : ''}${flg_new_reply ? '有新回复 / New Reply' : ''}">
                            ${rep.data.count}</strong>)`);
                } else {
                    $('#topic_num').text(rep.data?.count ? `(${rep.data.count})` : '');
                }
                if(action_page == 'topic_list') {
                    // 确保管理员查看了 topic_list 页面，消除高亮显示（更新提问/回复数）
                    if (rep.data?.count !== undefined && rep.data?.count !== null) {
                        csg.store(topic_num_key, rep.data.count);
                    }
                    if(rep.data?.reply_sum !== undefined && rep.data?.reply_sum !== null) {
                        csg.store(reply_num_key, rep.data.reply_sum);
                    }
                }                
            }
        });
    }
    $(document).ready(function() {
        GetTopicNum();
        setInterval(GetTopicNum, 30000);

        let contest_export = $('#contest_export');
        contest_export.on('click', function() {
            alertify.confirm("Confirm to export?",
                function() {
                    $.ajax({
                        url: '/{$module}/{$contest_controller}/contest_export',
                        data: {
                            'cid': contest_export.attr('cid')
                        },
                        success: function(ret) {
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
                function() {
                    alertify.message("Canceled");
                }
            );

        });
    });
</script>
{/if}

<style>
</style>