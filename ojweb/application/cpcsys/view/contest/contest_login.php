<!-- 标准比赛登录框模板 -->
{if $needAuth /}
{if $isCollectMode /}
<!-- 收集模式界面 -->
<div class="alert alert-danger border-danger mb-3" style="background-color: #f8d7da; border-left: 4px solid #dc3545 !important;">
    <div class="d-flex align-items-start">
        <i class="bi bi-exclamation-triangle-fill me-2" style="color: #dc3545; font-size: 1.2rem;"></i>
        <div>
            <strong style="color: #721c24;">此界面为赛务工作界面，如果您是选手，请勿操作，并联系管理员处理</strong>
            <div class="en-text" style="color: #721c24; font-size: 0.9em; margin-top: 0.25rem;">
                <strong>This is a contest management interface. If you are a contestant, please do not operate and contact the administrator.</strong>
            </div>
        </div>
    </div>
</div>
<div class="alert alert-info d-flex flex-column gap-3">
    <div class="d-flex align-items-center">
        <i class="bi bi-person-plus me-2"></i>
        <span>账号收集<span class="en-text">Account Collection</span></span>
    </div>
    
    <form id="contest_collect_form" method='post' action="/{$module}/contest/contest_collect_team_ajax" class="d-flex flex-column gap-3">
        <input type="hidden" name="cid" value="{$contest['contest_id']}">
        
        <div class="d-flex flex-column gap-2">
            <label class="form-label">
                <span>请输入队伍ID<span class="en-text">Please enter Team ID</span></span>
            </label>
            <input type="text" id="collect_team_id" name="team_id" class="form-control" placeholder="队伍ID(Team ID)" required autofocus style="max-width: 300px;" />
        </div>
        
        {if $team_id_bind != null /}
        <div class="alert alert-success">
            <span>已登记队伍：<strong>{$team_id_bind}</strong><span class="en-text">Registered Team: <strong>{$team_id_bind}</strong></span></span>
        </div>
        {/if}
        
        <div class="d-flex align-items-center gap-2">
            <button type="submit" id="collect_submit_button" class="btn btn-primary">提交<span class="en-text">Submit</span></button>
        </div>
        
        <div class="text-muted small">
            <span>当前IP：<code>{$client_ip}</code><span class="en-text">Current IP: <code>{$client_ip}</code></span></span>
        </div>
    </form>
</div>
{else /}
<!-- 正常登录界面 -->
<div class="alert alert-warning d-flex flex-column gap-3">
    <div class="d-flex align-items-center">
        <i class="bi bi-people me-2"></i>
        <span>请登录比赛账号<span class="en-text">You need to log in with the contest account.</span></span>
    </div>
    
    <form id="contest_auth_form" method='post' action="/{$module}/contest/contest_auth_ajax" class="d-flex align-items-start gap-3">
        <input type="hidden" class="form-control" name="cid" value="{$contest['contest_id']}">
        
        <div class="d-flex flex-column gap-2">
            <input type="text" id="cpc_team_id" name="team_id" class="form-control cpc_login" placeholder="队伍ID(Team ID)" required autofocus title="请输入队伍ID" style="width: 150px; height: 38px;" />
            <input type="password" id="cpc_password" name="password" class="form-control cpc_login" placeholder="密码(Password)" required title="请输入密码" style="width: 150px; height: 38px;">
        </div>
        
        <div class="d-flex flex-column gap-2 align-items-start">
            <button type="submit" id="submit_contest_logon_button" class="btn btn-primary" style="height: 38px; width: 150px;">提交<span class="en-text">Submit</span></button>
            {if $team_id_bind != null /}
            <button type="button" id="passwordless_login_btn" class="btn btn-success" style="height: 38px; width: 150px;" title="本机已绑定账号，可以免密一键登录 / This device is bound to an account, you can log in with one click without password">免密登录<span class="en-text">Passwordless Login</span></button>
            {/if}
        </div>
    </form>
</div>
{/if}
<script type="text/javascript">
    // 传递模板变量给JavaScript（两种模式都需要）
    window.CONTEST_AUTH_CONFIG = {
        redirectUrl: "/{$module}/contest/contest_auth_ajax",
        passwordlessUrl: "/{$module}/contest/contest_auth_passwordless_ajax",
        collectUrl: "/{$module}/contest/contest_collect_team_ajax",
        cid: "{$contest['contest_id']}"
    };
</script>
{js href="__STATIC__/csgoj/contest/contest_auth.js" /}
{/if}
