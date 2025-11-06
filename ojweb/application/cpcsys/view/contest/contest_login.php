<!-- 标准比赛登录框模板 -->
{if $needAuth /}
<div class="alert alert-warning d-flex flex-column gap-3">
    <div class="d-flex align-items-center">
        <i class="bi bi-people me-2"></i>
        <span>请登录比赛账号<span class="en-text">You need to log in with the contest account.</span></span>
    </div>
    
    <form id="contest_auth_form" method='post' action="/{$module}/contest/contest_auth_ajax" class="d-flex align-items-start gap-3">
        <input type="hidden" class="form-control" name="cid" value="{$contest['contest_id']}">
        
        <div class="d-flex flex-column gap-2">
            <input type="text" id="cpc_team_id" name="team_id" class="form-control cpc_login" placeholder="队伍ID(Team ID)" required autofocus title="请输入队伍ID" style="width: 150px;" />
            <input type="password" id="cpc_password" name="password" class="form-control cpc_login" placeholder="密码(Password)" required title="请输入密码" style="width: 150px;">
        </div>
        
        <div class="d-flex align-items-center">
            <button type="submit" id="submit_button" class="btn btn-primary">提交<span class="en-text">Submit</span></button>
        </div>
    </form>
</div>
<script type="text/javascript">
    // 传递模板变量给JavaScript
    window.CONTEST_AUTH_CONFIG = {
        redirectUrl: "/{$module}/contest/contest_auth_ajax",
        cid: "{$contest['contest_id']}"
    };
</script>
{js href="__STATIC__/csgoj/contest/contest_auth.js" /}
{/if}
