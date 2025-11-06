<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex align-items-center mb-3">
                <i class="bi bi-bell-fill text-primary me-2 fs-4"></i>
                <h4 class="mb-0 bilingual-inline">
                    通知消息<span class="en-text">Messages</span>
                </h4>
                <span id="messageCount" class="badge bg-primary ms-2">0</span>
            </div>
            <div id="messageList" class="list-group list-group-flush"></div>
            <div id="noMessages" class="text-center text-muted py-5 d-none">
                <i class="bi bi-inbox fs-1 mb-3"></i>
                <p class="bilingual-inline">
                    暂无消息<span class="en-text">No messages</span>
                </p>
            </div>
        </div>
    </div>
</div>
{include file="../../csgoj/view/public/pkg_vditor" /}
<script>
    function renderMessages(messages) {
        const messageList = document.getElementById('messageList');
        const noMessages = document.getElementById('noMessages');
        const messageCount = document.getElementById('messageCount');
        
        messageList.innerHTML = '';

        if (!messages || messages.length === 0) {
            noMessages.classList.remove('d-none');
            messageCount.textContent = '0';
            return;
        }

        noMessages.classList.add('d-none');
        messageCount.textContent = messages.length;

        // 按 in_date 逆序排序
        messages.sort((a, b) => new Date(b.in_date) - new Date(a.in_date));

        const now = new Date();

        messages.forEach((msg, index) => {
            let team_id = msg.team_id;
            let team_type = '';
            if (team_id.includes('#')) {
                team_id = msg.team_id.split('_')[1];
                team_type = '比赛管理员<span class="en-text">Contest Admin</span>';
            } else {
                team_type = '系统管理员<span class="en-text">System Admin</span>';
            }

            const msgDate = new Date(msg.in_date);
            const isRecent = (now - msgDate) / (1000 * 60) <= 10; // 判断是否在10分钟内

            const messageItem = document.createElement('div');
            messageItem.className = `list-group-item list-group-item-action ${isRecent ? 'border-danger border-2' : ''}`;
            messageItem.innerHTML = `
                <div class="d-flex w-100 justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center mb-2">
                            <span class="badge bg-secondary me-2">#${messages.length - index}</span>
                            ${isRecent ? '<span class="badge bg-danger me-2"><span class="cn-text"><i class="bi bi-exclamation-triangle me-1"></i>新消息</span><span class="en-text">New</span></span>' : ''}
                        </div>
                        <div id="message-${index}" class="message-content mb-2"></div>
                        <div class="d-flex align-items-center text-muted small">
                            <i class="bi bi-clock me-1"></i>
                            <span class="me-3">${msg.in_date}</span>
                            <i class="bi bi-person me-1"></i>
                            <span class="me-1">${team_id}</span>
                            <span class="badge bg-light text-dark">${team_type}</span>
                        </div>
                    </div>
                </div>
            `;
            
            messageList.appendChild(messageItem);

            // 使用Vditor渲染消息内容
            if (typeof Vditor !== 'undefined') {
                Vditor.preview(document.getElementById(`message-${index}`), msg.content, {
                    mode: 'light',
                    hljs: {
                        style: 'dracula'
                    }
                });
            } else {
                // 回退到简单的HTML渲染
                document.getElementById(`message-${index}`).innerHTML = msg.content;
            }
        });
    }
</script>
<style>
/* 消息列表样式 */
.list-group-item {
    border-left: none;
    border-right: none;
    transition: all 0.2s ease-in-out;
}

.list-group-item:hover {
    background-color: rgba(0, 123, 255, 0.05);
}

.list-group-item:first-child {
    border-top: none;
}

.list-group-item:last-child {
    border-bottom: none;
}

/* 消息内容样式 */
.message-content {
    font-size: 1rem;
    line-height: 1.6;
}

.message-content h1,
.message-content h2,
.message-content h3,
.message-content h4,
.message-content h5,
.message-content h6 {
    margin-top: 1rem;
    margin-bottom: 0.5rem;
    color: #495057;
}

.message-content p {
    margin-bottom: 0.75rem;
}

.message-content code {
    background-color: #f8f9fa;
    padding: 0.125rem 0.25rem;
    border-radius: 0.25rem;
    font-size: 0.875em;
    color: #e83e8c;
}

.message-content pre {
    background-color: #f8f9fa;
    padding: 1rem;
    border-radius: 0.375rem;
    overflow-x: auto;
    margin-bottom: 1rem;
}

.message-content blockquote {
    border-left: 4px solid #dee2e6;
    padding-left: 1rem;
    margin: 1rem 0;
    color: #6c757d;
}

/* 新消息高亮样式 */
.border-danger {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% {
        box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.4);
    }
    70% {
        box-shadow: 0 0 0 10px rgba(220, 53, 69, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(220, 53, 69, 0);
    }
}

/* 空状态样式 */
#noMessages {
    background-color: #f8f9fa;
    border-radius: 0.5rem;
    border: 2px dashed #dee2e6;
}

/* 徽章样式优化 */
.badge {
    font-size: 0.75em;
    font-weight: 500;
}

/* 响应式设计 */
@media (max-width: 768px) {
    .message-content {
        font-size: 0.9rem;
    }
    
    .d-flex.align-items-center.text-muted.small {
        flex-direction: column;
        align-items: flex-start !important;
    }
    
    .d-flex.align-items-center.text-muted.small > * {
        margin-bottom: 0.25rem;
    }
}
</style>