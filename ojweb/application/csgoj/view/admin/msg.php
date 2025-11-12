<div class="admin-page-header">
    <div class="admin-page-header-left">
        <div class="admin-page-header-icon">
            <i class="bi bi-chat-dots"></i>
        </div>
        <h1 class="admin-page-header-title">
            <div class="admin-page-header-title-main">
                消息管理
            </div>
            <div class="admin-page-header-title-right">
                <a href="__CPC__/contest/contest?cid={$contest['contest_id']}" class="admin-page-header-id">
                    <i class="bi bi-hash"></i> {$contest['contest_id']}
                </a>
                <span class="en-text">
                    Message Management
                </span>
            </div>
        </h1>
    </div>
    <div class="admin-page-header-right">
        <button class="btn btn-primary btn-sm" id="add_msg_btn">
            <span class="cn-text"><i class="bi bi-plus-circle me-1"></i>添加消息</span><span class="en-text">Add Message</span>
        </button>
        <button class="btn btn-info btn-sm" id="preview_all_msg_btn">
            <span class="cn-text"><i class="bi bi-eye me-1"></i>预览全部</span><span class="en-text">Preview All</span>
        </button>
        <button class="btn btn-success btn-sm" id="preview_public_msg_btn">
            <span class="cn-text"><i class="bi bi-eye-fill me-1"></i>预览公开</span><span class="en-text">Preview Public</span>
        </button>
    </div>
</div>

<div class="container">
    <div id="table_toolbar" class="table-toolbar">
        <div class="d-flex align-items-center gap-2" role="form">
            <div class="toolbar-group">
                <span class="toolbar-label-inline"><span>消息状态</span><span class="toolbar-label en-text">Message Status</span></span>
                <select name="defunct" class="form-select toolbar-select msg_filter">
                    <option value="-1" selected>
                        All
                    </option>
                    <option value="0">
                        已发送 <span class="en-text">Sent</span>
                    </option>
                    <option value="1">
                        准备中 <span class="en-text">Prepared</span>
                    </option>
                </select>
            </div>
            <div class="toolbar-group">
                <span class="toolbar-label-inline"><span>搜索</span><span class="toolbar-label en-text">Search</span></span>
                <input id="msg_search_input" name="search" class="form-control toolbar-input msg_filter" type="text" placeholder="消息内容/发送者" style="width: 200px;">
            </div>
        </div>
    </div>
<table
    id="msg_table"
    class="bootstraptable_refresh_local"
    data-toggle="table"
    data-url="/{$module}/admin/msg_ajax?cid={$contest['contest_id']}"
    data-pagination="true"
    data-side-pagination="client"
    data-method="get"
    data-striped="true"
    data-search="false"
    data-search-align="left"
    data-sort-name="msg_id"
    data-sort-order="desc"
    data-pagination-v-align="bottom"
    data-pagination-h-align="left"
    data-pagination-detail-h-align="right"
    data-classes="table table-hover table-striped"
    data-show-refresh="true"
    data-buttons-align="left"
    data-toolbar="#table_toolbar"
    data-query-params="queryParams">
    <thead>
        <tr>
            <th data-field="msg_id" data-align="center" data-valign="middle" data-sortable="true" data-width="55">ID<span class="en-text">ID</span></th>
            <th data-field="content" data-align="left" data-valign="middle" data-sortable="true" data-formatter="FormatterContestMsgContent">内容<span class="en-text">Content</span></th>
            <th data-field="team_id" data-align="center" data-valign="middle" data-sortable="true" data-width="55" data-formatter="FormatterContestMsgUser">发送者<span class="en-text">Sender</span></th>
            <th data-field="defunct" data-align="center" data-valign="middle" data-sortable="true" data-width="55" data-formatter="FormatterContestMsgStatus">状态<span class="en-text">Status</span></th>
            <th data-field="edit" data-align="center" data-valign="middle" data-sortable="true" data-width="55" data-formatter="FormatterContestMsgEdit">编辑<span class="en-text">Edit</span></th>
            <th data-field="in_date" data-align="center" data-valign="middle" data-sortable="true" data-width="120">时间<span class="en-text">In Date</span></th>
        </tr>
    </thead>
</table>
{js href="__JS__/refresh_in_table.js" /}

<!-- Edit Modal Structure -->
<div class="modal fade" id="msg_edit_modal" tabindex="-1" aria-labelledby="messageModalLabel" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="messageModalLabel">
                    <span class="cn-text"><i class="bi bi-chat-dots me-2"></i>添加/编辑弹窗消息</span><span class="en-text">Add/Edit Message</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="messageForm">
                    <div class="mb-3">
                        <label for="messageContent" class="form-label">
                            消息内容 (Markdown)<span class="en-text">Message Content (Markdown)</span>
                        </label>
                        <div id="vditor" class="form-control" style="height: 300px;"></div>
                        <div id="charCount" class="form-text text-muted">255 bytes remaining</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">关闭<span class="en-text">Close</span></button>
                <button type="button" class="btn btn-primary" id="saveMessageBtn">保存更改<span class="en-text">Save Changes</span></button>
            </div>
        </div>
    </div>
</div>

