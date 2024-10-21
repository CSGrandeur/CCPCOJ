<h3>公告(Announcement):</h3>
<article class="md_display_div">
    {if $contestStatus == -1}
        <span class="alert alert-info" style="display: inline-block;">This contest is not started yet.</span>
    {/if}
    {if $needAuth /}
        <form id="contest_auth_form" method='post' action="/{$module}/contest/contest_auth_ajax">
            <div class="form-group">
                <span class="alert alert-warning" style="display: inline-block;">请登录比赛账号<br/>You need to log in with the contest account.</span>
                <input type="hidden" class="form-control" name="cid" value="{$contest['contest_id']}">
                
                <input type="text" id="cpc_team_id" name="team_id" class="form-control cpc_login" placeholder="Team ID" required autofocus />
                <input type="password" id="cpc_password" name="password" class="form-control cpc_login" placeholder="Password" required style="margin-top: 5px;">
            </div>
            <button type="submit" id="submit_button" class="btn btn-primary">Submit</button>
        </form>
        <script type="text/javascript">
            $(document).ready(function() {
                $('.cpc_login').tooltipster({
                    trigger: 'custom',
                    position: 'bottom',
                    animation: 'grow',
                    theme: 'tooltipster-noir',
                    distance: -15
                });
                $('#contest_auth_form').validate(
                    {
                        rules: {
                            team_id: {
                                minlength: 3,
                                maxlength: 20
                            },
                            password: {
                                minlength: 3,
                                maxlength: 32
                            }
                        },
                        errorPlacement: function (error, element) {
                            var ele = $(element),
                                err = $(error),
                                msg = err.text();
                            if (msg != null && msg !== '') {
                                ele.tooltipster('content', msg);
                                ele.tooltipster('open');
                            }
                        },
                        unhighlight: function(element, errorClass, validClass) {
                            $(element).removeClass(errorClass).addClass(validClass).tooltipster('close');
                        },
                        submitHandler: function (form) {
                            $(form).ajaxSubmit({
                                success: function (ret) {
                                    var submit_button = $('#submit_button');
                                    if (ret.code == 1) {
                                        button_delay(submit_button, 3, 'Submit');
                                        alertify.success(ret.msg);
                                        setTimeout(function(){location.href=ret.data.redirect_url}, 1000);
                                    }
                                    else {
                                        alertify.alert(ret['msg']);
                                        button_delay(submit_button, 3, 'Submit');
                                    }
                                    return false;
                                }
                            });
                            return false;
                        }
                    });
            });
        </script>
    {elseif !$canJoin /}
        <span class="alert alert-danger" style="display: inline-block;">Not permitted to participate, you can only watch the ranklist.</span>
    {/if}
    <?php if(strlen($contest['description']) == 0) echo "<h5>Nothing more.</h5>"; ?>
    {$contest['description']}
</article>
{include file="../../csgoj/view/public/mathjax_js" /}