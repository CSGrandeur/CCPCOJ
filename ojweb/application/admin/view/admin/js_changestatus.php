<input type="hidden" id="page_item" value="{$controller}">
<script type="text/javascript">
    $(document).off('click',  '.change_status');
    $(document).on('click',  '.change_status', function(){
        var button = $(this);
        var item_name = button.attr('item_name');
        if(!item_name) item_name = $('#page_item').val();
        $.get(
            '__ADMIN__/itemstatuschange/change_status_ajax',
            {
                'item': item_name,
                'id': button.attr('itemid'),
                'field': button.attr('field'),
                'status': button.attr('status') == '1' ? '0' : '1'
            },
            function(ret) {
                if(ret.code == 1) {
                    let data = ret.data;
                    alertify.success("Successfully changed to <strong>" + data['status_str'] + "</strong>");
                    button.attr('status', data['status']);
                    button.removeClass(`btn-${data['status_class_rmv']}`).addClass(`btn-${data['status_class']}`);
                    button.text(data['status_str']);
                } else {
                    alertify.error(ret['msg']);
                }
            },
            'json'
        );

    });
</script>