<div class="logout_div">
    <div class="mb-2">
        <div class="d-flex align-items-center justify-content-between">
            <small class="text-muted">欢迎回来<span class="en-text">Welcome Back</span></small>
            {if $OJ_SSO == false}
            <a href="__OJ__/user/modify?user_id={$Think.session.user_id}" class="a_noline text-muted gear-icon" title="修改信息">
                <i class="bi bi-gear-fill" style="font-size: 0.9rem; opacity: 0.7;"></i>
            </a>
            {else /}
            <a href="{if null != $Think.session.sso_login }{$OJ_SSO}/sso/changepass/{else /}{$OJ_SSO}/sso/changepass/notlogin.html{/if}" class="a_noline text-muted gear-icon" title="修改密码 Change Password" target="_blank">
                <i class="bi bi-key-fill" style="font-size: 0.9rem; opacity: 0.7;"></i>
            </a>
            {/if}
        </div>
        <div class="fw-bold">
            <a href="{if $OJ_MODE == 'cpcsys' }__CPC__{else /}__OJ__{/if}/user/userinfo?user_id={$Think.session.user_id}" class="a_noline text-decoration-none">
                <i class="bi bi-person-circle"></i> {$Think.session.user_id}
            </a>
        </div>
    </div>
    <div class="d-grid gap-1">
        {if $OJ_SSO != false}
            <a href="__OJTOOL__/sso/sso_logout" class="a_noline">
                <button class="btn btn-outline-danger btn-sm bilingual-inline" type="button">
                    <span class="cn-text"><i class="bi bi-box-arrow-right"></i> 登出</span><span class="en-text">Logout</span>
                </button>
            </a>
        {else /}
            <button class="btn btn-outline-danger btn-sm bilingual-inline" id="logout_button" type="button">
                <span class="cn-text"><i class="bi bi-box-arrow-right"></i> 登出</span><span class="en-text">Logout</span>
            </button>
        {/if}
    </div>
</div>

<script type="text/javascript">
    // 传递模板变量给JavaScript
    window.logoutConfig = {
        logoutUrl: "{if $OJ_MODE == 'cpcsys' }__CPC__{else /}__OJ__{/if}/user/logout_ajax",
        redirectUrl: "__OJ__"
    };
</script>
{js href="__STATIC__/csgoj/user/login_out.js" /}