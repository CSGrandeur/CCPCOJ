{include file="../../csgoj/view/public/clipboard_js" /}
<div style="max-width:1140px; margin:auto;">
    <form id="upload_form" method="post" action="{$upload_url}" enctype="multipart/form-data" style="display:none">
        <input type="file" id="upload_input" name="upload_file[]" multiple style="display:none">
        <input type="hidden" name="item" id='item_input' value="{$inputinfo['item']}">
        <input
            type="hidden"
            name="id"
            id='id_input'
            maxfilesize={$maxfilesize}
            delete_url="{$delete_url}"
            rename_url="{$rename_url}"
            maxfilenum="{$maxFileNum}"
            fire_url="{if(isset($fire_url))}{$fire_url}{else/}null{/if}"
            value="{$inputinfo['id']}">
    </form>
    {if(isset($attach_notify))}<span class="alert alert-info" style="display: inline-block;">{$attach_notify}</span>{/if}
    <div id="upload_toolbar">
        <div class="form-inline fake-form" role="form">
            <button id="upload_button" class="btn btn-default">Upload File</button>&nbsp;&nbsp;
            {if isset($addition_tools) && !empty($addition_tools)}
                {$addition_tools}&nbsp;&nbsp;
            {/if}
        </div>
    </div>
    <table
        class="bootstraptable_refresh_local"
        id="upload_table"
        data-toggle="table"
        data-url="{$file_url}"
        data-toolbar="#upload_toolbar"
        data-pagination="true"
        data-page-list="[10, 25, 50]"
        data-page-size="50"
        data-method="get"
        data-search="true"
        data-search-align="left"
        data-side-pagination="client"
        data-unique-id="file_name"
        data-pagination-v-align="bottom"
        data-pagination-h-align="left"
        data-pagination-detail-h-align="right"

        data-cookie="true"
        data-cookie-id-table="{$OJ_SESSION_PREFIX}-file-{$inputinfo['item']}"
        data-cookie-expire="5mi"
        data-cookie-enabled="['bs.table.sortOrder', 'bs.table.sortName', 'bs.table.pageList']">
        <thead>
            <tr>
                <th data-field="file_serial" data-align="center" data-valign="middle" data-sortable="true" data-width="60" data-formatter="FormatterIdx">ID</th>
                <th data-field="file_name" data-align="left" data-valign="middle" data-sortable="true" data-formatter="FormatterFileName">Name</th>
                <th data-field="file_size" data-align="right" data-valign="middle" data-sortable="true" data-width="120">Size(Kb)</th>
                <th data-field="file_type" data-align="center" data-valign="middle" data-width="120" data-formatter="FormatterFileType">{$method_button}</th>
                <th data-field="file_rename" data-align="center" data-valign="middle" data-width="100" data-formatter="FormatterFileRename">Rename</th>
                <th data-field="file_lastmodify" data-align="center" data-valign="middle" data-sortable="true" data-width="180">Last Modify</th>
                <th data-field="file_delete" data-align="center" data-valign="middle" data-width="90" data-formatter="FormatterFileDelete">Del(DblClick)</th>
            </tr>
        </thead>
    </table>
