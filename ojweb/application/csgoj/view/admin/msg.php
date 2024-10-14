<div id="table_toolbar">
    <div class="form-inline fake-form" role="form">
        <button class="btn btn-primary" id="add_msg_btn">Add Message</button> &nbsp;&nbsp;
        <button class="btn btn-info" id="preview_all_msg_btn">Preview All</button> &nbsp;&nbsp;
        <button class="btn btn-success" id="preview_public_msg_btn">Preview Public</button> &nbsp;&nbsp;
    </div>
</div>
<table
    id="msg_table"
    data-toggle="table"
    data-url="/{$module}/admin/msg_ajax?cid={$contest['contest_id']}"
    data-pagination="true"
    data-side-pagination="client"
    data-method="get"
    data-striped="true"
    data-search="true"
    data-search-align="left"
    data-sort-name="msg_id"
    data-sort-order="desc"
    data-pagination-v-align="bottom"
    data-pagination-h-align="left"
    data-pagination-detail-h-align="right"
    data-classes="table-no-bordered table table-hover"
    data-show-refresh="true"
    data-buttons-align="left"
    data-toolbar="#table_toolbar">
    <thead>
        <tr>
            <th data-field="msg_id" data-align="center" data-valign="middle" data-sortable="true" data-width="55">ID</th>
            <th data-field="content" data-align="left" data-valign="middle" data-sortable="true" data-formatter="FormatterContestMsgContent">Content</th>
            <th data-field="team_id" data-align="center" data-valign="middle" data-sortable="true" data-width="55" data-formatter="FormatterContestMsgUser">Sender</th>
            <th data-field="defunct" data-align="center" data-valign="middle" data-sortable="true" data-width="55" data-formatter="FormatterContestMsgStatus">Status</th>
            <th data-field="edit" data-align="center" data-valign="middle" data-sortable="true" data-width="55" data-formatter="FormatterContestMsgEdit">Edit</th>
            <th data-field="in_date" data-align="center" data-valign="middle" data-sortable="true" data-width="120">In Date</th>
        </tr>
    </thead>
</table>

<!-- Edit Modal Structure -->
<div class="modal fade" id="msg_edit_modal" tabindex="-1" role="dialog" aria-labelledby="messageModalLabel" data-backdrop="static">
    <div class="modal-dialog" role="document" style="width:800px; max-width: 100%;">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button>
                <h4 class="modal-title" id="messageModalLabel">Add/Edit Message</h4>
            </div>
            <div class="modal-body">
                <form id="messageForm">
                    <div class="form-group">
                        <label for="messageContent">Message Content (Markdown)</label>
                        <div id="vditor" class="form-control" style="height: 300px;"></div>
                        <!-- <textarea class="form-control" id="messageContent" rows="6"></textarea> -->
                        <small id="charCount" class="form-text text-muted">255 bytes remaining</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="saveMessageBtn">Save changes</button>
            </div>
        </div>
    </div>
</div>

