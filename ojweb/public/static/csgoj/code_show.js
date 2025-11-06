/**
 * 代码查看器类
 * 提供代码显示、语法高亮、复制下载等功能
 */
class CodeViewer {
    constructor() {
        this.modal = new bootstrap.Modal(document.getElementById('code_show_modal'));
        this.contentContainer = document.getElementById('code_viewer_content');
        this.titleElement = document.getElementById('code_show_modal_title');
        this.languageBadge = document.getElementById('code_language_badge');
        // this.solutionIdElement = document.getElementById('code_solution_id'); // 已移除，不再需要
        this.copyBtn = document.getElementById('code_copy_btn');
        this.downloadBtn = document.getElementById('code_download_btn');
        
        // 紧凑元信息元素
        this.metaElements = {
            // 紧凑显示
            submitTimeCompact: document.getElementById('meta_submit_time_compact'),
            codeLengthCompact: document.getElementById('meta_code_length_compact'),
            problemIdCompact: document.getElementById('meta_problem_id_compact'),
            userIdCompact: document.getElementById('meta_user_id_compact'),
            resultCompact: document.getElementById('meta_result_compact'),
            timeCompact: document.getElementById('meta_time_compact'),
            memoryCompact: document.getElementById('meta_memory_compact'),
            resultCompactContainer: document.getElementById('meta_result_compact_container'),
            timeCompactContainer: document.getElementById('meta_time_compact_container'),
            memoryCompactContainer: document.getElementById('meta_memory_compact_container'),
            // 下拉菜单显示
            submitTimeDropdown: document.getElementById('meta_submit_time_dropdown'),
            codeLengthDropdown: document.getElementById('meta_code_length_dropdown'),
            problemIdDropdown: document.getElementById('meta_problem_id_dropdown'),
            userIdDropdown: document.getElementById('meta_user_id_dropdown'),
            resultDropdown: document.getElementById('meta_result_dropdown'),
            timeDropdown: document.getElementById('meta_time_dropdown'),
            memoryDropdown: document.getElementById('meta_memory_dropdown'),
            resultDropdownContainer: document.getElementById('meta_result_dropdown_container'),
            timeDropdownContainer: document.getElementById('meta_time_dropdown_container'),
            memoryDropdownContainer: document.getElementById('meta_memory_dropdown_container')
        };
        
        this.initEventListeners();
        this.initManualDropdown();
    }
    
    initEventListeners() {
        // 复制按钮事件
        this.copyBtn.addEventListener('click', () => {
            this.copyCode();
        });
        
        // 下载按钮事件
        this.downloadBtn.addEventListener('click', () => {
            this.downloadCode();
        });
    }
    
    
    // 手动处理下拉菜单点击事件
    initManualDropdown() {
        const dropdownButton = document.getElementById('metaDropdown');
        const dropdownMenu = document.getElementById('meta_dropdown_menu');
        
        if (dropdownButton && dropdownMenu && !dropdownButton.hasAttribute('data-manual-initialized')) {
            // 标记已初始化，避免重复绑定
            dropdownButton.setAttribute('data-manual-initialized', 'true');
            
            // 移除Bootstrap的自动初始化属性，防止冲突
            dropdownButton.removeAttribute('data-bs-toggle');
            
            dropdownButton.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                
                // 切换显示状态
                if (dropdownMenu.classList.contains('show')) {
                    dropdownMenu.classList.remove('show');
                    dropdownButton.setAttribute('aria-expanded', 'false');
                } else {
                    dropdownMenu.classList.add('show');
                    dropdownButton.setAttribute('aria-expanded', 'true');
                }
            });
            
