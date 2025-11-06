{if $controller == 'contest'}

<div class="modal fade" id="contentModal" tabindex="-1" aria-labelledby="contentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title bilingual-inline" id="contentModalLabel">
                    <span class="cn-text"><i class="bi bi-bell me-2"></i>
                    比赛通知</span><span class="en-text">Contest Messages</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                {include file="../../csgoj/view/contest/msg_show" /}
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary bilingual-button" data-bs-dismiss="modal">
                    <span><i class="bi bi-x-circle me-1"></i>关闭</span>
                    <span class="en-text">Close</span>
                </button>
            </div>
        </div>
    </div>
</div>


<script>
    const TIME_MSG_RECENT = 60000;
    const TIME_FETCH_INTERVAL = 60000;
    const TIME_REFRESH_FETCH = 180000;

    function MsgGetCurrentTime() {
        return new Date(document.getElementById('current_time_div').textContent.trim()).getTime();
    }
    let remote_msg_callback, other_msg_dif;
    let fetchMessagesInterval;

    function CompareMsg(msg_list_local, msg_list) {
        if (!Array.isArray(msg_list_local) || !Array.isArray(msg_list)) {
            return false;
        }
        remote_msg_callback = false;
        other_msg_dif = false;
        let ret = true;
        let localMap = {};
        let remoteMap = {};
        msg_list_local.forEach(msg => {
            localMap[msg.msg_id] = msg.in_date;
        });
        msg_list.forEach(msg => {
            remoteMap[msg.msg_id] = msg.in_date;
        });
        for (let id in localMap) {
            if (localMap[id] !== remoteMap[id]) {
                remote_msg_callback = !remote_msg_callback && !(id in remoteMap);
                other_msg_dif = other_msg_dif || (id in remoteMap);
                ret = false;
            }
        }
        for (let id in remoteMap) {
            if (remoteMap[id] !== localMap[id]) {
                other_msg_dif = true;
                ret = false;
            }
        }
        return ret;
    }

    function fetchMessages(show = true) {
        // AJAX 请求获取 msg_list
        let cid = <?php echo $contest['contest_id'] ?>;
        $.get(`msg_list_ajax?cid=${cid}`, function(rep) {
            let msg_list = rep;
            if(!Array.isArray(msg_list)) {
                console.error('msg_list is not an array', msg_list);
            }
            let msg_list_local_obj = csg.store(`contest_msg#cid${cid}`);
            let currentTime = MsgGetCurrentTime();
            if (!msg_list_local_obj || !CompareMsg(msg_list_local_obj.msg_list, msg_list)) {
                csg.store(`contest_msg#cid${cid}`, {
                    msg_list: msg_list,
                    time: currentTime
                });
                if (show && (!remote_msg_callback || other_msg_dif)) { // 仅有远程message撤回的情况下不弹窗
                    renderMessages(msg_list);
                    const modal = new bootstrap.Modal(document.getElementById('contentModal'));
                    modal.show();
                }
            }

            updateMsgNum(msg_list);
        });
    }

    function updateMsgNum(msg_list) {
        if (!Array.isArray(msg_list)) {
            return;
        }
        let msg_num = document.getElementById('msg_num');
        msg_num.textContent = msg_list.length;

        let hasRecentMsg = msg_list.some(function(msg) {
            let msgDate = new Date(msg.in_date).getTime();
            return (new Date().getTime()) - msgDate < TIME_MSG_RECENT;
        });

        if (hasRecentMsg) {
            msg_num.classList.add('text-danger');
        } else {
            msg_num.classList.remove('text-danger');
        }
    }

    function handleVisibilityChange() {
        if (document.visibilityState === 'visible') {
            startFetchingMessages();
        } else {
            stopFetchingMessages();
        }
    }

    function startFetchingMessages() {
        if (!fetchMessagesInterval) {
            fetchMessagesInterval = setInterval(fetchMessages, TIME_FETCH_INTERVAL); 
        }
    }

    function stopFetchingMessages() {
        if (fetchMessagesInterval) {
            clearInterval(fetchMessagesInterval);
            fetchMessagesInterval = null;
        }
    }

    function ContestMsgTimingInit() {
        let currentTime = MsgGetCurrentTime();
        if (!currentTime) {
            setTimeout(ContestMsgTimingInit, 1000);
            return;
        }
        let startTime = new Date(document.getElementById('start_time_span').textContent.trim()).getTime();
        let endTime = new Date(document.getElementById('end_time_span').textContent.trim()).getTime();
        let cid = <?php echo $contest['contest_id'] ?>;

        if (currentTime >= startTime && currentTime <= endTime) {
            document.addEventListener('visibilitychange', handleVisibilityChange);
            handleVisibilityChange(); // 初始化时检查页面可见性
        }
        let auto_show = <?php echo !$isContestAdmin && (!isset($isContestStaff) || !$isContestStaff) ? "true" : "false"; ?>;
        let msg_list_local_obj = csg.store(`contest_msg#cid${cid}`);
        if (!msg_list_local_obj || MsgGetCurrentTime() - msg_list_local_obj.time > TIME_REFRESH_FETCH) {
            fetchMessages(auto_show);
        } else {
            updateMsgNum(msg_list_local_obj.msg_list);
        }
        document.getElementById('show_msg_btn').addEventListener('click', async function() {
            await fetchMessages(false);
            const cid = <?php echo $contest['contest_id']; ?>;
            let msg_list_local = csg.store(`contest_msg#cid${cid}`);
            if(!msg_list_local) {
                msg_list_local = {msg_list: []};
            }
            renderMessages(msg_list_local.msg_list);
            const modal = new bootstrap.Modal(document.getElementById('contentModal'));
            modal.show();
        });
    }

    document.addEventListener('DOMContentLoaded', ContestMsgTimingInit);
</script>
{/if}