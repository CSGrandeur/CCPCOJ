<div class="login_div">
    <?php 
        if($OJ_SSO == false) {
            if($OJ_MODE == 'online') {
                $login_url = "/csgoj/user/login_ajax";
            } else if($OJ_STATUS == 'cpc') {
                $login_url = "/cpcsys/user/login_ajax";
            } else {
                $login_url = "/expsys/user/login_ajax";
            }
        } else {
            $login_url = "/ojtool/sso/sso_direct_ajax";
        }
    ?>
    
    {if $OJ_SSO != false } 
        <div class="d-grid gap-2 mt-2">
            <a href="/ojtool/sso/sso_start" class="a_noline">
                <button class="btn btn-success btn-sm bilingual-inline">
                    <span class="cn-text"><i class="bi bi-box-arrow-in-right"></i> 登录</span><span class="en-text">Login</span>
                </button>
            </a>
        </div>
    {else /}
        <form id="login_form" class="form-signin" method="post" action="{$login_url}">
            <div class="mb-1">
                <input type="text" id="user_id" name="user_id" class="form-control form-control-sm" placeholder="用户名 User ID" {if($controller == 'index' || $controller == 'problemset')}autofocus{/if}>
            </div>
            <div class="mb-1">
                <input type="password" id="login_password" name="password" class="form-control form-control-sm" placeholder="密码 Password">
            </div>
            <div class="d-grid gap-1 mb-1">
                <button class="btn btn-primary btn-sm bilingual-inline" id="login_submit_button" type="submit">
                    <span class="cn-text"><i class="bi bi-box-arrow-in-right"></i> 登录</span><span class="en-text">Login</span>
                </button>
            </div>
            {if $OJ_MODE == 'online' && $OJ_STATUS=='cpc' && $OJ_SSO == false}
            <div class="d-grid gap-1 mb-1">
                <a href="__OJ__/user/register" class="a_noline">
                    <button class="btn btn-outline-success btn-sm bilingual-inline" type="button">
                        <span class="cn-text"><i class="bi bi-person-plus"></i> 注册</span><span class="en-text">Register</span>
                    </button>
                </a>
            </div>
            {/if}
            {if $OJ_MODE == 'online' && $OJ_STATUS == 'cpc' && $OJ_SSO == false}
            <div class="text-center">
                <a href="__OJ__/user/passback" class="a_noline text-decoration-none small text-muted">
                    忘记密码<span class="en-text">Forgot Password</span>?
                </a>
            </div>
            {/if}
        </form>
    {/if}
</div>
{js href="__STATIC__/csgoj/user/login_out.js" /}