</div>
{include file="../../csgoj/view/public/refresh_in_table" /}
<input type="hidden" id="input_hidden_page_info" action="{$action}">
<script type="text/javascript">
    function FormatterFileName(value, row, index, field) {
        return `<a href='${row['file_url']}' filename='${value}'>${value}</a>`;
    }

    function FormatterFileType(value, row, index, field) {
        if (('file_type' in row) && row.file_type.toLowerCase() == 'import') {
            return "<button class='fire_button btn btn-success'>Import</button>";
        } else {
            return `<button class='copy_button btn btn-success' data-clipboard-text=${row['file_url']}>${value}</button>`;
        }

    }

    function FormatterFileDelete(value, row, index, field) {
        if (row?.file_type === 'directory') {
            return 'Disabled'
        }
        return "<button class='delete_button btn btn-danger'>Delete</button>";
    }

    function FormatterFileRename(value, row, index, field) {
        if (row?.file_type === 'directory') {
            return 'Disabled'
        }
        return "<button class='rename_button btn btn-info'>Rename</button>";
    }
    //delete file.
    var table = $('#upload_table');
    var upload_button = $('#upload_button');
    var id_input = $('#id_input');
    var item_id = id_input.val();
    var maxfilesize = id_input.attr('maxfilesize');
    var delete_url = id_input.attr('delete_url');
    var rename_url = id_input.attr('rename_url');
    var maxfilenum = id_input.attr('maxfilenum');
    let flg_dblclick_inform = false;

    var clipboard = new ClipboardJS('.copy_button');
    clipboard.on('success', function(e) {
        alertify.success('Copied file url');
        e.clearSelection();
    });
    let input_hidden_page_info = $('#input_hidden_page_info');
    let this_action = input_hidden_page_info.attr('action');
    $(document).ready(function() {
        localStorage.setItem('file_delete_btn', 0);
    });

    function SendDelete(row) {
        $.get(
            delete_url, {
                'id': item_id,
                'filename': row.file_name
            },
            function(ret) {
                if (ret['code'] == 1) {
                    alertify.warning(ret['msg']);
                    table.bootstrapTable('removeByUniqueId', row['file_name']);
                } else {
                    alertify.error(ret['msg']);
                }
                return false;
            }
        );
    }
    table.on('dbl-click-cell.bs.table', function(e, field, td, row) {
        // 双击直接删除
        if (field == 'file_delete' && row?.file_type != 'directory') {
            SendDelete(row);
            localStorage.setItem('file_delete_btn', 2);
        }
    });
    table.on('click-cell.bs.table', function(e, field, td, row) {
        if (field == 'file_delete') {
            if (row?.file_type != 'directory' && !flg_dblclick_inform) {
                alertify.message("Double click to delete");
                flg_dblclick_inform = true;
            }
        } else if (field == 'file_rename') {
            if (row?.file_type != 'directory') {
                var filename = row.file_name;
                alertify.prompt("Enter the new name.", filename, function(evt, rename) {
                    rename = $.trim(rename);
                    if (rename.length == 0) {
                        alertify.error('Please enter a valid filename');
                        return;
                    }
                    if (rename.length > 128) {
                        alertify.error('Filename too long');
                        return;
                    }
                    if (rename == row['file_name']) {
                        alertify.success('Filename is the same');
                        return;
                    }
                    if (re_checkfile.test(rename)) {
                        $.get(
                            rename_url, {
                                'id': item_id,
                                'filename': filename,
                                'rename': rename
                            },
                            function(ret) {
                                if (ret['code'] == 1) {
                                    alertify.success(ret['msg']);
                                    table.bootstrapTable('refresh');
                                } else {
                                    alertify.error(ret['msg']);
                                }
                                return false;
                            }
                        );
                    } else {
                        alertify.error("Please enter a valid filename<br/>(English, Numbers, Underlines, and the right Extension Names)");
                    }
                }, function() {
                    //canceled
                });
            }
        }
    });

    upload_button.off('click');
    upload_button.on('click', function() {
        var file_button = $('#upload_input');
        if (/msie/i.test(navigator.userAgent.toLowerCase())) {
            file_button.click();
        } else {
            var a = document.createEvent("MouseEvents"); //FF的处理
            a.initEvent("click", true, true);
            file_button[0].dispatchEvent(a);
        }
        return false;
    });
    $(document).off('change', '#upload_input');
    $(document).on('change', '#upload_input', function() {
        var upload_file_input = $(this)
        var upload_file_form = $('#upload_form');
        var upload_file_button = $('#upload_button');
        var upload_filepath = $.trim(upload_file_input.val());
        if (upload_filepath == null || upload_filepath == '' || typeof(upload_filepath) == 'undefined')
            return;
        var maxfilesize = $('#id_input').attr('maxfilesize');
        var multifileRet = CheckMultiFile(this, maxfilesize, re_checkfile);
        var filename_check = multifileRet[0];
        var msg = multifileRet[1];
        if (filename_check == true) {
            UploadFile(
                upload_file_input,
                upload_file_form,
                upload_file_button
            );
        } else {
            alertify.alert(msg);
        }
        upload_file_input.val('');
    });

    function CheckMultiFile(filelist, maxsize, reg) {
        var filename_check = true;
        var msg = `单文件大小限制(Single file size limit):<br/>- ${Math.ceil(maxsize / 1024 / 1024)}MB<br/>文件名限制(File name limit):<br/>- 仅包含字母或数字(Only include alphabet and numbers)<br/>`;
        if (filelist.files.length > maxfilenum) {
            filename_check = false;
            msg = "我真不信有这么多测试数据，一定是你鼠标点错了<br/>一次别超过" + maxfilenum + "个。。。";
            return [filename_check, msg];
        }
        for (var i = 0; i < filelist.files.length; i++) {
            if (filelist.files[i].size > maxsize) {
                msg += '<br/>' + filelist.files[i].name + ': <br/>- 文件过大(size too large).';
                filename_check = false;
            }

            if (!reg.test(filelist.files[i].name.toLowerCase())) {
                filename_check = false;
                msg += '<br/>' + filelist.files[i].name + ': <br/>- 文件名不合法(name not valid).';
            }
        }
        return [filename_check, msg];
    }

    function UploadFile(upload_file_input, upload_file_form, upload_file_button) {
        upload_file_form.ajaxForm({
            beforeSend: function() {
                upload_file_button.attr('disabled', true);
                var percentVal = '0%';
                upload_file_button.text('Uploading' + percentVal);
            },
            uploadProgress: function(event, position, total, percentComplete) {
                var percentVal = percentComplete + '%';
                upload_file_button.text('Uploading' + percentVal);
            },
            success: function() {
                var percentVal = '100%';
                upload_file_button.text("Uploaded");
            },
            complete: function(e) {
                ret = JSON.parse(e.responseText);
                if (ret.code == 1) {
                    alertify.success(ret['msg']);
                    table.bootstrapTable('refresh');
                    upload_file_button.text('Upload File');
                    upload_file_button.removeAttr('disabled');
                    return true;
                } else {
                    alertify.alert(ret.msg);
                    button_delay(upload_file_button, 1, 'Upload File', 'Upload File');
                    return false;
                }
            }
        });
        upload_file_form.submit();
        upload_file_input.val('');
    }
    table.on('post-body.bs.table', function() {
        table.bootstrapTable('resetView', {
            'height': this.scrollHeight + 120
        });
    });
</script>

{include file="../../csgoj/view/public/refresh_in_table" /}
<style type="text/css">
    .progress {
        position: relative;
        width: 100%;
        border: 1px solid #ddd;
        padding: 1px;
        border-radius: 3px;
    }

    .bar {
        background-color: #31b0d5;
        width: 0%;
        height: 20px;
        border-radius: 3px;
    }

    .percent {
        color: black;
        position: absolute;
        display: inline-block;
        top: 3px;
        left: 48%;
    }

    .img-rounded {
        max-width: 512px;
        max-height: 512px;
    }
</style>