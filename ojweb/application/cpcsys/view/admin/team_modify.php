<div class="page-header">
    <h1>Update Team's Information</h1>
</div>
<div class="container marketing">
    <form id="team_modify_form" class="form-modify" method="post" action="/{$module}/admin/team_modify_ajax">
        <div class="form-inline">
            <label class="description_label">Team ID ：</label>
            <input type="text" id="team_id_input" class="form-control teaminfo_input" placeholder="Team ID" name="team_id" value="" required>
            <label for="team_id" class="notification_label"></label>
        </div>
        <div class="form-inline">
            <label class="description_label">Team Name ：</label>
            <input type="text" class="form-control teaminfo_input" name="name" placeholder="No more than 30 characters" value="">
            <label for="name" class="notification_label"></label>
        </div>
        <div class="form-inline">
            <label class="description_label">Member ：</label>
            <input type="text" class="form-control teaminfo_input" name="tmember" placeholder="Member" value="" >
            <label for="tmember" class="notification_label"></label>
        </div>
        <div class="form-inline">
            <label class="description_label">Coach ：</label>
            <input type="text" class="form-control teaminfo_input" name="coach" placeholder="Coach" value="" >
            <label for="coach" class="notification_label"></label>
        </div>
        <div class="form-inline">
            <label class="description_label">School ：</label>
            <input type="text" class="form-control teaminfo_input" name="school" placeholder="School" value="">
            <label for="school" class="notification_label"></label>
        </div>
        <div class="form-inline">
            <label class="description_label">Room ：</label>
            <input type="text" class="form-control teaminfo_input" name="room" placeholder="Room" value="">
            <label for="room" class="notification_label"></label>
        </div>
        <div class="form-inline">
            <label class="description_label">Tkind ：</label>
            <input type="text" class="form-control teaminfo_input" name="tkind" placeholder="Tkind(0common,1girls,2star)" value="">
            <label for="tkind" class="notification_label"></label>
        </div>
        <div class="form-inline">
            <label class="description_label">New Password ：</label>
            <input type="text" id="modify_password" class="form-control teaminfo_input" placeholder="Let it blank or at least 6 characters" name="password" >
            <label for="password" class="notification_label"></label>
        </div>
        <div class="form-inline">
            <label class="description_label">Fixed IP ：</label>
            <input type="text" class="form-control teaminfo_input" name="fixed_ip" placeholder="Fixed IP: xxx.xxx.xxx.xxx" value="">
            <label for="fixed_ip" class="notification_label"></label>
        </div>
        <input type='hidden' id="cid_input" name='cid' value="{$contest['contest_id']}" >
        <button class="btn btn-primary" id="submit_button" type="submit">Submit</button>
    </form>
</div>

<style type="text/css">
    #team_modify_form{
        /*width: 500px;*/
    }
    #team_modify_form .description_label{
        width: 150px;
        text-align: right;
    }
    #team_modify_form .notification_label{
        width: 300px;
        text-align: left;
    }
    #team_modify_form input{
        width: 360px;
    }
    #team_modify_form > div{
        margin-top: 10px;
    }
    #team_modify_form > button {
        margin-left: 155px;
        margin-top: 20px;
    }
</style>
<script type="text/javascript">
    $(document).ready(function(){
        let team_id_input = $('#team_id_input');
        team_id_input.blur(function(){
            if(team_id_input.val().trim() != '') {
                $.get(
                    '__CPC__/admin/teaminfo_ajax',
                    {
                        'team_id': team_id_input.val().trim(),
                        'cid': $('#cid_input').val()
                    },
                    function(ret){
                        if(ret['code'] == 1)
                        {
                            $('.teaminfo_input').each(function(){
                                $(this).val(ret['data']['teaminfo'][$(this).attr('name')]);
                            });
                        }
                        else
                        {
                            alertify.error(ret['msg']);
                        }
                        return false;
                    }
                );
            }
        });

        $.validator.addMethod('ipcheck', function(value, element) {
            if(value.trim() == '') return true;
            let ip_pattern = /^((25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/;
            return this.optional(element) || ip_pattern.test(value);
        }, 'Invalid IP format.');

        $('#team_modify_form').validate({
            rules:{
                team_id: {
                    required: true,
                    minlength: 3,
                    maxlength: 30
                },
                fixed_ip: {
                    ipcheck: true
                }
            },
            submitHandler: function(form)
            {
                $('#submit_button').attr('disabled', true);
                $(form).ajaxSubmit(
                {
                    success: function(ret)
                    {
                        button_delay($('#submit_button'), 1, 'Submit');
                        if(ret["code"] == 1)
                        {
                            alertify.success(ret['msg']);
                        }
                        else
                        {
                            alertify.alert(ret['msg']);
                            var ts = Date.parse(new Date())/1000;
                            $('#vcode').find('img').attr('src', "/captcha?id="+ts);
                        }
                        return false;
                    }
                });
                return false;
            }
        });

    });
</script>