<!-- Content Modal Structure -->
<div class="modal fade" id="contentModal" tabindex="-1" role="dialog" aria-labelledby="contentModalLabel">
    <div class="modal-dialog modal-lg" role="document" style="width:1280px; max-width: 100%;">
        <div class="modal-content">

            <div class="modal-body">
                {include file="../../csgoj/view/contest/msg_show" /}
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    function FormatterContestMsgEdit(value, row, index, field) {
        return row.defunct == 1 ? "<button class='btn btn-primary'>Edit</button>" : '-';
    }

    function FormatterContestMsgStatus(value, row, index, field) {
        let txt = value == 1 ? 'Prepared' : 'Sent';
        let color = value == 1 ? 'warning' : 'success';
        let title = value == 1 ? 'Click to send' : 'Click to callback';
        return `<button class='btn btn-${color}' title='${title}'>${txt}</button>`
    }

    function FormatterContestMsgUser(value, row, index, field) {
        if (value.includes('#')) {
            let simple = value.split('_')[1];
            return `<a href='/${page_module}/contest/teaminfo?cid=${cid}&team_id=${simple}' target='_blank'>${simple}</a>`;
        }
        return `<a href='/${page_module}/user/userinfo?user_id=${value}' target='_blank'>${value}</a>`;
    }

    function FormatterContestMsgContent(value, row, index, field) {
        return `<a href='#' class='limit_span'>${value}</a>`;
    }
    let cid = "<?php echo $contest['contest_id']; ?>";
    let page_module = "<?php echo $module; ?>";
    let isEditMode = false;
    let contest_msg_id = null;
    let msg_talbe = $('#msg_table');
    let msg_edit_modal = $('#msg_edit_modal');
    msg_talbe.on('click-cell.bs.table', function(e, field, td, row) {
        if (field == 'edit') {
            if (row.defunct == 1) {
                EditMessage(row.msg_id, row.content);
            }
        } else if (field == 'defunct') {
            alertify.confirm('确认', `发布或召回后再发布将重新向选手弹窗，确定${row.defunct == 1 ? "发布" : "召回"}？`, function() {
                let new_defunct = row.defunct == 1 ? 0 : 1;
                $.post('msg_status_change_ajax', {
                    cid: cid,
                    msg_id: row.msg_id,
                    defunct: row.defunct == 1 ? 0 : 1
                }, function(rep) {
                    if (rep.code == 1) {
                        alertify[new_defunct == 1 ? 'warning' : 'success'](rep.msg);
                        row.defunct = rep.data.defunct;
                        row.in_date = rep.data.in_date;
                        msg_talbe.bootstrapTable('updateByUniqueId', {
                            id: row.msg_id,
                            row: row
                        });
                    } else {
                        alertify.error(rep.msg);
                    }
                });
            }, function() {});
        } else if (field == 'content') {
            // 弹出展示内容的Modal
            renderMessages([row])
            $('#contentModal').modal('show');
            // $('#contentMarkdown').html(marked(row.content));
        }
    });
    document.getElementById('add_msg_btn').addEventListener('click', function() {
        isEditMode = false;
        document.getElementById('messageForm').reset();
        setVditorContent('');
        msg_edit_modal.modal('show');
    });
    document.getElementById('preview_all_msg_btn').addEventListener('click', function() {
        const allMessages = $('#msg_table').bootstrapTable('getData');
        renderMessages(allMessages);
        $('#contentModal').modal('show');
    });

    document.getElementById('preview_public_msg_btn').addEventListener('click', function() {
        const allMessages = $('#msg_table').bootstrapTable('getData');
        const publicMessages = allMessages.filter(msg => msg.defunct == 0);
        renderMessages(publicMessages);
        $('#contentModal').modal('show');
    });

    // **************************************************
    // vditor related
    const vditor = new Vditor('vditor', {
        mode: 'wysiwyg',
        height: 300,
        toolbar: [
            'emoji',
            'headings',
            'bold',
            'italic',
            'strike',
            'link',
            'list',
            'ordered-list',
            'check',
            'quote',
            'line',
            'code',
            'inline-code',
            'undo',
            'redo',
            'fullscreen',
            'edit-mode',
            'export'
        ],
        toolbarConfig: {
            pin: true,
        },
        cache: {
            enable: false,
        },
        after: () => {
            vditor.setValue('');
        },
        input: (value) => {
            updateCharCount();
        }
    });

    function getVditorContent() {
        return vditor.getValue();
    }

    function setVditorContent(content) {
        vditor.setValue(content);
    }

    function updateCharCount() {
        const maxLength = 255;
        const content = getVditorContent();
        let currentLength = StrByteLength(content);
        let remaining = maxLength - currentLength;

        if (remaining < 0) {
            // 截断超出部分
            let truncatedValue = content;
            while (StrByteLength(truncatedValue) > maxLength) {
                truncatedValue = truncatedValue.slice(0, -1);
            }
            setVditorContent(truncatedValue);
            remaining = 0;
        }

        document.getElementById('charCount').textContent = remaining + ' bytes remaining';
    }
    document.getElementById('saveMessageBtn').addEventListener('click', function() {
        const messageContent = getVditorContent(); //document.getElementById('messageContent').value;
        let data_post = {
            content: messageContent
        };
        if (isEditMode) {
            data_post.msg_id = contest_msg_id;
        }
        $.post(`/${page_module}/admin/msg_add_edit_ajax?cid=${cid}`, data_post, function(rep) {
            if (rep.code == 1) {
                // Handle response
                msg_edit_modal.modal('hide');
                msg_talbe.bootstrapTable('refresh');
            } else {
                alertify.error(rep.msg);
            }
        });
    });

    function EditMessage(messageId, content) {
        isEditMode = true;
        contest_msg_id = messageId;
        setVditorContent(content); // document.getElementById('messageContent').value = content;
        msg_edit_modal.modal('show');
    }
</script>
<style type="text/css">
    /* Ensure compatibility with both Bootstrap 3 and Bootstrap 5 */
    .modal-header .close {
        margin-top: -2px;
    }

    .limit_span {
        max-width: 550px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
</style>