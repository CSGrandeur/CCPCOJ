<!-- URL Modal -->
<div class="modal fade" id="urlModal" tabindex="-1" aria-labelledby="urlModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" style="max-width: min(95vw, 1600px); height: 90vh; margin: 5vh auto;">
        <div class="modal-content h-100 d-flex flex-column">
            <div class="modal-header d-flex justify-content-between align-items-center flex-shrink-0">
                <h5 class="modal-title mb-0" id="urlModalLabel">
                    <i class="bi bi-link-45deg"></i> 
                    <span id="modalTitle" class="modal-title-text">加载中...</span>
                </h5>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="copyUrlBtn" title="复制链接 (Copy Link)">
                        <i class="bi bi-clipboard"></i>
                    </button>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body p-0 flex-grow-1 d-flex flex-column">
                <div id="modalLoading" class="d-flex justify-content-center align-items-center flex-grow-1">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">加载中...</span>
                        </div>
                        <p class="mt-2 text-muted">正在加载页面...</p>
                    </div>
                </div>
                <iframe id="modalIframe" 
                        src="" 
                        style="width: 100%; height: 100%; border: none; min-width: 100%; flex-grow: 1;"
                        >
                </iframe>
            </div>
            <div class="modal-footer flex-shrink-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">关闭</button>
                <a id="modalOpenLink" href="#" target="_blank" class="btn btn-primary">
                    <i class="bi bi-box-arrow-up-right"></i> 在新窗口打开
                </a>
            </div>
        </div>
    </div>
</div>

<script>
// 全局变量存储当前 URL
if(typeof(currentUrl) == 'undefined') {
    var currentUrl = '';
}

