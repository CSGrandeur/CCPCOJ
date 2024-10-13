<div id="table_toolbar">
    <div class="form-inline fake-form" role="form">
        <button class="btn btn-primary" id="add_msg_btn">Add Message</button> &nbsp;&nbsp;
    </div>
</div>
<table
    id="problemset_table"
    data-toggle="table"
    data-url="{$module}/admin/msg_ajax"
    data-pagination="true"
    data-side-pagination="client"
    data-method="get"
    data-striped="true"
    data-search="true"
    data-search-align="left"
    data-sort-name="problem_id"
    data-sort-order="asc"
    data-pagination-v-align="both"
    data-pagination-h-align="left"
    data-pagination-detail-h-align="right"
    data-classes="table-no-bordered table table-hover"
    data-show-refresh="true"
    data-buttons-align="left"
    data-toolbar="#table_toolbar"
>
    <thead>
    <tr>
        <th data-field="msg_id"     data-align="center" data-valign="middle"  data-sortable="true" data-width="55">ID</th>
        <th data-field="content"    data-align="center" data-valign="middle"  data-sortable="true" >Content</th>
        <th data-field="team_id"    data-align="center" data-valign="middle"  data-sortable="true" data-width="55">Add User</th>
        <th data-field="defunct"    data-align="center" data-valign="middle"  data-sortable="true" data-width="55">Status</th>
        <th data-field="edit"    data-align="center" data-valign="middle"  data-sortable="true" data-width="55" formatter="FormatterContestMsgEdit">Edit</th>
        <th data-field="in_date"    data-align="center" data-valign="middle"  data-sortable="true" data-width="120">In Date</th>
    </tr>
    </thead>
</table>

<!-- Modal Structure -->
<div class="modal fade" id="messageModal" tabindex="-1" role="dialog" aria-labelledby="messageModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="messageModalLabel">Add/Edit Message</h4>
            </div>
            <div class="modal-body">
                <form id="messageForm">
                    <div class="form-group">
                        <label for="messageContent">Message Content (Markdown)</label>
                        <textarea class="form-control" id="messageContent" rows="6"></textarea>
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

<script type="text/javascript">

function FormatterContestMsgEdit(value, row, index, field) {
    return "<button class='btn btn-primary>Edit</button>"
}
let cid = "<?php echo $contest['contest_id']; ?>";
let page_module = "<?php echo $module; ?>";
let isEditMode = false;
let contest_msg_id = null;

document.getElementById('add_msg_btn').addEventListener('click', function() {
    isEditMode = false;
    document.getElementById('messageForm').reset();
    $('#messageModal').modal('show');
});

document.getElementById('saveMessageBtn').addEventListener('click', function() {
    const messageContent = document.getElementById('messageContent').value;
    let data_post = { content: messageContent };
    if(isEditMode) {
        data_post.msg_id = contest_msg_id;
    }
    $.post(`/${page_module}/admin/msg_add_edit_ajax?cid=${cid}`, data_post, function(rep) {
        if(rep.code == 1) {
            // Handle response
            $('#messageModal').modal('hide');
            $('#problemset_table').bootstrapTable('refresh');
        } else {
            alertify.error(rep.msg);
        }
    });
});

function EditMessage(messageId, content) {
    isEditMode = true;
    contest_msg_id = messageId;
    document.getElementById('messageContent').value = content;
    $('#messageModal').modal('show');
}
</script>
<style type="text/css">
/* Ensure compatibility with both Bootstrap 3 and Bootstrap 5 */
.modal-header .close {
    margin-top: -2px;
}
</style>