/**
 * 运行信息查看器类
 * 提供运行结果详情展示功能
 */
class RuninfoViewer {
    constructor() {
        this.modal = new bootstrap.Modal(document.getElementById('runinfo_show_modal'));
        this.contentContainer = document.getElementById('runinfo_viewer_content');
        this.titleElement = document.getElementById('runinfo_show_modal_title');
        this.solutionIdBadge = document.getElementById('runinfo_solution_id');
    }

    /**
     * 显示运行信息
     */
    showInfo(solutionId, resultData) {
        // 更新标题和提交ID
        this.solutionIdBadge.textContent = `#${solutionId}`;
        
        if (!resultData || !resultData.data) {
            this.showError('没有详细信息<span class="en-text">No detailed information available</span>');
            return;
        }

        const payload = resultData.data;
        const dataType = (payload.data_type || '').toString().toLowerCase();

        // 文本型：直接按代码块原样显示
        if (dataType === 'txt' || dataType === 'text') {
            this.renderText(payload.data || '');
            this.modal.show();
            return;
        }

        // 结构化JSON：按原有结构化视图渲染
        if (dataType === 'json') {
            const structured = (payload.data && (payload.data.error_summary || payload.data)) || null;
            if (!structured) {
                this.showError('没有错误信息<span class="en-text">No error information available</span>');
                return;
            }
            this.renderContent(structured);
            this.modal.show();
            return;
        }

        // 未标注类型的后向兼容处理
        if (typeof payload.data === 'string') {
            this.renderText(payload.data);
            this.modal.show();
            return;
        }

        const fallback = (payload.data && (payload.data.error_summary || payload.data)) || null;
        if (fallback) {
            this.renderContent(fallback);
            this.modal.show();
        } else {
            this.showError('没有错误信息<span class="en-text">No error information available</span>');
        }
    }

    /**
     * 渲染运行信息内容
     */
    renderContent(data) {
        // 构建结果视图
        let html = `
                <div class="failed-cases-summary">
                    <div class="title">
                        未通过用例数
                        <span class="en-text">Failed Test Cases</span>
                    </div>
                    <div class="count">${data.total_failed_cases}</div>
                </div>`;
        
        if (data.failed_cases && data.failed_cases.length > 0) {
            html += `
                <div class="table-container">
                    <table class="table table-hover">
                    <thead>
                        <tr>
                                <th>测试点<span class="en-text">Test Case</span></th>
                                <th>结果<span class="en-text">Result</span></th>
                                <th>详细信息<span class="en-text">Details</span></th>
                        </tr>
                    </thead>
                    <tbody>`;
            
            data.failed_cases.forEach((failedCase, index) => {
                // 获取错误详情字段（judge_info 或 runtime_error）
                const errorDetail = failedCase.judge_info || failedCase.runtime_error || '';
                const hasErrorDetail = errorDetail.length > 0;
                
                // 生成唯一ID用于按钮绑定
                const detailId = `error_detail_${index}`;
                
                // 详细信息单元格：显示消息，如果有错误详情则整个单元格可点击
                let detailsCellContent = this.escapeHtml(failedCase.message || '');
                let detailsCellClass = 'text-break';
                let detailsCellAttr = '';
                
                if (hasErrorDetail) {
                    detailsCellClass += ' error-detail-cell-clickable';
                    detailsCellAttr = ` data-detail-id="${detailId}" data-error-detail="${this.escapeHtml(errorDetail)}" title="点击查看错误详情 / Click to view error details"`;
                    detailsCellContent += ` <i class="bi bi-info-circle text-primary ms-2" style="font-size: 1rem;"></i>`;
                }
                
                html += `
                        <tr>
                            <td class="font-monospace">${failedCase.test_case}</td>
                            <td><span class="badge bg-danger">${failedCase.judge_result}</span></td>
                            <td class="${detailsCellClass}"${detailsCellAttr}>${detailsCellContent}</td>
                        </tr>`;
            });
            
            html += `
                    </tbody>
                </table>
            </div>`;
        }

        this.contentContainer.innerHTML = html;
        
        // 绑定错误详情按钮事件
        this.bindErrorDetailButtons();
    }

    /**
     * HTML转义
     */
    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * 绑定错误详情单元格点击事件
     */
    bindErrorDetailButtons() {
        const detailCells = this.contentContainer.querySelectorAll('.error-detail-cell-clickable');
        detailCells.forEach(cell => {
            cell.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                
                const errorDetail = cell.getAttribute('data-error-detail');
                if (!errorDetail || !window.alerty) return;
                
                // 使用 alerty 弹窗显示错误详情
                window.alerty.modal({
                    title: '错误详情<span class="en-text">Error Details</span>',
                    message: `<pre style="white-space: pre-wrap; word-wrap: break-word; font-family: 'Fira Code', 'Consolas', 'Monaco', 'Courier New', monospace; font-size: 0.875rem; background: #f8f9fa; padding: 1rem; border-radius: 0.375rem; margin: 0;">${this.escapeHtml(errorDetail)}</pre>`,
                    width: 'xl'
                });
            });
        });
    }

    /**
     * 文本结果渲染（代码块）
     */
    renderText(text) {
        const escaped = (text ?? '').toString()
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
        // 不用class标识语言，让hljs自动识别。无论标 language-plaintext 还是 language-txt，都会报 warning，反正hljs不认识。
        this.contentContainer.innerHTML = `
            <div class="p-3">
                <pre class="mb-0"><code>${escaped}</code></pre>
            </div>`;
        // 应用 highlight.js 高亮（纯文本统一样式）
        try {
            if (window.hljs) {
                const codeElement = this.contentContainer.querySelector('code');
                if (codeElement) {
                    hljs.highlightElement(codeElement);
                }
            }
        } catch (e) {
            // 忽略高亮异常
        }
    }

    /**
     * 显示错误信息
     */
    showError(message) {
        this.contentContainer.innerHTML = `
            <div class="alert alert-info m-3" role="alert">
                <i class="bi bi-info-circle me-2"></i>
                ${message}
            </div>`;
        this.modal.show();
    }
}

// 全局运行信息查看器实例
window.runinfoViewer = new RuninfoViewer();
