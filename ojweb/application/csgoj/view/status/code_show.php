<!-- 代码显示Modal -->
<div class="modal fade" id="code_show_modal" tabindex="-1" aria-labelledby="code_show_modal_label" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <div class="d-flex justify-content-between align-items-center w-100">
                    <div class="d-flex align-items-center">
                        <h5 class="modal-title mb-0 me-3" id="code_show_modal_label">
                            <i class="bi bi-code-slash me-2"></i>
                            <span id="code_show_modal_title">代码查看<span class="en-text">Code Viewer</span></span>
                        </h5>
                        
                        <div class="language-info d-flex align-items-center me-3">
                            <i class="bi bi-code-slash me-1 text-primary"></i>
                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1" id="code_language_badge" title="编程语言 (Programming Language)">Language</span>
                        </div>
                        
                        <!-- 紧凑的元信息显示 -->
                        <div class="code-meta-compact d-none d-lg-flex align-items-center text-muted small" id="code_meta_compact">
                            <span class="me-3" title="提交时间 (Submit Time)">
                                <i class="bi bi-clock me-1"></i>
                                <span id="meta_submit_time_compact">-</span>
                            </span>
                            <span class="me-3" title="代码长度 (Code Length)">
                                <i class="bi bi-file-text me-1"></i>
                                <span id="meta_code_length_compact">-</span>
                            </span>
                            <span class="me-3" title="题目编号 (Problem ID)">
                                <i class="bi bi-puzzle me-1"></i>
                                <span id="meta_problem_id_compact">-</span>
                            </span>
                            <span class="me-3" title="用户ID (User ID)">
                                <i class="bi bi-person me-1"></i>
                                <span id="meta_user_id_compact">-</span>
                            </span>
                            <span class="me-3" id="meta_result_compact_container" style="display: none;" title="评测结果 (Judge Result)">
                                <i class="bi bi-check-circle me-1"></i>
                                <span id="meta_result_compact">-</span>
                            </span>
                            <span class="me-3" id="meta_time_compact_container" style="display: none;" title="运行时间 (Runtime)">
                                <i class="bi bi-stopwatch me-1"></i>
                                <span id="meta_time_compact">-</span>
                            </span>
                            <span class="me-3" id="meta_memory_compact_container" style="display: none;" title="内存使用 (Memory Usage)">
                                <i class="bi bi-memory me-1"></i>
                                <span id="meta_memory_compact">-</span>
                            </span>
                        </div>
                    </div>
                    
                    <div class="code-actions d-flex align-items-center">
                        <!-- 移动端下拉菜单 -->
                        <div class="dropdown d-lg-none me-2">
                            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" id="metaDropdown" aria-expanded="false" title="详细信息 (Details)">
                                <i class="bi bi-info-circle"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="metaDropdown" id="meta_dropdown_menu">
                                <li><h6 class="dropdown-header">提交信息 (Submission Info)</h6></li>
                                <li><span class="dropdown-item-text" title="提交时间 (Submit Time)"><strong>提交时间:</strong> <span id="meta_submit_time_dropdown">-</span></span></li>
                                <li><span class="dropdown-item-text" title="代码长度 (Code Length)"><strong>代码长度:</strong> <span id="meta_code_length_dropdown">-</span></span></li>
                                <li><span class="dropdown-item-text" title="题目编号 (Problem ID)"><strong>题目:</strong> <span id="meta_problem_id_dropdown">-</span></span></li>
                                <li><span class="dropdown-item-text" title="用户ID (User ID)"><strong>用户:</strong> <span id="meta_user_id_dropdown">-</span></span></li>
                                <li id="meta_result_dropdown_container" style="display: none;"><span class="dropdown-item-text" title="评测结果 (Judge Result)"><strong>结果:</strong> <span id="meta_result_dropdown">-</span></span></li>
                                <li id="meta_time_dropdown_container" style="display: none;"><span class="dropdown-item-text" title="运行时间 (Runtime)"><strong>时间:</strong> <span id="meta_time_dropdown">-</span></span></li>
                                <li id="meta_memory_dropdown_container" style="display: none;"><span class="dropdown-item-text" title="内存使用 (Memory Usage)"><strong>内存:</strong> <span id="meta_memory_dropdown">-</span></span></li>
                            </ul>
                        </div>
                        
                        <button type="button" class="btn btn-outline-secondary btn-sm me-2" id="code_copy_btn" title="复制代码 (Copy Code)">
                            <i class="bi bi-clipboard"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="code_download_btn" title="下载代码 (Download Code)">
                            <i class="bi bi-download"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-body p-0" style="margin-bottom: 10px">
                <div class="code-viewer-container">
                    <div class="code-viewer-content" id="code_viewer_content">
                        <div class="text-center p-5 text-muted">
                            <i class="bi bi-code-slash fs-1"></i>
                            <p class="mt-2">点击语言按钮查看代码</p>
                            <p class="en-text">Click language button to view code</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 引入代码查看器样式 -->
{css href="__STATIC__/csgoj/code_show.css" /}

<!-- 引入代码查看器脚本 -->
{js href="__STATIC__/csgoj/code_show.js" /}
