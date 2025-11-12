<input type="hidden" id="page_item" value="{$controller}">
<script type="text/javascript">
    $(document).off('click',  '.change_status');
    $(document).on('click',  '.change_status', function(){
        var button = $(this);
        var item_name = button.attr('item_name');
        if(!item_name) item_name = $('#page_item').val();
        const item_id = button.attr('itemid');
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
                    alerty.success({
                        message: `${item_id} 状态已更改为 ${data['status_str']}`,
                        message_en: "Successfully changed to " + data['status_str']
                    });
                    button.attr('status', data['status']);
                    button.removeClass(`btn-${data.status_class_rmv}`).addClass(`btn-${data.status_class}`);
                    
                    // 根据字段类型和状态重新构建双语显示
                    let field = button.attr('field');
                    let status = data['status'];
                    let bilingualText = '';
                    let iconClass = '';
                    
                    if (field === 'defunct') {
                        // 公开/隐藏状态
                        if (status == '0') {
                            iconClass = 'bi bi-unlock-fill ';
                            bilingualText = '<span class="en-text">Public</span>';
                        } else {
                            iconClass = 'bi bi-lock-fill ';
                            bilingualText = '<span class="en-text">Hidden</span>';
                        }
                    } else if (field === 'archived') {
                        // 归档状态
                        if (status == '0') {
                            iconClass = 'bi bi-archive ';
                            bilingualText = '<span class="en-text">UnArchive</span>';
                        } else {
                            iconClass = 'bi bi-archive-fill ';
                            bilingualText = '<span class="en-text">Archived</span>';
                        }
                    } else if (field === 'private') {
                        // 私有状态
                        if (status == '0') {
                            iconClass = 'bi bi-globe ';
                            bilingualText = '<span class="en-text">Public</span>';
                        } else {
                            iconClass = 'bi bi-lock ';
                            bilingualText = '<span class="en-text">Private</span>';
                        }
                    } else {
                        // 默认使用后台返回的文本
                        bilingualText = data['status_str'];
                    }
                    
                    button.html(`<i class="${iconClass}"></i>${bilingualText}`);
                    
                    // 更新title属性
                    let nextStatus = status == '0' ? '1' : '0';
                    let nextStatusText = '';
                    let nextStatusTextEn = '';
                    
                    if (field === 'defunct') {
                        if (nextStatus == '0') {
                            nextStatusText = '公开';
                            nextStatusTextEn = 'Public';
                        } else {
                            nextStatusText = '隐藏';
                            nextStatusTextEn = 'Hidden';
                        }
                    } else if (field === 'archived') {
                        if (nextStatus == '0') {
                            nextStatusText = '未归档';
                            nextStatusTextEn = 'UnArchive';
                        } else {
                            nextStatusText = '已归档';
                            nextStatusTextEn = 'Archived';
                        }
                    } else if (field === 'private') {
                        if (nextStatus == '0') {
                            nextStatusText = '公开';
                            nextStatusTextEn = 'Public';
                        } else {
                            nextStatusText = '私有';
                            nextStatusTextEn = 'Private';
                        }
                    }
                    
                    if (nextStatusText) {
                        button.attr('title', `点击更改为${nextStatusText}状态 (Click to change to ${nextStatusTextEn})`);
                    }
                } else {
                    alerty.error({
                        message: ret?.msg,
                        message_en: ret?.msg ?? ''
                    });
                }
            },
            'json'
        );

    });
</script>