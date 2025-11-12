<!-- 公共比赛登录框模板 -->
{if $needAuth /}
<div class="alert alert-warning d-flex flex-column gap-3">
    <div class="d-flex align-items-center">
        <i class="bi bi-shield-lock me-2"></i>
        <span>此比赛已加密，需要验证密码<span class="en-text">This contest is encrypted, you need to verify the password.</span></span>
    </div>
    
    <form id="contest_auth_form" method='post' action="/{$module}/{$contest_controller}/contest_auth_ajax" class="d-flex align-items-center gap-3">
        <input type="hidden" class="form-control" name="cid" value="{$contest['contest_id']}">
        
        <div>
            <input type="text" class="form-control" name="contest_pass" placeholder="比赛密码 Contest Password..." required title="请输入比赛密码" style="width: 150px;">
        </div>
        
        <div>
            <button type="submit" id="submit_button" class="btn btn-primary">提交<span class="en-text">Submit</span></button>
        </div>
    </form>
</div>
<script type="text/javascript">
    $(document).ready(function() {
        
        $('#contest_auth_form').validate(
            {
                rules: {
                    contest_pass: {
                        minlength: 6,
                        maxlength: 15
                    }
                },
                errorPlacement: function (error, element) {
                    // 使用Bootstrap 5的tooltip显示错误信息
                    var tooltip = bootstrap.Tooltip.getInstance(element[0]);
                    if (tooltip) {
                        tooltip.setContent({'.tooltip-inner': error.text()});
                        tooltip.show();
                    }
                },
                unhighlight: function(element, errorClass, validClass) {
                    // 隐藏tooltip
                    var tooltip = bootstrap.Tooltip.getInstance(element[0]);
                    if (tooltip) {
                        tooltip.hide();
                    }
                },
                submitHandler: function (form) {
                    $(form).ajaxSubmit({
                        success: function (ret) {
                            var submit_button = $('#submit_button');
                            if (ret["code"] == 1) {
                                button_delay(submit_button, 3, '提交', '提交', 'Submit');
                                alertify.success(ret['msg']);
                                setTimeout(function(){location.href=ret['data']['redirect_url']}, 1000);
                            }
                            else {
                                alertify.alert(ret['msg']);
                                button_delay(submit_button, 3, '提交', '提交', 'Submit');
                            }
                            return false;
                        }
                    });
                    return false;
                }
            });
    });
</script>
{/if}
