/**
 * Overlay 加载遮罩 - Bootstrap 5 风格
 * 显示处理进度和状态信息
 * 
 * 支持两种调用方式：
 * 1. 字符串参数（向后兼容）：showOverlay("文本内容")
 * 2. 对象参数（新接口）：
 *    showOverlay({
 *      message: "中文文本",
 *      message_en: "English Text",  // 可选
 *      type: "text"  // 或 "html"，默认为 "text"
 *    })
 */

/**
 * 解析 overlay 参数，支持字符串和对象两种形式
 * @param {string|Object} param - 字符串文本或配置对象
 * @returns {Object} 标准化的配置对象 {message, message_en, type}
 */
function parseOverlayParam(param) {
    // 如果是字符串，向后兼容
    if (typeof param === 'string') {
        return {
            message: param,
            message_en: '',
            type: 'text'
        };
    }
    
    // 如果是对象
    if (typeof param === 'object' && param !== null) {
        return {
            message: param.message || '',
            message_en: param.message_en || '',
            type: param.type || 'text'
        };
    }
    
    // 默认值
    return {
        message: "扫描中... 发现 0 道题目, 0 组测试数据",
        message_en: '',
        type: 'text'
    };
}

/**
 * 格式化文本内容（支持中英双语和 HTML/text 模式）
 * @param {Object} config - 配置对象 {message, message_en, type}
 * @returns {string} 格式化后的 HTML 字符串
 */
function formatOverlayContent(config) {
    const { message, message_en, type } = config;
    
    // 处理换行符（仅在 text 模式下）
    let processedMessage = message || '';
    let processedMessageEn = message_en || '';
    
    if (type === 'text') {
        // text 模式：处理换行符并转义 HTML
        processedMessage = escapeHtml(processedMessage.replace(/\n/g, '<br>'));
        processedMessageEn = escapeHtml(processedMessageEn.replace(/\n/g, '<br>'));
    } else {
        // html 模式：直接使用，不转义
        // 处理换行符（如果传入的是纯文本换行）
        if (processedMessage && !processedMessage.includes('<')) {
            processedMessage = processedMessage.replace(/\n/g, '<br>');
        }
        if (processedMessageEn && !processedMessageEn.includes('<')) {
            processedMessageEn = processedMessageEn.replace(/\n/g, '<br>');
        }
    }
    
    // 格式化双语内容
    if (processedMessageEn && processedMessageEn.trim() !== '') {
        return `${processedMessage}<span class="en-text"> ${processedMessageEn}</span>`;
    }
    
    return processedMessage;
}