<!-- Content Modal Structure -->
<div class="modal fade" id="contentModal" tabindex="-1" aria-labelledby="contentModalLabel">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="contentModalLabel">
                    <span class="cn-text"><i class="bi bi-eye me-2"></i>消息预览</span><span class="en-text">Message Preview</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                {include file="../../csgoj/view/contest/msg_show" /}
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">关闭<span class="en-text">Close</span></button>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    function FormatterContestMsgEdit(value, row, index, field) {
        return row.defunct == 1 ? "<button class='btn btn-primary btn-sm'><i class='bi bi-pencil me-1'></i>编辑<span class='en-text'>Edit</span></button>" : '-';
    }

    function FormatterContestMsgStatus(value, row, index, field) {
        let txt = value == 1 ? '准备中' : '已发送';
        let txtEn = value == 1 ? 'Prepared' : 'Sent';
        let color = value == 1 ? 'warning' : 'success';
        let title = value == 1 ? '点击发送' : '点击撤回';
        let titleEn = value == 1 ? 'Click to send' : 'Click to callback';
        return `<button class='btn btn-${color} btn-sm' title='${title} (${titleEn})'>${txt}<span class='en-text'>${txtEn}</span></button>`
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
    var page_module = "<?php echo $module; ?>";
    let isEditMode = false;
    let contest_msg_id = null;
    let msg_talbe = $('#msg_table');
    let msg_edit_modal = $('#msg_edit_modal');
    
    // 初始化消息管理工具栏（客户端分页版本）
    initBootstrapTableClientToolbar({
        tableId: 'msg_table',
        prefix: 'msg',
        filterSelectors: ['defunct'],
        searchInputId: 'msg_search_input',
        searchFields: {
            content: 'content',
            team_id: 'team_id'
        }
    });
    msg_talbe.on('click-cell.bs.table', function(e, field, td, row) {
        if (field == 'edit') {
            if (row.defunct == 1) {
                EditMessage(row.msg_id, row.content);
            }
        } else if (field == 'defunct') {
            const action = row.defunct == 1 ? "发布" : "召回";
            const actionEn = row.defunct == 1 ? "publish" : "recall";
            alerty.confirm({
                message: `发布或召回后再发布将重新向选手弹窗，确定${action}？`,
                message_en: `Publishing or recalling will re-popup to players, sure to ${actionEn}?`,
                callback: function() {
                    let new_defunct = row.defunct == 1 ? 0 : 1;
                    $.post('msg_status_change_ajax', {
                        cid: cid,
                        msg_id: row.msg_id,
                        defunct: row.defunct == 1 ? 0 : 1
                    }, function(rep) {
                        if (rep.code == 1) {
                            alerty[new_defunct == 1 ? 'warning' : 'success']({
                                message: rep.msg,
                                message_en: rep.msg
                            });
                            row.defunct = rep.data.defunct;
                            row.in_date = rep.data.in_date;
                            msg_talbe.bootstrapTable('updateByUniqueId', {
                                id: row.msg_id,
                                row: row
                            });
                        } else {
                            alerty.error({
                                message: rep.msg,
                                message_en: rep.msg
                            });
                        }
                    });
                }
            });
        } else if (field == 'content') {
            // 弹出展示内容的Modal
            renderMessages([row])
            const contentModal = new bootstrap.Modal(document.getElementById('contentModal'));
            contentModal.show();
        }
    });
    document.getElementById('add_msg_btn').addEventListener('click', function() {
        isEditMode = false;
        document.getElementById('messageForm').reset();
        setVditorContent('');
        updateCharCount();
        const editModal = new bootstrap.Modal(document.getElementById('msg_edit_modal'));
        editModal.show();
    });
    document.getElementById('preview_all_msg_btn').addEventListener('click', function() {
        const allMessages = $('#msg_table').bootstrapTable('getData');
        renderMessages(allMessages);
        const contentModal = new bootstrap.Modal(document.getElementById('contentModal'));
        contentModal.show();
    });

    document.getElementById('preview_public_msg_btn').addEventListener('click', function() {
        const allMessages = $('#msg_table').bootstrapTable('getData');
        const publicMessages = allMessages.filter(msg => msg.defunct == 0);
        renderMessages(publicMessages);
        const contentModal = new bootstrap.Modal(document.getElementById('contentModal'));
        contentModal.show();
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
            while (StrByteLength(truncatedValue) >= maxLength) {
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
                alerty.success({
                    message: rep.msg,
                    message_en: rep.msg
                });
                const editModal = bootstrap.Modal.getInstance(document.getElementById('msg_edit_modal'));
                editModal.hide();
                msg_talbe.bootstrapTable('refresh');
            } else {
                alerty.error({
                    message: rep.msg,
                    message_en: rep.msg
                });
            }
        });
    });

    function EditMessage(messageId, content) {
        isEditMode = true;
        contest_msg_id = messageId;
        setVditorContent(content);
        updateCharCount();
        const editModal = new bootstrap.Modal(document.getElementById('msg_edit_modal'));
        editModal.show();
    }
</script>
<style type="text/css">
    /* 消息管理页面样式 */
    .limit_span {
        display: inline-block;
        max-width: 550px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    
    
    /* 表格按钮样式 */
    .btn-sm .en-text {
        font-size: 0.6em;
        margin-left: 4px;
    }
    
    /* Modal样式优化 */
    .modal-xl {
        max-width: 90%;
    }
    
    /* Vditor编辑器样式 */
    #vditor {
        border: 1px solid #ced4da;
        border-radius: 0.375rem;
    }
    
    #vditor:focus {
        border-color: #86b7fe;
        outline: 0;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }
    
    /* Bootstrap Table 工具栏样式 */
    .fixed-table-toolbar {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .fixed-table-toolbar .columns {
        order: -1;
    }
    
    .bootstrap-table .fixed-table-toolbar {
        padding: 0.5rem 0;
    }
</style>
</div>