<h1>Password Retrieve</h1>
<hr/>
<span class="alert alert-warning" style="display: inline-block;">
    <h2>ATTENTION</h2>
    Password retrieve procedure will send email to the email address of your account.<br/>
    <strong>Please make sure the address you left in your account is valid.</strong><br/>
    If not, You can join QQ group 1065953958 and ask in the group.(don't send private message to any admin)<br/>
</span>
<form id="passback_form" method='post' action="passback_ajax">
    <div class="mb-3">
        <label for="user_id" class="form-label">User ID：</label>
        <input type="text" class="form-control" name="user_id" placeholder="User ID..." style="max-width: 200px;" required>
    </div>
    <div class="mb-3">
        <label class="form-label">*V-Code ：</label>
        <input type="text" class="form-control" placeholder="Verification Code" name="vcode" style="max-width: 200px;"  required>
    </div>
    <button type="submit" id="submit_button" class="btn btn-primary">Submit</button>
    <label id="vcode">{:captcha_img()}</label>
</form>
<script type="text/javascript">
    $('#vcode').on('click', function(){
        var ts = Date.parse(new Date())/1000;
        this.getElementsByTagName('img')[0].src = "/captcha?id="+ts;
    });
    $(document).ready(function() {
        $('input[type="text"]').tooltipster({ //find more options on the tooltipster page
            trigger: 'custom', // default is 'hover' which is no good here
            position: 'right',
            animation: 'grow',
            theme: 'tooltipster-noir',
            distance: -10
        });
        var submit_button = $('#submit_button');
        $('#passback_form').validate(
            {
                rules: {
                    user_id: {
                        minlength: 5,
                        maxlength: 20
                    },
                    vcode: {
                        required: true
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
                                    alertify.alert(ret['msg']);
                                    button_delay(submit_button, 3, 'Submit');
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