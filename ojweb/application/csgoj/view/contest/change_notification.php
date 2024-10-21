<!-- Modal -->
<div class="modal fade" id="page_modal" tabindex="-1" role="dialog" aria-labelledby="page_modal_label">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h3 class="modal-title" id="page_modal_Label" style="color:black !important;">Notification of Contest {$contest['contest_id']}
                    <button type="button" class="btn btn-primary page_modal_submit" id="page_modal_submit_top">Save Changes</button></h3>
            </div>
            <div class="modal-body">
                <input type="hidden" id="page_info" cid="{$contest['contest_id']}" >
                <textarea class="form-control" rows="20" id="page_modal_text"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary page_modal_submit" id="page_modal_submit_bottom">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    var page_info = $('#page_info');
    var page_modal = $('#page_modal');
    var page_modal_text = $('#page_modal_text');
    var page_modal_submit = $('.page_modal_submit');
    var submit_button_text = $(page_modal_submit[0]).text();
    var contest_notification_div = $('#contest_notification_div');
    page_modal.on('show.bs.modal', function(e){
        $.get(
            'description_md_ajax',
            {
                'cid': page_info.attr('cid')
            },
            function(ret){
                if(ret['code'] == 1) {
                    page_modal_text.val(ret['data']);

                }
            }
        )
    });
    page_modal_submit.off('click');
    page_modal_submit.on('click', function(){
        var notification = $.trim(page_modal_text.val());
        if(notification.length == 0){
            alertify.confirm(
                'Notification is cleared. Are you sure to submit?',
                function(){
                    ChangeNotification(notification);
                },
                function(){
                    alertify.message('Canceled');
                }
            )
        }
        else if(notification.length < 16384){
            ChangeNotification(notification);
        }
        else{
            alertify.error('Notification too long');
        }

    });
    function ChangeNotification(notification)
    {
        page_modal_submit.attr('disabled', 'disabled');
        page_modal_submit.text('Waiting');
        $.post(
            'description_change_ajax',
            {
                'cid': page_info.attr('cid'),
                'description_md': notification
            },
            function(ret){
                if(ret.code == 1) {
                    alertify.success(ret.msg);
                    contest_notification_div.html(ret.data);
                    page_modal.modal('hide');
                    button_delay(page_modal_submit, 5, submit_button_text);
                }
                else {
                    alertify.error(ret['msg']);
                    button_delay(page_modal_submit, 5, submit_button_text);
                }
            }
        )
    }
    page_modal_text.keydown(function(e){
        if(e.ctrlKey && e.keyCode == 13){
            e.preventDefault();
            var a=document.createEvent("MouseEvents");
            a.initEvent("click", true, true);
            page_modal_submit[0].dispatchEvent(a);
        }

    });
</script>