<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div class="d-flex align-items-center">
                    <i class="bi bi-bell-fill text-primary me-2 fs-3"></i>
                    <h3 class="mb-0 bilingual-inline">
                        比赛通知<span class="en-text">Contest Messages</span>
                    </h3>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="refreshMessages()">
                        <span class="cn-text"><i class="bi bi-arrow-clockwise me-1"></i>
                        刷新</span><span class="en-text">Refresh</span>
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="markAllAsRead()">
                        <span class="cn-text"><i class="bi bi-check-all me-1"></i>
                        全部已读</span><span class="en-text">Mark All Read</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

{include file="../../csgoj/view/contest/msg_show" /}

<script>
    // 传递模板变量给JavaScript
    window.msgConfig = {
        contest_id: <?php echo $contest['contest_id']; ?>
    };

    // 刷新消息函数
    function refreshMessages() {
        const cid = window.msgConfig.contest_id;
        fetchMessages(false);
        let msg_list_local = csg.store(`contest_msg#cid${cid}`);
        if (msg_list_local && msg_list_local.msg_list) {
            renderMessages(msg_list_local.msg_list);
        }
    }

    // 标记全部已读函数
    function markAllAsRead() {
        const cid = window.msgConfig.contest_id;
        let msg_list_local = csg.store(`contest_msg#cid${cid}`);
        if (msg_list_local && msg_list_local.msg_list) {
            // 更新本地存储的时间戳，模拟已读
            csg.store(`contest_msg#cid${cid}`, {
                msg_list: msg_list_local.msg_list,
                time: Date.now()
            });
            
            // 重新渲染消息，移除新消息标记
            renderMessages(msg_list_local.msg_list);
            
            if (typeof alerty !== 'undefined') {
                alerty.success('已标记全部消息为已读', 'All messages marked as read');
            } else if (typeof alertify !== 'undefined') {
                alertify.success('已标记全部消息为已读');
            }
        }
    }

    $(document).ready(function() {
        fetchMessages(false);
        const cid = window.msgConfig.contest_id;
        let msg_list_local = csg.store(`contest_msg#cid${cid}`);
        if (msg_list_local && msg_list_local.msg_list) {
            renderMessages(msg_list_local.msg_list);
        }
    });
</script>