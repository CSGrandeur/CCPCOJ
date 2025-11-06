{include file="../../csgoj/view/user/mail_header" /}
<form role="form" id="topic_add_form" action="mail_add_ajax" method="POST">
    <div class="mb-3">
        <label for="user_id" class="form-label">*To User:</label>
        <input type="text" name="user_id" class="form-control" style="width:200px;" >
    </div>
    <div class="mb-3">
        <label for="title" class="form-label">*Mail Title：</label>
        <input type="text" name="title" placeholder="1~64 characters" class="form-control" style="max-width:900px;" >
    </div>
    <div class="mb-3">
        <label for="content" class="form-label">Mail Content：</label>
        <textarea class="form-control" rows="20" name="content" spellcheck="false" placeholder="^_^" style="max-width:900px;" ></textarea>
    </div>
    <div class="mb-3">
        <label class="form-label">*V-Code ：</label>
        <input type="text" class="form-control" placeholder="Verification Code" name="vcode" required>
        <label for="vcode" class="notification_label"></label>
    </div>
    <button type="submit" id='submit_button' class="btn btn-primary">Send Mail</button>
    <label id="vcode">{:captcha_img()}</label>
</form>

<script type="text/javascript">
    var submit_button = $('#submit_button');
    $('#vcode').on('click', function(){
        var ts = Date.parse(new Date())/1000;
        this.getElementsByTagName('img')[0].src = "/captcha?id="+ts;
    });
    $(document).ready(function(){
        $('input[type="text"]').tooltipster({ //find more options on the tooltipster page
            trigger: 'custom', // default is 'hover' which is no good here
            position: 'top',
            animation: 'grow',
            theme: 'tooltipster-noir',
            distance: -10
        });
        $('textarea').tooltipster({ //find more options on the tooltipster page
            trigger: 'custom', // default is 'hover' which is no good here
            position: 'top',
            animation: 'grow',
            theme: 'tooltipster-noir',
            distance: -10
        });
        $('#topic_add_form').validate({
            rules:{
                user_id:{
                    required: true,
                    minlength: 5,
                    maxlength: 20
                },
                title: {
                    required: true,
                    minlength: 1,
                    maxlength: 64
                },
                content: {
                    minlength: 2,
                    maxlength: 16384
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
            submitHandler: function(form)
            {
                submit_button.attr('disabled', true);
                $(form).ajaxSubmit(function(ret)
                {
                    if(ret["code"] == 1)
                    {
                        alertify.success(ret['msg']);
                        setTimeout(function(){location.href='mail_detail?mail_id=' + ret['data']['mail_id'];}, 500);
                        return true;
                    }
                    else
                    {
                        alertify.alert(ret["msg"]);
                        button_delay(submit_button, 3, submit_button.text());
                    }
                    return false;
                });
                return false;
            }
        });
    });
    $("textarea").on('keydown',function(e){
        if(e.keyCode == 9){
            e.preventDefault();
            var indent = '    ';
            var start = this.selectionStart;
            var end = this.selectionEnd;
            var selected = window.getSelection().toString();
            selected = indent + selected.replace(/\n/g,'\n'+indent);
            this.value = this.value.substring(0,start) + selected + this.value.substring(end);
            this.setSelectionRange(start+indent.length,start+selected.length);
        }
    })
</script>