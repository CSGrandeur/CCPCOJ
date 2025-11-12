/**
 * Alerty - Bootstrap 5 弹窗和通知系统
 * 模仿 alertify.js 接口，支持中英双语
 */

// 避免重复声明
if (typeof window.alerty !== 'undefined') {
    // console.warn('Alerty already initialized, skipping...');
} else {

class Alerty {
    constructor() {
        this.notificationContainer = null;
        this.notificationCount = 0;
        this.maxNotifications = 5;
        this.init();
    }

    init() {
        this.createNotificationContainer();
    }

    // 创建通知容器
    createNotificationContainer() {
        if (!this.notificationContainer) {
            if (!document.body) {
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', () => {
                        this.createNotificationContainer();
                    });
                    return;
                }
            }
            
            this.notificationContainer = document.createElement('div');
            this.notificationContainer.id = 'alerty-notifications';
            this.notificationContainer.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
                max-width: 400px;
            `;
            document.body.appendChild(this.notificationContainer);
        }
    }

    // 检查样式值是否包含单位
    hasUnit(value) {
        return typeof value === 'string' && 
               (value.includes('%') || value.includes('px') || value.includes('vw') || 
                value.includes('vh') || value.includes('rem') || value.includes('em'));
    }

    // 生成样式属性值
    generateStyleValue(value, property) {
        if (typeof value === 'number') {
            return `${property}: ${value}px !important;`;
        } else if (typeof value === 'string') {
            if (this.hasUnit(value)) {
                return `${property}: ${value} !important;`;
            } else {
                return `${property}: ${value}px !important;`;
            }
        }
        return '';
    }

    // 获取模态框样式
    getModalDialogStyle(options) {
        let style = '';
        const width = options.width || options.size;
        
        // 处理自定义宽度
        if (width && !['sm', 'lg', 'xl', 'fullscreen'].includes(width)) {
            style += this.generateStyleValue(width, 'width');
        }
        
        // 处理高度
        if (options.height) {
            style += this.generateStyleValue(options.height, 'height');
        }
        
        return style;
    }

    // 创建模态框
    createModal(options) {
        const title = options.type === 'confirm' ? '确认<span class="en-text">Confirm</span>' : '提示<span class="en-text">Tip</span>';
        const modalId = 'alerty-modal-' + Date.now();
        const width = options.width || options.size;
        
        // 处理预设尺寸类
        let modalDialogClass = 'modal-dialog';
        const presetSizes = {
            'sm': ' modal-sm',
            'lg': ' modal-lg', 
            'xl': ' modal-xl',
            'fullscreen': ' modal-fullscreen'
        };
        
        if (presetSizes[width]) {
            modalDialogClass += presetSizes[width];
        }
        
        const modalDialogStyle = this.getModalDialogStyle(options);
        
        // 生成自定义宽度类
        let customWidthClass = '';
        if (modalDialogStyle && width && !presetSizes[width]) {
            customWidthClass = ` alerty-custom-width-${Date.now()}`;
        }
        
        // 根据 allowBackdropClose 参数决定是否允许点击空白关闭
        const allowBackdropClose = options.allowBackdropClose === true;
        const backdropAttr = allowBackdropClose ? 'true' : 'static';
        
        const modalHtml = `
            <div class="modal fade" id="${modalId}" tabindex="-1" aria-labelledby="${modalId}Label" aria-hidden="true" data-bs-backdrop="${backdropAttr}" data-bs-keyboard="false">
                <div class="${modalDialogClass}${customWidthClass}"${modalDialogStyle ? ` style="${modalDialogStyle}"` : ''}>
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title bilingual-inline" id="${modalId}Label">${options.title || title}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            ${this.formatContent(options.message, options.message_en)}
                        </div>
                        <div class="modal-footer">
                            ${this.createModalButtons(options)}
                        </div>
                    </div>
                </div>
            </div>
        `;

        // 移除旧的模态框
        const oldModal = document.getElementById(modalId);
        if (oldModal) {
            oldModal.remove();
        }

        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // 添加自定义样式
        if (customWidthClass) {
            this.addCustomModalStyle(modalId, customWidthClass, modalDialogStyle, width);
        }
        
        return modalId;
    }

    // 添加自定义模态框样式
    addCustomModalStyle(modalId, customWidthClass, modalDialogStyle, width) {
        const styleId = `alerty-style-${modalId}`;
        const style = document.createElement('style');
        style.id = styleId;
        style.textContent = `
            .${customWidthClass.trim()} {
                ${modalDialogStyle}
                max-width: ${typeof width === 'number' ? width + 'px' : width} !important;
            }
        `;
        document.head.appendChild(style);
        
        // 清理样式
        const modalElement = document.getElementById(modalId);
        modalElement.addEventListener('hidden.bs.modal', () => {
            const styleElement = document.getElementById(styleId);
            if (styleElement) {
                styleElement.remove();
            }
        });
    }

    // 格式化内容（支持中英双语）
    formatContent(message, message_en) {
        if (message_en && message !== message_en) {
            return `
                <div class="alerty-bilingual">
                    <div class="alerty-primary">${this.formatTextWithLineBreaks(message)}</div>
                    <div class="alerty-secondary">${this.formatTextWithLineBreaks(message_en)}</div>
                </div>
            `;
        }
        return `<div>${this.formatTextWithLineBreaks(message)}</div>`;
    }
    
    // 格式化文本，智能处理 \n 换行符和 HTML 标签
    formatTextWithLineBreaks(text) {
        if (!text) return '';
        
        let processed = String(text);
        
        // 先处理转义的换行符（字符串字面量 "\\n"），转换为真正的换行符
        // 这处理了可能被双重转义的情况
        processed = processed.replace(/\\n/g, '\n');
        processed = processed.replace(/\\r\\n/g, '\r\n');
        processed = processed.replace(/\\r/g, '\r');
        
        // 处理各种换行符：\r\n, \r, \n 都转换为 <br>
        processed = processed.replace(/\r\n/g, '<br>');
        processed = processed.replace(/\r/g, '<br>');
        processed = processed.replace(/\n/g, '<br>');
        
        // 处理已存在的 <br> 和 <br/> 标签（统一为 <br>）
        processed = processed.replace(/<br\s*\/?>/gi, '<br>');
        
        // 使用白名单方式处理 HTML：只允许安全的标签
        processed = this.sanitizeHtml(processed);
        
        return processed;
    }
    
    // HTML 转义函数（防止 XSS）
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // 安全的 HTML 处理：只允许白名单中的标签
    sanitizeHtml(html) {
        // 白名单：允许的 HTML 标签
        const allowedTags = {
            'br': true,
            'strong': true,
            'b': true,
            'em': true,
            'i': true,
            'u': true,
            'span': true,
            'p': true,
            'div': true,
            'code': true,
            'pre': true
        };
        
        // 临时替换允许的标签为占位符（使用更安全的占位符格式）
        const placeholders = {};
        let placeholderIndex = 0;
        
        // 匹配所有 HTML 标签（包括自闭合标签）
        const tagRegex = /<\/?([a-z][a-z0-9]*)\b[^>]*\/?>/gi;
        
        // 先处理允许的标签，用占位符替换
        let processed = html.replace(tagRegex, (match, tagName) => {
            const lowerTagName = tagName.toLowerCase();
            if (allowedTags[lowerTagName]) {
                // 使用更独特的占位符，避免与文本内容冲突
                const placeholder = `__ALERTY_SAFE_TAG_${placeholderIndex}_${Date.now()}__`;
                placeholders[placeholder] = match;
                placeholderIndex++;
                return placeholder;
            }
            // 不允许的标签转义
            return this.escapeHtml(match);
        });
        
        // 转义所有剩余的 HTML 特殊字符
        processed = this.escapeHtml(processed);
        
        // 恢复占位符（允许的标签）- 使用全局替换确保所有占位符都被恢复
        for (const placeholder in placeholders) {
            // 使用正则表达式进行全局替换，确保所有占位符都被恢复
            const regex = new RegExp(this.escapeRegex(placeholder), 'g');
            processed = processed.replace(regex, placeholders[placeholder]);
        }
        
        return processed;
    }
    
    // 转义正则表达式特殊字符
    escapeRegex(str) {
        return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    // 创建模态框按钮
    createModalButtons(options) {
        if (options.type === 'confirm') {
            return `
                <button type="button" class="btn btn-secondary" id="alerty-cancel-btn">${options.cancelText || '取消'}</button>
                <button type="button" class="btn btn-primary" id="alerty-confirm-btn">${options.okText || '确定'}</button>
            `;
        } else {
            return `<button type="button" class="btn btn-primary" id="alerty-ok-btn" data-bs-dismiss="modal">${options.okText || '确定'}</button>`;
        }
    }

    // 显示模态框
    showModal(modalId, options) {
        const modalElement = document.getElementById(modalId);
        // 根据 allowBackdropClose 参数决定是否允许点击空白区域关闭
        const allowBackdropClose = options.allowBackdropClose === true;
        const modal = new bootstrap.Modal(modalElement, {
            backdrop: allowBackdropClose ? true : 'static',
            keyboard: false
        });

        // 解析回调函数
        const callback = options.callback;
        const callbackConfirm = options.callbackConfirm || callback;
        const callbackCancel = options.callbackCancel;

        // 绑定按钮事件
        this.bindModalEvents(modalElement, modal, callbackConfirm, callbackCancel, allowBackdropClose);

        modal.show();
        
        // 确保模态框获得焦点
        modalElement.addEventListener('shown.bs.modal', () => {
            modalElement.focus();
        });
    }

    // 绑定模态框事件
    bindModalEvents(modalElement, modal, callbackConfirm, callbackCancel, allowBackdropClose) {
        const confirmBtn = modalElement.querySelector('#alerty-confirm-btn');
        const alertBtn = modalElement.querySelector('#alerty-ok-btn'); // alert 类型的确定按钮
        const closeBtn = modalElement.querySelector('.btn-close'); // 关闭按钮（叉叉）
        const cancelBtn = modalElement.querySelector('#alerty-cancel-btn');

        // 标记模态框关闭的原因（用于决定执行哪个回调）
        let closeReason = null; // 'confirm', 'cancel', 'escape', 'backdrop'
        
        // 确认按钮事件
        if (confirmBtn) {
            confirmBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                closeReason = 'confirm';
                modal.hide();
            });
        }
        
        // Alert按钮事件（确定按钮）
        if (alertBtn && !confirmBtn) {
            alertBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                closeReason = 'confirm';
                modal.hide();
            });
        }
        
        // 关闭按钮事件（叉叉）
        // confirm 类型：关闭按钮等同于取消按钮
        // alert 类型：关闭按钮等同于确定按钮（都应该执行回调）
        if (closeBtn) {
            closeBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                // 如果存在取消按钮（confirm 类型），关闭按钮等同于取消
                // 如果不存在取消按钮（alert 类型），关闭按钮等同于确定
                closeReason = cancelBtn ? 'cancel' : 'confirm';
                modal.hide();
            });
        }
        
        // 取消按钮事件
        if (cancelBtn) {
            cancelBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                closeReason = 'cancel';
                modal.hide();
            });
        }

        // 键盘事件处理
        const handleKeydown = (e) => {
            if (e.key === 'Enter') {
                // 确定按钮（confirm 或 alert 类型）
                const okBtn = confirmBtn || alertBtn;
                if (okBtn) {
                    e.preventDefault();
                    closeReason = 'confirm';
                    okBtn.click();
                }
            } else if (e.key === 'Escape') {
                e.preventDefault();
                // ESC 键等同于关闭按钮：confirm 类型等同于取消，alert 类型等同于确定
                closeReason = cancelBtn ? 'cancel' : 'confirm';
                modal.hide();
            }
        };

        modalElement.addEventListener('keydown', handleKeydown);
        
        // 处理点击空白区域关闭（如果允许）
        if (allowBackdropClose) {
            // 监听 hide.bs.modal 事件，检查是否是因为点击 backdrop 关闭
            modalElement.addEventListener('hide.bs.modal', (e) => {
                // 如果 closeReason 还没有被设置，说明是点击了 backdrop
                if (closeReason === null) {
                    closeReason = 'backdrop';
                }
            });
        }
        
        // 清理事件和回调执行
        const handleModalHidden = () => {
            modalElement.removeEventListener('keydown', handleKeydown);
            modalElement.removeEventListener('hidden.bs.modal', handleModalHidden);
            modalElement.remove();
            // 根据关闭原因执行相应回调
            if (closeReason === 'confirm' && callbackConfirm && typeof callbackConfirm === 'function') {
                callbackConfirm();
            } else if ((closeReason === 'cancel' || closeReason === 'backdrop') && callbackCancel && typeof callbackCancel === 'function') {
                // 取消按钮、关闭按钮（confirm 类型）或点击空白区域，执行取消回调
                callbackCancel();
            }
            // 注意：alert 类型的关闭按钮会被设置为 'confirm'，会执行 callbackConfirm
        };
        
        modalElement.addEventListener('hidden.bs.modal', handleModalHidden);
    }

    // 创建通知元素
    createNotification(options) {
        const notificationId = 'alerty-notification-' + Date.now();
        const typeClass = this.getTypeClass(options.type);
        const icon = this.getTypeIcon(options.type);
        const currentTime = this.getCurrentTimeString();
        
        const notificationHtml = `
            <div class="alert ${typeClass} alert-dismissible fade show alerty-notification" 
                 id="${notificationId}" 
                 style="margin-bottom: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                <div class="d-flex align-items-start">
                    <i class="${icon} me-2 mt-1"></i>
                    <div class="flex-grow-1">
                        ${this.formatContent(options.message, options.message_en)}
                        <div class="alerty-timestamp">${currentTime}</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            </div>
        `;

        return { id: notificationId, html: notificationHtml };
    }

    // 获取当前时间字符串
    getCurrentTimeString() {
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        
        return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
    }

    // 获取类型样式类
    getTypeClass(type) {
        const typeMap = {
            'success': 'alert-success',
            'error': 'alert-danger',
            'warning': 'alert-warning',
            'info': 'alert-info',
            'notify': 'alert-info'
        };
        return typeMap[type] || 'alert-info';
    }

    // 获取类型图标
    getTypeIcon(type) {
        const iconMap = {
            'success': 'bi bi-check-circle-fill',
            'error': 'bi bi-exclamation-triangle-fill',
            'warning': 'bi bi-exclamation-triangle-fill',
            'info': 'bi bi-info-circle-fill',
            'notify': 'bi bi-bell-fill'
        };
        return iconMap[type] || 'bi bi-info-circle-fill';
    }

    // 显示通知
    showNotification(options) {
        if (!this.notificationContainer) {
            this.createNotificationContainer();
        }
        
        if (!this.notificationContainer) {
            setTimeout(() => {
                this.showNotification(options);
            }, 100);
            return;
        }
        
        const notification = this.createNotification(options);
        
        // 限制通知数量
        if (this.notificationCount >= this.maxNotifications) {
            const oldestNotification = this.notificationContainer.querySelector('.alerty-notification');
            if (oldestNotification) {
                oldestNotification.remove();
                this.notificationCount--;
            }
        }

        // 保存当前滚动位置，防止插入通知时页面滚动
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        const scrollLeft = window.pageXOffset || document.documentElement.scrollLeft;
        
        this.notificationContainer.insertAdjacentHTML('beforeend', notification.html);
        this.notificationCount++;
        
        // 防止新插入的通知元素获得焦点（可能导致页面滚动）
        const notificationElement = document.getElementById(notification.id);
        if (notificationElement) {
            notificationElement.setAttribute('tabindex', '-1');
            // 确保通知元素不会导致页面滚动
            notificationElement.style.outline = 'none';
        }

        // 使用 requestAnimationFrame 确保在 DOM 更新后立即恢复滚动位置
        requestAnimationFrame(() => {
            window.scrollTo(scrollLeft, scrollTop);
            // 再次确保滚动位置（某些浏览器可能需要两次调用）
            if (window.pageYOffset !== scrollTop || window.pageXOffset !== scrollLeft) {
                window.scrollTo(scrollLeft, scrollTop);
            }
        });
        
        this.setupAutoHide(notification.id, options.duration || 3000);
    }

    // 设置自动隐藏和鼠标悬停暂停功能
    setupAutoHide(notificationId, duration) {
        const notificationElement = document.getElementById(notificationId);
        if (!notificationElement) return;

        let hideTimer = null;
        let remainingTime = duration;
        let startTime = Date.now();

        const startTimer = () => {
            startTime = Date.now();
            hideTimer = setTimeout(() => {
                this.hideNotification(notificationId);
            }, remainingTime);
        };

        const pauseTimer = () => {
            if (hideTimer) {
                clearTimeout(hideTimer);
                remainingTime -= (Date.now() - startTime);
                hideTimer = null;
            }
        };

        const resumeTimer = () => {
            if (remainingTime > 0) {
                startTimer();
            }
        };

        notificationElement.addEventListener('mouseenter', pauseTimer);
        notificationElement.addEventListener('mouseleave', resumeTimer);
        startTimer();
    }

    // 隐藏通知
    hideNotification(notificationId) {
        const notificationElement = document.getElementById(notificationId);
        if (notificationElement) {
            notificationElement.style.animation = 'slideOutRight 0.3s ease-in forwards';
            setTimeout(() => {
                notificationElement.remove();
                this.notificationCount--;
            }, 300);
        }
    }

    // 模态框方法
    modal(options) {
        const modalId = this.createModal({
            ...options,
            type: 'modal'
        });
        this.showModal(modalId, options);
    }

    confirm(message, callback) {
        const options = typeof message === 'string' 
            ? { message: message, callback: callback }
            : { ...message, type: 'confirm' };
        
        const modalId = this.createModal({
            ...options,
            type: 'confirm'
        });
        this.showModal(modalId, options);
    }

    alert(message, callback) {
        const options = typeof message === 'string'
            ? { message: message, callback: callback }
            : { ...message, type: 'alert' };
        
        const modalId = this.createModal({
            ...options,
            type: 'alert'
        });
        this.showModal(modalId, options);
    }

    // 处理通知参数（支持多种调用方式）
    parseNotificationArgs(args) {
        if (args.length === 0) {
            return { message: '', message_en: '' };
        }
        
        if (typeof args[0] === 'object' && args[0] !== null) {
            return args[0];
        }
        
        if (args.length === 1 && typeof args[0] === 'string') {
            return { message: args[0] };
        }
        
        if (args.length === 2 && typeof args[0] === 'string' && typeof args[1] === 'string') {
            return {
                message: args[0],
                message_en: args[1]
            };
        }
        
        return {
            message: args[0] || '',
            message_en: args[1] || ''
        };
    }

    // 通知方法
    success(...args) {
        const options = this.parseNotificationArgs(args);
        this.showNotification({ ...options, type: 'success' });
    }

    error(...args) {
        const options = this.parseNotificationArgs(args);
        this.showNotification({ ...options, type: 'error' });
    }

    warning(...args) {
        const options = this.parseNotificationArgs(args);
        this.showNotification({ ...options, type: 'warning' });
    }

    info(...args) {
        const options = this.parseNotificationArgs(args);
        this.showNotification({ ...options, type: 'info' });
    }

    notify(...args) {
        const options = this.parseNotificationArgs(args);
        this.showNotification({ ...options, type: 'notify' });
    }

    // message 接口，复用 notify 功能
    message(...args) {
        this.notify(...args);
    }
    
    // warn 接口，复用 warning 功能
    warn(...args) {
        this.warning(...args);
    }
}

// 创建全局实例
const alerty = new Alerty();

// 添加 CSS 样式（避免重复添加）
if (!document.getElementById('alerty-styles')) {
    const style = document.createElement('style');
    style.id = 'alerty-styles';
    style.textContent = `
        .alerty-bilingual {
            line-height: 1.4;
        }
        
        .alerty-primary {
            font-weight: 500;
            margin-bottom: 4px;
        }
        
        .alerty-secondary {
            font-size: 0.9em;
            opacity: 0.8;
            font-style: italic;
        }
        
        .alerty-timestamp {
            font-size: 0.65em;
            opacity: 0.5;
            margin-top: 3px;
            font-family: monospace;
        }
        
        .alerty-notification {
            animation: slideInRight 0.3s ease-out forwards;
            transform: translateX(100%);
            opacity: 0;
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);
}

// 导出到全局
window.alerty = alerty;

} // 结束重复声明检查