// HTML 安全过滤函数
function escapeHtml(text) {
    if (typeof text !== 'string') {
        return '';
    }
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// URL 安全过滤函数
function escapeUrl(url) {
    if (typeof url !== 'string') {
        return '';
    }
    // 基本的URL安全过滤，防止javascript:等危险协议
    try {
        const urlObj = new URL(url, window.location.origin);
        // 只允许 http 和 https 协议
        if (urlObj.protocol === 'http:' || urlObj.protocol === 'https:') {
            return urlObj.href;
        }
    } catch (e) {
        // URL解析失败，返回空字符串
        return '';
    }
    return '';
}

// URL Modal 功能
function openUrlModal(url, title) {
    // 对输入进行安全过滤
    const safeUrl = escapeUrl(url);
    const safeTitle = escapeHtml(title || '页面预览');
    
    if (!safeUrl) {
        console.error('Invalid URL provided to openUrlModal:', url);
        return;
    }
    
    currentUrl = safeUrl;
    
    // 设置标题和链接（已进行安全过滤）
    document.getElementById('modalTitle').textContent = safeTitle;
    document.getElementById('modalOpenLink').href = safeUrl;
    document.getElementById('copyUrlBtn').setAttribute('data-url', safeUrl);
    
    // 重置状态
    const modalLoading = document.getElementById('modalLoading');
    const modalIframe = document.getElementById('modalIframe');
    
    modalLoading.style.setProperty('display', 'flex', 'important');
    modalIframe.style.setProperty('display', 'none', 'important');
    modalIframe.src = ''; // 先清空
    
    // 使用 Bootstrap 5 原生 modal
    const modal = new bootstrap.Modal(document.getElementById('urlModal'));
    modal.show();
}

// 复制链接功能
function copyToClipboard(text) {
    if (navigator.clipboard && window.isSecureContext) {
        return navigator.clipboard.writeText(text).then(() => true).catch(() => false);
    } else {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        textArea.style.top = '-999999px';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        
        try {
            const successful = document.execCommand('copy');
            document.body.removeChild(textArea);
            return Promise.resolve(successful);
        } catch (err) {
            document.body.removeChild(textArea);
            return Promise.resolve(false);
        }
    }
}

// 页面加载完成后绑定事件
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('urlModal');
    const modalIframe = document.getElementById('modalIframe');
    const modalLoading = document.getElementById('modalLoading');
    
    // 使用事件委托绑定所有带有 data-modal-url 属性的元素（包括动态生成的）
    document.addEventListener('click', function(e) {
        // 检查点击的元素是否有 data-modal-url 属性
        const modalTrigger = e.target.closest('[data-modal-url]');
        if (modalTrigger) {
            e.preventDefault();
            const url = modalTrigger.getAttribute('data-modal-url');
            const title = modalTrigger.getAttribute('data-modal-title') || '页面预览';
            openUrlModal(url, title);
        }
    });
    
    // 绑定复制链接按钮
    document.getElementById('copyUrlBtn').addEventListener('click', function() {
        const url = this.getAttribute('data-url');
        if (url) {
            copyToClipboard(url).then(function(success) {
                const btn = document.getElementById('copyUrlBtn');
                const originalText = btn.innerHTML;
                
                if (success) {
                    btn.innerHTML = '<i class="bi bi-check"></i>';
                    btn.classList.remove('btn-outline-secondary');
                    btn.classList.add('btn-success');
                    btn.title = '已复制 (Copied)';
                    
                    setTimeout(function() {
                        btn.innerHTML = originalText;
                        btn.classList.remove('btn-success');
                        btn.classList.add('btn-outline-secondary');
                        btn.title = '复制链接 (Copy Link)';
                    }, 2000);
                } else {
                    btn.innerHTML = '<i class="bi bi-x"></i>';
                    btn.classList.remove('btn-outline-secondary');
                    btn.classList.add('btn-danger');
                    btn.title = '复制失败 (Copy Failed)';
                    
                    setTimeout(function() {
                        btn.innerHTML = originalText;
                        btn.classList.remove('btn-danger');
                        btn.classList.add('btn-outline-secondary');
                        btn.title = '复制链接 (Copy Link)';
                    }, 2000);
                }
            });
        }
    });
    
    // Bootstrap 5 原生事件 - modal 显示后加载 iframe
    modal.addEventListener('shown.bs.modal', function() {
        if (currentUrl) {
            modalIframe.src = currentUrl;
            // iframe 加载完成事件
            modalIframe.onload = function() {
                modalLoading.style.setProperty('display', 'none', 'important');
                modalIframe.style.setProperty('display', 'block', 'important');
            };
            
            // 超时保护
            setTimeout(function() {
                if (modalLoading.style.display !== 'none') {
                    modalLoading.style.setProperty('display', 'none', 'important');
                    modalIframe.style.setProperty('display', 'block', 'important');
                }
            }, 3000);
        }
    });
    
    // Bootstrap 5 原生事件 - modal 隐藏后清理
    modal.addEventListener('hidden.bs.modal', function() {
        modalIframe.src = '';
        modalLoading.style.setProperty('display', 'flex', 'important');
        modalIframe.style.setProperty('display', 'none', 'important');
        currentUrl = '';
    });
});

// 动态调整模态框标题宽度
function updateModalTitleWidth() {
    const modalTitle = document.getElementById('modalTitle');
    if (modalTitle) {
        const windowWidth = window.innerWidth;
        const modalMaxWidth = Math.min(windowWidth * 0.95, 1600); // 模态框最大宽度
        const titleMaxWidth = Math.min(windowWidth * 0.8, modalMaxWidth * 0.8); // 标题最大宽度
        modalTitle.style.maxWidth = Math.floor(titleMaxWidth) + 'px';
    }
}

// 页面加载完成后初始化标题宽度
document.addEventListener('DOMContentLoaded', function() {
    updateModalTitleWidth();
    
    // 窗口大小变化时重新调整
    window.addEventListener('resize', function() {
        updateModalTitleWidth();
    });
});

// 模态框显示时重新调整标题宽度
document.addEventListener('shown.bs.modal', function(e) {
    if (e.target.id === 'urlModal') {
        updateModalTitleWidth();
    }
});
</script>

<style>
/* 模态框标题样式 */
.modal-title-text {
    display: inline-block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    vertical-align: middle;
    transition: max-width 0.3s ease;
}

/* 确保模态框头部布局正确 */
.modal-header {
    min-height: 60px;
}

.modal-header .modal-title {
    flex: 1;
    margin-right: 1rem;
}

/* 响应式调整 */
@media (max-width: 768px) {
    .modal-title-text {
        max-width: 60vw !important;
    }
}

@media (max-width: 576px) {
    .modal-title-text {
        max-width: 50vw !important;
    }
}
</style>
