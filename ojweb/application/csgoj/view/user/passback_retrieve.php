<h1>Password Retrieve</h1>
<hr/>
{if !$authOk }
<h2>Token validation failed</h2>
<span class="alert alert-info" style="display: block; max-width: 400px;">
    Perhaps your validation url is expired, or already used.
</span>
<a href="passback">Click to return to passback page</a>
{else /}
<h2>Reset password of <a href="/csgoj/user/userinfo?user_id={$user_id}">{$user_id}</a></h2>
<form id="passback_retrieve_form" method='post' action="passback_retrieve_ajax">
    <div class="mb-3">
        <label for="user_id" class="form-label">New Password：</label>
        <input type="password" id="reset_password" class="form-control" name="password" placeholder="At least 6 characters..." style="max-width: 200px;" required>
    </div>
    <div class="mb-3">
        <label for="confirm_password" class="form-label">Confirm：</label>
        <input type="password" class="form-control" name="confirm_password" placeholder="Confirm Password..." style="max-width: 200px;" required>
    </div>
    <input type="hidden" name="user_id" value="{$user_id}">
    <input type="hidden" name="token" value="{$token}">
    <button type="submit" id="submit_button" class="btn btn-primary">Submit</button>
</form>
<script type="text/javascript">
    $(document).ready(function() {
        $('input[type="password"]').tooltipster({ //find more options on the tooltipster page
            trigger: 'custom', // default is 'hover' which is no good here
            position: 'right',
            animation: 'grow',
            theme: 'tooltipster-noir',
            distance: -10
        });
        var submit_button = $('#submit_button');
        $('#passback_retrieve_form').validate(
            {
                rules: {
                    password: {
                        required: true,
                        minlength: 6,
                        maxlength: 64
                    },
                    confirm_password: {
                        required: true,
                        minlength: 6,
                        maxlength: 64,
                        equalTo: "#reset_password"
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
                    submit_button.attr('disabled', 'disabled');
                    submit_button.text('Processing...');
                    $(form).ajaxSubmit(
                        {
                            success: function (ret) {
                                if (ret["code"] == 1) {
                                    button_delay(submit_button, 3, 'Submit');
                                    alertify.alert(ret['msg'], function(){
                                        location.href='/';
                                    });
                                }
                                else {
                                    if(ret['msg'].length < 20)
                                        alertify.error(ret['msg']);
                                    else
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
{/if}