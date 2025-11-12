{if isset($login_teaminfo) && $login_teaminfo}

<div class="contest-header-actions">
    <!-- 队伍信息切换按钮 -->
    <button class="btn btn-outline-secondary btn-sm team-info-toggle" id="team_info_toggle" 
            title="队伍信息 / Team Information">
        <i class="bi bi-person-circle"></i>
    </button>
    
    <!-- 登出按钮 -->
    <a href="#" class="btn btn-outline-danger btn-sm contest-logout-btn" id="contest_logout_button"
       title="登出 / Logout">
        <i class="bi bi-box-arrow-right"></i>
    </a>
    
    <!-- 队伍信息展开面板 -->
    <div id="team_info_panel" class="card position-absolute" style="top: 0; right: 0; width: 300px; max-width: 90vw; z-index: 1001; display: none;">
        <div class="card-header d-flex align-items-center" style="height: 40px; padding: 0 1rem;">
            <h5 class="card-title mb-0" style="font-size: 0.875rem;">队伍面板 <span class="en-text">Team Panel</span></h5>
        </div>
        <div class="card-body" style="padding: 0.75rem;">
            <!-- 队伍ID -->
            <div class="team-info-item" title="队伍ID / Team ID: {$login_teaminfo['team_id']}">
                <i class="bi bi-hash text-primary"></i>
                <div class="item-content">
                    <div class="main-text">{$login_teaminfo['team_id']}</div>
                </div>
            </div>
            
            <!-- 队伍名称 -->
            <div class="team-info-item" title="队伍名称 / Team Name: {$login_teaminfo['name']|htmlspecialchars}{if isset($login_teaminfo['name_en']) && $login_teaminfo['name_en']} | 副语言队名 / Secondary Language: {$login_teaminfo['name_en']|htmlspecialchars}{/if}">
                <i class="bi bi-flag-fill text-success"></i>
                <div class="item-content">
                    <div class="main-text">{$login_teaminfo['name']}</div>
                    {if isset($login_teaminfo['name_en']) && $login_teaminfo['name_en']}
                    <div class="sub-text">{$login_teaminfo['name_en']}</div>
                    {/if}
                </div>
            </div>
            
            <!-- 学校 -->
            <div class="team-info-item" title="学校 / School: {$login_teaminfo['school']|htmlspecialchars}">
                <i class="bi bi-building text-info"></i>
                <div class="item-content">
                    <div class="main-text">{$login_teaminfo['school']}</div>
                </div>
            </div>
            
            <!-- 机房/区域 -->
            {if $login_teaminfo['room']}
            <div class="team-info-item" title="机房/区域 / Room/Zone: {$login_teaminfo['room']}">
                <i class="bi bi-geo-alt text-warning"></i>
                <div class="item-content">
                    <div class="main-text">{$login_teaminfo['room']}</div>
                </div>
            </div>
            {/if}
            
            <!-- 教练信息 -->
            {if $login_teaminfo['coach']}
            <div class="team-info-item" title="教练 / Coach: {$login_teaminfo['coach']}">
                <i class="bi bi-person-badge text-secondary"></i>
                <div class="item-content">
                    <div class="main-text">{$login_teaminfo['coach']}</div>
                </div>
            </div>
            {/if}
            
            <!-- 选手信息 -->
            <div class="team-info-item" title="选手 / Players: {$login_teaminfo['tmember']}">
                <i class="bi bi-people text-secondary"></i>
                <div class="item-content">
                    <div class="main-text">{$login_teaminfo['tmember']}</div>
                </div>
            </div>
            
            {if !isset($isContestStaff) || !$isContestStaff }
            <!-- 成绩信息 -->
            <div class="team-info-item team-score-section">
                <div class="item-content team-score-content">
                    {include file="../../csgoj/view/contest/team_score_panel" /}
                </div>
            </div>
            {/if}
        </div>
    </div>
</div>
<script>
    window.TEAM_INFO_PANEL_CONFIG = {
        key: '<?php echo $contest['contest_id']; ?>',
        contest_id: '<?php echo $contest['contest_id']; ?>',
        cid_list: '<?php echo $contest['contest_id']; ?>',
        api_url: '/<?php echo $module; ?>/contest/contest_data_ajax',
        module: '<?php echo $module; ?>',
        rank_mode: "team",
        flg_rank_cache: true,
        team_id: '<?php echo $login_teaminfo['team_id']; ?>',
    };
</script>
{include file="../../csgoj/view/public/js_rank"}
{css file="__STATIC__/csgoj/contest/tinfo_panel.css" /}
{js file="__STATIC__/csgoj/contest/tinfo_panel.js" /}
{/if}