            // 点击外部关闭下拉菜单
            document.addEventListener('click', (e) => {
                if (!dropdownButton.contains(e.target) && !dropdownMenu.contains(e.target)) {
                    dropdownMenu.classList.remove('show');
                    dropdownButton.setAttribute('aria-expanded', 'false');
                }
            });
        }
    }
    
    // 显示代码
    showCode(solutionId, language, codeData) {
        // 设置标题
        this.titleElement.innerHTML = `提交 <span class="text-primary fw-bold">#${solutionId}</span> 的代码<span class="en-text">Code for Submission <span class="text-primary fw-bold">#${solutionId}</span></span>`;
        
        // 设置语言标识
        const languageMap = {
            'C': 'c',
            'C++': 'cpp',
            'Java': 'java',
            'Python': 'python',
            'Python3': 'python',
            'JavaScript': 'javascript',
            'Go': 'go',
            'Rust': 'rust',
            'PHP': 'php',
            'Ruby': 'ruby'
        };
        
        const hljsLanguage = languageMap[language] || '';
        this.languageBadge.textContent = language;
        this.languageBadge.className = `badge bg-primary language-badge`;
        
        // 设置紧凑元信息
        this.metaElements.submitTimeCompact.textContent = codeData.submitTime || '-';
        this.metaElements.codeLengthCompact.textContent = codeData.codeLength ? `${codeData.codeLength}字符` : '-';
        this.metaElements.problemIdCompact.textContent = codeData.problemId || '-';
        this.metaElements.userIdCompact.textContent = codeData.user_id || '-';
        
        // 设置下拉菜单元信息
        this.metaElements.submitTimeDropdown.textContent = codeData.submitTime || '-';
        this.metaElements.codeLengthDropdown.textContent = codeData.codeLength ? `${codeData.codeLength} 字符` : '-';
        this.metaElements.problemIdDropdown.textContent = codeData.problemId || '-';
        this.metaElements.userIdDropdown.textContent = codeData.user_id || '-';
        
        // 显示结果信息（如果存在）
        if (codeData.result) {
            this.metaElements.resultCompact.textContent = codeData.result;
            this.metaElements.resultCompactContainer.style.display = 'inline';
            this.metaElements.resultDropdown.textContent = codeData.result;
            this.metaElements.resultDropdownContainer.style.display = 'block';
        } else {
            this.metaElements.resultCompactContainer.style.display = 'none';
            this.metaElements.resultDropdownContainer.style.display = 'none';
        }
        
        // 显示时间和内存信息（如果存在）
        if (codeData.time && codeData.memory) {
            this.metaElements.timeCompact.textContent = `${codeData.time}ms`;
            this.metaElements.memoryCompact.textContent = `${codeData.memory}KB`;
            this.metaElements.timeCompactContainer.style.display = 'inline';
            this.metaElements.memoryCompactContainer.style.display = 'inline';
            
            this.metaElements.timeDropdown.textContent = `${codeData.time}ms`;
            this.metaElements.memoryDropdown.textContent = `${codeData.memory}KB`;
            this.metaElements.timeDropdownContainer.style.display = 'block';
            this.metaElements.memoryDropdownContainer.style.display = 'block';
        } else {
            this.metaElements.timeCompactContainer.style.display = 'none';
            this.metaElements.memoryCompactContainer.style.display = 'none';
            this.metaElements.timeDropdownContainer.style.display = 'none';
            this.metaElements.memoryDropdownContainer.style.display = 'none';
        }
        
        // 渲染代码
        this.renderCode(codeData.source, hljsLanguage, codeData);
        
        // 显示modal
        this.modal.show();
    }
    
    // 渲染代码
    renderCode(source, language, codeData) {
        // 生成代码头部注释
        const headerComment = this.generateCodeHeader(codeData, language);
        const fullCode = headerComment + source;
        
        // 创建行号
        const lines = fullCode.split('\n');
        const lineNumbers = lines.map((_, index) => index + 1).join('\n');
        
        // 创建HTML结构
        const html = `
            <div class="code-with-lines">
                <div class="code-line-numbers">${lineNumbers}</div>
                <div class="code-content">
                    <pre><code class="language-${language}">${fullCode}</code></pre>
                </div>
            </div>
        `;
        
        this.contentContainer.innerHTML = html;
        
        // 应用语法高亮
        const codeElement = this.contentContainer.querySelector('code');
        if (codeElement) {
            // 先移除可能存在的旧高亮类
            codeElement.className = codeElement.className.replace(/hljs-\w+/g, '');
            // 应用新的高亮
            hljs.highlightElement(codeElement);
        }
    }
    
    // 生成代码头部注释
    generateCodeHeader(codeData, language) {
        const info = {
            solution_id: codeData.solution_id || 'Unknown',
            problem: codeData.problemId || 'Unknown',
            user: codeData.user_id || 'Unknown',
            language: this.languageBadge.textContent || 'Unknown',
            result: codeData.result || 'Unknown',
            time: codeData.time ? `${codeData.time}ms` : null,
            memory: codeData.memory ? `${codeData.memory}KB` : null,
            submitTime: codeData.submitTime || 'Unknown',
            codeLength: codeData.codeLength ? `${codeData.codeLength} chars` : 'Unknown'
        };
        
        const headerContent = this.generateCodeHeaderContent(info);
        // Python 使用 # 注释
        if (language === 'python') {
            return this.generatePythonHeader(headerContent);
        }
        
        // 其他语言都使用 /* */ 注释
        return this.generateStandardHeader(headerContent);
    }
    
    // 标准注释风格 (/* */) - 适用于 C/C++, Java, JavaScript, Go, Rust, PHP 等
    generateCodeHeaderContent(info) {
        let header = "";
        
        header += `\tSolution ID: ${info.solution_id}\n`;
        header += `\tProblem: ${info.problem}\n`;
        header += `\tUser: ${info.user}\n`;
        header += `\tLanguage: ${info.language}\n`;
        header += `\tResult: ${info.result}\n`;
        if (info.time && info.memory) {
            header += `\tTime: ${info.time}\n`;
            header += `\tMemory: ${info.memory}\n`;
        }
        header += `\tSubmit Time: ${info.submitTime}\n`;
        header += `\tCode Length: ${info.codeLength}\n`;
        if(info.contest_id) {
            header += `\tContest: ${info.contest_id}\n`;
        }
        return header;

    }
    generateStandardHeader(content) {
        let header = "/**********************************************************************\n";
        header += content;
        header += "**********************************************************************/\n\n";
        return header;
    }
    
    // Python 风格注释 (#)
    generatePythonHeader(content) {
        let header = `"""#######################################################################\n`;
        header += content;
        header += `#######################################################################"""\n\n`;
        return header;
    }
    
    // HTML转义
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // 复制代码
    async copyCode() {
        const codeElement = this.contentContainer.querySelector('code');
        if (!codeElement) return;
        
        // 获取要复制的文本内容
        const textToCopy = codeElement.textContent || codeElement.innerText;
        
        if (!textToCopy || textToCopy.trim() === '') {
            console.error('无法获取要复制的文本内容');
            return;
        }
        
        // 使用全局 global.js 的 ClipboardWrite 函数
        const success = await ClipboardWrite(textToCopy);
        
        if (success) {
            // 更新按钮状态
            const originalIcon = this.copyBtn.innerHTML;
            this.copyBtn.innerHTML = '<i class="bi bi-check"></i>';
            this.copyBtn.classList.add('btn-copied');
            
            // x秒后恢复
            setTimeout(() => {
                this.copyBtn.innerHTML = originalIcon;
                this.copyBtn.classList.remove('btn-copied');
            }, 500);
        } else {
            console.error('复制失败');
            alerty.error('复制失败，请手动选择代码复制', 'Copy failed, please manually select and copy the code');

        }
    }
    
    // 下载代码
    downloadCode() {
        const codeElement = this.contentContainer.querySelector('code');
        if (!codeElement) return;
        
        const language = this.languageBadge.textContent.toLowerCase();
        const extension = this.getFileExtension(language);
        
        // 从当前显示的元信息中获取数据
        const problemId = this.metaElements.problemIdCompact.textContent || 'Unknown';
        const user_id = this.metaElements.userIdCompact.textContent || 'Unknown';
        const result = this.metaElements.resultCompact.textContent || 'Unknown';
        
        // 从标题中提取提交号
        const titleText = this.titleElement.textContent;
        const solutionIdMatch = titleText.match(/#(\d+)/);
        const solutionId = solutionIdMatch ? solutionIdMatch[1] : 'Unknown';
        
        // 生成14位时间戳 (YYYYMMDDHHMMSS)
        const now = new Date();
        const timestamp = now.getFullYear().toString() +
                         (now.getMonth() + 1).toString().padStart(2, '0') +
                         now.getDate().toString().padStart(2, '0') +
                         now.getHours().toString().padStart(2, '0') +
                         now.getMinutes().toString().padStart(2, '0') +
                         now.getSeconds().toString().padStart(2, '0');
        
        // 生成文件名：提交号_用户名_题目编号_评测结果_时间戳.扩展名
        const filename = `sol${solutionId}_${user_id}_pro${problemId}_${result}_${timestamp}.${extension}`;
        
        const blob = new Blob([codeElement.textContent], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }
    
    // 获取文件扩展名
    getFileExtension(language) {
        const extensions = {
            'c': 'c',
            'c++': 'cpp',
            'java': 'java',
            'python': 'py',
            'python3': 'py',
            'javascript': 'js',
            'go': 'go',
            'rust': 'rs',
            'php': 'php',
            'ruby': 'rb'
        };
        return extensions[language] || 'txt';
    }
}

// 全局代码查看器实例
window.codeViewer = new CodeViewer();
