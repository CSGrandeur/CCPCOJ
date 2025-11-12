<!-- 运行信息显示Modal -->
<div class="modal fade" id="runinfo_show_modal" tabindex="-1" aria-labelledby="runinfo_show_modal_label" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <div class="d-flex justify-content-between align-items-center w-100">
                    <div class="d-flex align-items-center">
                        <h5 class="modal-title mb-0 me-3" id="runinfo_show_modal_label">
                            <i class="bi bi-info-circle me-2"></i>
                            <span id="runinfo_show_modal_title">运行详情<span class="en-text">Code Run Details</span></span>
                        </h5>
                        
                        <div class="runinfo-meta d-flex align-items-center">
                            <span class="text-primary fw-bold" id="runinfo_solution_id" title="提交编号 (Submission ID)">-</span>
                        </div>
                    </div>

                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body p-0">
                <div class="runinfo-viewer-container">
                    <div class="runinfo-viewer-content" id="runinfo_viewer_content">
                        <div class="text-center p-5 text-muted">
                            <i class="bi bi-info-circle fs-1"></i>
                            <p class="mt-2">正在加载运行结果<span class="en-text">Loading run results</span></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 引入运行信息查看器样式 -->
{css href="__STATIC__/csgoj/runinfo_show.css" /}

<!-- 引入运行信息查看器脚本 -->
{js href="__STATIC__/csgoj/runinfo_show.js" /}
