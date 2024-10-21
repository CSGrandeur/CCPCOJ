<h3>公告(Announcement):</h3>
<article class="md_display_div">
    {if $contestStatus == -1}
        <span class="alert alert-info" style="display: inline-block;">This contest is not started yet.</span>
    {elseif !session('?user_id') }
        <span class="alert alert-info" style="display: inline-block;">Please login before joining the contest.</span>
    {elseif $needAuth /}
        <form id="contest_auth_form" method='post' action="/{$module}/{$contest_controller}/contest_auth_ajax">
            <div class="form-group">
                <span class="alert alert-warning" style="display: inline-block;">This contest is encrypted, you need to verify the password.</span>
                <input type="hidden" class="form-control" name="cid" value="{$contest['contest_id']}">
                <input type="text" class="form-control" name="contest_pass" placeholder="Contest Password..." required>
            </div>
            <button type="submit" id="submit_button" class="btn btn-primary">Submit</button>
        </form>
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
                        submitHandler: function (form) {
                            $(form).ajaxSubmit({
                                success: function (ret) {
                                    var submit_button = $('#submit_button');
                                    if (ret["code"] == 1) {
                                        button_delay(submit_button, 3, 'Submit');
                                        alertify.success(ret['msg']);
                                        setTimeout(function(){location.href=ret['data']['redirect_url']}, 1000);
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