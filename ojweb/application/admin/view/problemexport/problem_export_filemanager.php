{__NOLAYOUT__}
{include file="../../csgoj/view/public/global_head" /}
<div class="container">
    <h1>Problem Export/Import File Manager</h1>
</div>
<script type="text/javascript">
    var re_checkfile = /^[0-9a-zA-Z-_\.\(\)]+\.(zip)$/;
</script>
{include file="filemanager/js_upload" /}
<script type="text/javascript">
    var table = $('#upload_table');
    var fire_url = id_input.attr('fire_url');
    var item_name = $('#item_input').val();
    table.on('click-cell.bs.table', function(e, field, td, row) {
        if (field === 'file_type') {
            var button = $('#' + $(td).attr('id'));
            alertify.confirm("Import problem will add new problems, reimport problem file may make duplicated problems.<br/> Sure to import?",
                function() {
                    button.attr('disabled', true);
                    var button_text = button.text();
                    button.text('Running...');
                    $.get(
                        fire_url, {
                            'filename': row.file_name,
                            'item': item_name
                        },
                        function(ret) {
                            if (ret.code === 1) {
                                data = ret.data;
                                var msg = '<br/>';
                                msg += data.addedList.length + " Imported<br/>";
                                msg += data.failedList.length + " Failed<br/>";
                                if (data['judgeDataFolderPermission'] === false) {
                                    msg += "Data Folder Permission Denied<br/>";
                                }
                                if (data['attachFolderPermission'] === false) {
                                    msg += "Attach Folder Permission Denied<br/>";
                                }
                                if (data.failedList.length > 0) {
                                    msg += 'Failed Detail:<br/>';
                                    msg += data.failedList.join('<br/>');
                                }

                                alertify.alert(ret.msg + msg);
                            } else {
                                alertify.alert(ret['msg']);
                            }
                            button_delay(button, 3, 'Import');
                            // button.removeAttr('disabled');
                            // button.text(button_text);
                            return false;
                        }
                    );
                },
                function() {
                    alertify.message('Canceled');
                });
        }
    });
</script>