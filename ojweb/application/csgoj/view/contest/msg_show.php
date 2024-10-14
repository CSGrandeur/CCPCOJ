<div class="container">
    <h1 class="text-center">通知消息/Message</h1>
    <div id="messageList" class="list-group"></div>
</div>
{include file="../../csgoj/view/public/pkg_vditor" /}
<script>
    function renderMessages(messages) {
        const messageList = $('#messageList');
        messageList.empty();

        // 按 in_date 逆序排序
        messages.sort((a, b) => new Date(b.in_date) - new Date(a.in_date));

        const now = new Date();

        messages.forEach((msg, index) => {
            let team_id = msg.team_id;
            if (team_id.includes('#')) {
                team_id = `${msg.team_id.split('_')[1]} (Contest)`;
            } else {
                team_id = `${msg.team_id} (System)`;
            }

            const msgDate = new Date(msg.in_date);
            const isRecent = (now - msgDate) / (1000 * 60) <= 10; // 判断是否在10分钟内

            const messageItem = $(`
                <div class="list-group-item message-item ${isRecent ? 'recent-message' : ''}">
                    <div id="message-${index}" class="message-info"></div>
                    <small class="text-muted">Date: ${msg.in_date} | Admin: ${team_id}</small>
                </div>
            `);
            messageList.append(messageItem);

            Vditor.preview(document.getElementById(`message-${index}`), msg.content, {
                mode: 'light',
                hljs: {
                    style: 'dracula'
                }
            });
        });
    }
</script>
<style>
    .message-item {
        margin-bottom: 20px;
    }

    .message-item small {
        display: block;
        margin-top: 10px;
    }

    .message-info {
        font-size: 1.2em;
        /* 调整字体大小 */
    }

    .recent-message {
        border: 2px solid red; /* 设置红色外框 */
    }
</style>