/**
 * HTML 转义函数（防止 XSS）
 * @param {string} text - 需要转义的文本
 * @returns {string} 转义后的文本
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showOverlay(initialText = "扫描中... 发现 0 道题目, 0 组测试数据") {
    // 如果已经存在 overlay，先移除旧的
    const existingOverlay = document.getElementById("overlay");
    if (existingOverlay) {
        existingOverlay.remove();
    }
    
    // 解析参数
    const config = parseOverlayParam(initialText);
    const formattedContent = formatOverlayContent(config);
    
    const overlay = document.createElement("div");
    overlay.id = "overlay";
    
    // 使用 Bootstrap 5 样式类
    overlay.className = "position-fixed top-0 start-0 w-100 h-100";
    overlay.style.backgroundColor = "rgba(0, 0, 0, 0.65)";
    overlay.style.zIndex = "10000";
    overlay.style.display = "flex";
    overlay.style.justifyContent = "center";
    overlay.style.alignItems = "center";
    overlay.style.backdropFilter = "blur(4px)";
    
    // 创建 Bootstrap 5 风格的卡片
    const card = document.createElement("div");
    card.className = "card shadow-lg border-0";
    card.style.minWidth = "320px";
    card.style.maxWidth = "500px";
    
    const cardBody = document.createElement("div");
    cardBody.className = "card-body text-center p-4";
    
    // 添加加载动画图标
    const spinner = document.createElement("div");
    spinner.className = "spinner-border text-primary mb-3";
    spinner.setAttribute("role", "status");
    spinner.style.width = "3rem";
    spinner.style.height = "3rem";
    
    const spinnerText = document.createElement("span");
    spinnerText.className = "visually-hidden";
    spinnerText.textContent = "加载中...";
    spinner.appendChild(spinnerText);
    
    // 文本容器
    const textContainer = document.createElement("div");
    textContainer.id = "overlay-text";
    textContainer.className = "text-dark";
    textContainer.style.fontSize = "1.1rem";
    textContainer.style.lineHeight = "1.6";
    textContainer.style.fontWeight = "500";
    
    // 根据 type 设置内容（text 使用 textContent，html 使用 innerHTML）
    if (config.type === 'html') {
        textContainer.innerHTML = formattedContent;
    } else {
        // 即使 type 是 text，我们也使用 innerHTML 以支持双语格式（包含 <span class="en-text">）
        textContainer.innerHTML = formattedContent;
    }
    
    // 进度条容器（可选）
    const progressContainer = document.createElement("div");
    progressContainer.id = "overlay-progress";
    progressContainer.className = "mt-3";
    progressContainer.style.display = "none";
    
    const progressBar = document.createElement("div");
    progressBar.className = "progress";
    progressBar.style.height = "8px";
    
    const progressBarInner = document.createElement("div");
    progressBarInner.className = "progress-bar progress-bar-striped progress-bar-animated";
    progressBarInner.setAttribute("role", "progressbar");
    progressBarInner.setAttribute("aria-valuenow", "0");
    progressBarInner.setAttribute("aria-valuemin", "0");
    progressBarInner.setAttribute("aria-valuemax", "100");
    progressBarInner.style.width = "0%";
    
    progressBar.appendChild(progressBarInner);
    progressContainer.appendChild(progressBar);
    
    // 组装元素
    cardBody.appendChild(spinner);
    cardBody.appendChild(textContainer);
    cardBody.appendChild(progressContainer);
    card.appendChild(cardBody);
    overlay.appendChild(card);
    
    document.body.appendChild(overlay);
    
    // 添加淡入动画
    overlay.style.opacity = "0";
    overlay.style.transition = "opacity 0.3s ease-in";
    setTimeout(() => {
        overlay.style.opacity = "1";
    }, 10);
}

/**
 * 更新 Overlay 内容
 * 
 * 支持多种调用方式：
 * 1. updateOverlay("文本内容", ratio) - 向后兼容
 * 2. updateOverlay({message: "...", message_en: "...", type: "html"}, ratio) - 新接口
 * 3. updateOverlay({message: "...", message_en: "..."}, ratio, "附加文本") - 支持 additionText（向后兼容）
 * 
 * @param {string|Object} initialText - 主文本或配置对象
 * @param {number|null} ratio - 进度条百分比 (0-100)
 * @param {string|null} additionText - 附加文本（向后兼容，可选）
 */
function updateOverlay(initialText = "", ratio = null, additionText = null) {
    const overlayText = document.getElementById("overlay-text");
    const progressContainer = document.getElementById("overlay-progress");
    const progressBar = progressContainer ? progressContainer.querySelector(".progress-bar") : null;
    
    if (overlayText) {
        // 解析参数
        const config = parseOverlayParam(initialText);
        
        // 如果有附加文本（向后兼容）
        if (additionText) {
            // 如果是对象形式，需要合并附加文本
            if (typeof initialText === 'object' && initialText !== null) {
                config.message = config.message ? `${config.message}<br/>${additionText}` : additionText;
            } else {
                // 字符串形式，直接拼接
                config.message = config.message ? `${config.message}<br/>${additionText}` : additionText;
            }
        }
        
        // 格式化内容
        const formattedContent = formatOverlayContent(config);
        overlayText.innerHTML = formattedContent || overlayText.innerHTML;
    }
    
    // 更新进度条
    if (ratio !== null && progressContainer && progressBar) {
        progressContainer.style.display = "block";
        progressBar.style.width = `${ratio}%`;
        progressBar.setAttribute("aria-valuenow", ratio);
        
        // 进度条颜色根据进度变化
        if (ratio < 30) {
            progressBar.className = "progress-bar progress-bar-striped progress-bar-animated bg-danger";
        } else if (ratio < 70) {
            progressBar.className = "progress-bar progress-bar-striped progress-bar-animated bg-warning";
        } else if (ratio < 100) {
            progressBar.className = "progress-bar progress-bar-striped progress-bar-animated bg-info";
        } else {
            progressBar.className = "progress-bar progress-bar-striped progress-bar-animated bg-success";
            progressBar.classList.remove("progress-bar-animated");
        }
    }
}

function hideOverlay() {
    const overlay = document.getElementById("overlay");
    if (overlay) {
        // 添加淡出动画
        overlay.style.transition = "opacity 0.3s ease-out";
        overlay.style.opacity = "0";
        
        setTimeout(() => {
            if (overlay.parentNode) {
                document.body.removeChild(overlay);
            }
        }, 300);
    }
}