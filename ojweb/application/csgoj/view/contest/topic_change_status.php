<script type="text/javascript">
    $(document).off('click',  '.change_status');
    $(document).on('click',  '.change_status', function(){
        var button = $(this);
        $.get(
            'topic_change_status_ajax',
            {
                'cid': cid,
                'topic_id': button.attr('topic_id'),
                'status': button.attr('status') == '0' ? '1' : '0'
            },
            function(ret)
            {
                if(ret['code'] == 1)
                {
                    var data = ret['data'];
                    alertify.success(ret['msg']);
                    button.attr('status', data['status']);
                    if(button.attr('status') == '1')
                    {
                        button.removeClass("btn-warning").addClass("btn-success");
                        button.text(data['statusstr']);
                    }
                    else
                    {
                        button.removeClass("btn-success").addClass("btn-warning");
                        button.text(data['statusstr']);
                    }
                }
                else
                {
                    alertify.error(ret['msg']);
                }
            },
            'json'
        );

    });
</script>