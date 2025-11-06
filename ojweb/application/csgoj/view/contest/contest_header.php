<div class="contest-header-container" style="display:flex; flex-direction: row; width:100%; position: relative;">
    <div style="flex-grow: 1;">
        <div>
            <h1 class="contest-title">{$contest['contest_id']}: {$contest['title']}</h1>
            <div class="form-text contest-info">
                <span class="inline_span">
                    <span class="bilingual-inline">开始时间<span class="en-text">Start Time</span></span>
                    <div class="text-info" id="start_time_span">{$contest['start_time']}</div>
                </span>
                <span class="inline_span">
                    <span class="bilingual-inline">结束时间<span class="en-text">End Time</span></span>
                    <div class="text-info" id="end_time_span">{$contest['end_time']}</div>
                </span>
                <span class="inline_span">
                    <span class="bilingual-inline">当前时间<span class="en-text">Current Time</span></span>
                    <div id="current_time_div" time_stamp=<?php echo microtime(true); ?>>{$now}</div>
                </span>
                <span class="inline_span">
                    <span class="bilingual-inline">比赛状态<span class="en-text">Contest Status</span></span>
                    <div id="contest_status_display" class="bilingual-inline">
                        <!-- 状态将通过 JavaScript 动态更新 -->
                    </div>
                </span>
                <span class="inline_span">
                    <span class="bilingual-inline">比赛类型<span class="en-text">Contest Type</span></span>
                    <div class="bilingual-inline">
                        {if $contest['private'] % 10 == 1}<span class="text-info">私有<span class="en-text">Private</span></span>
                        {elseif $contest['private'] % 10 == 2}<span class="text-primary">标准<span class="en-text">Standard</span></span>
                        {elseif $contest['password'] != null && strlen($contest['password']) > 0 /}<span class="text-warning">加密<span class="en-text">Encrypted</span></span>
                        {else /}<span class="text-success">公开<span class="en-text">Public</span></span>
                        {/if}
                    </div>
                </span>
                <span class="inline_span">
                    <span class="bilingual-inline">榜单状态<span class="en-text">Rank Status</span></span>
                    <div id="rank_status_display" class="bilingual-inline">
                        <!-- 状态将通过 JavaScript 动态更新 -->
                    </div>
                </span>
            </div>
        </div>
        <ul class="nav nav-tabs" id="contest_menu">
            <!-- 比赛首页 -->
            <li class="nav-item" {if $action=='contest'}class="nav-item"{/if}>
                <a class="nav-link{if $action=='contest'} active{/if}" href="/{$module}/{$contest_controller}/contest?cid={$contest['contest_id']}" title="比赛首页">
                    <span class="cn-text"><i class="bi bi-house-door"></i>首 </span><span class="en-text">Index</span>
                </a>
            </li>
            
            <!-- 题目列表 -->
            {if $isContestAdmin || !isset($balloonSender) || !$balloonSender}
                {if $canJoin==true && $contestStatus > -1 && (!isset($isContestStaff) || !$isContestStaff) || (isset($proctorAdmin) && $proctorAdmin) || $isContestAdmin}
                    <li class="nav-item">
                        <a class="nav-link{if strpos($action, 'problem')===0} active{/if}" href="/{$module}/{$contest_controller}/problemset?cid={$contest['contest_id']}" title="题目">
                            <span class="cn-text"><i class="bi bi-list-task"></i>题 </span><span class="en-text">Problems</span>
                        </a>
                    </li>
                {/if}
            {/if}
            
            <!-- 比赛进行中的菜单 -->
            {if $contestStatus > -1}
                <!-- 评测状态 -->
                {if $canJoin}
                    <li class="nav-item">
                        <a class="nav-link{if strpos($action, 'status')===0} active{/if}" href="/{$module}/{$contest_controller}/status?cid={$contest['contest_id']}{if !$isContestAdmin && !IsAdmin('source_browser') && (!isset($proctorAdmin) || !$proctorAdmin)}#user_id={$contest_user}{/if}" title="评测状态">
                            <span class="cn-text"><i class="bi bi-clock-history"></i>测 </span><span class="en-text">Status</span>
                        </a>
                    </li>
                {/if}
                
                <!-- 排行榜 -->
                <li class="nav-item">
                    <a class="nav-link{if $action=='rank'} active{/if}" href="/{$module}/{$contest_controller}/rank?cid={$contest['contest_id']}" title="排名">
                        <span class="cn-text"><i class="bi bi-trophy"></i>榜 </span><span class="en-text">Ranklist</span>
                    </a>
                </li>
                
                <!-- 参赛者专用菜单 -->
                {if $canJoin==true}
                    <!-- 通知消息 -->
                    {if $controller == 'contest' && !$isContestAdmin}
                        <li class="nav-item">
                            <a class="nav-link" href="#" id="show_msg_btn" title="通知">
                                <i class="bi bi-bell"></i>通(<span id="msg_num">0</span>) <span class="en-text">Message</span>
                            </a>
                        </li>
                    {/if}
                    
                    <!-- 提问菜单 -->
                    {if $isContestAdmin || $contest_user}
                        {include file="../../csgoj/view/contest/topic_menu" /}
                    {/if}
                {/if}
            {/if}
            
            <!-- CPCSYS模块专用菜单 -->
            {if $module=='cpcsys' && ($isContestAdmin || $contest_user)}
                <!-- 打印菜单 -->
                {if $contestStatus > -1 || $isContestAdmin || $printManager}
                    {include file="../../cpcsys/view/contest/print_menu" /}
                {/if}
                
                <!-- 气球管理 -->
                {if $balloonManager || $isContestAdmin}
                    <!-- 有多个菜单项，使用下拉菜单 -->
                    <li class="nav-item dropdown {if $action=='balloon' || $action=='balloon_queue'} active {/if}" title="气球管理">
                        <a class="nav-link dropdown-toggle{if $action=='balloon' || $action=='balloon_queue'} active{/if}" href="#" id="balloonDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="cn-text"><i class="bi bi-balloon"></i>球 </span><span class="en-text">Balloon</span>
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="balloonDropdown">
                            <li>
                                <a class="dropdown-item{if $action=='balloon'} active{/if}" href="/{$module}/{$contest_controller}/balloon_manager?cid={$contest['contest_id']}" title="气球总览">
                                    <span class="cn-text"><i class="bi bi-balloon me-2"></i> 气球总览 </span><span class="en-text">Balloon Manager</span>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item{if $action=='balloon_queue'} active{/if}" href="/{$module}/{$contest_controller}/balloon_queue?cid={$contest['contest_id']}"  title="气球队列">
                                    <span class="cn-text"><i class="bi bi-list-check me-2"></i> 气球队列 </span><span class="en-text">Balloon Queue</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                {elseif $balloonSender}
                    <!-- 只有气球队列，直接显示 -->
                    <li class="nav-item">
                        <a class="nav-link{if $action=='balloon_queue'} active{/if}" href="/{$module}/{$contest_controller}/balloon_queue?cid={$contest['contest_id']}" target="_blank" title="气球队列">
                            <span class="cn-text"><i class="bi bi-balloon"></i>球 </span><span class="en-text">Balloon</span>
                        </a>
                    </li>
                {/if}
                
                <!-- 直播功能 -->
                {if isset($watcherUser) && $watcherUser}
                    <li class="nav-item">
                        <a class="nav-link" href="/ojtool/contestlive/ctrl?cid={$contest['contest_id']}" target="_blank" title="直播">
                            <span class="cn-text"><i class="bi bi-broadcast"></i>播 </span><span class="en-text">Live</span>
                        </a>
                    </li>
                {/if}
            {/if}
            
            <!-- 管理员菜单 -->
            {if $isContestAdmin || isset($proctorAdmin) && $proctorAdmin}
                <li class="nav-item" id="contest_admin" cid="{$contest['contest_id']}">
                    <a class="nav-link{if $controller=='admin'} active{/if}" href="/{$module}/admin?cid={$contest['contest_id']}" title="比赛管理">
                        <span class="cn-text"><i class="bi bi-gear"></i>管 </span><span class="en-text">Admin</span>
                    </a>
                </li>
            {/if}
        </ul>
    </div>
    
    <!-- 右上角队伍信息面板 -->
    <div class="contest-header-actions-container" style="position: absolute; top: 0; right: 0; z-index: 1000;">
        {include file="../../csgoj/view/contest/team_info_panel" /}
    </div>
</div>

<!-- 比赛消息通知 -->
{if $canJoin && !$isContestAdmin}
    {include file="../../csgoj/view/contest/contest_msg_timing" /}
{/if}


<script type="text/javascript">
    // 传递模板变量给JavaScript
    window.contestHeaderConfig = {
        module: "{$module}",
        contest_controller: "{$contest_controller}",
        contest_id: "{$contest['contest_id']}",
        contest_status: "{$contestStatus}",
        rank_frozen: "{$rankFrozen ? 'true' : 'false'}",
        action: "{$action}",
        is_contest_admin: "{$isContestAdmin ? 'true' : 'false'}",
        proctor_admin: "{(isset($proctorAdmin) && $proctorAdmin) ? 'true' : 'false'}",
        // 比赛时间信息
        start_time: "{$contest['start_time']}",
        end_time: "{$contest['end_time']}",
        frozen_minute: "{$contest['frozen_minute']}",
        frozen_after: "{$contest['frozen_after']}",
        // 当前时间戳（用于计算时间差）
        current_timestamp: <?php echo microtime(true); ?>
    };
</script>
{css href="__STATIC__/csgoj/contest/contest_header.css" /}
{js href="__STATIC__/csgoj/contest/contest_header.js" /}