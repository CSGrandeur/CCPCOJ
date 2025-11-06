<!-- 气球颜色选择Modal -->
<div class="modal fade" id="balloonColorModal" tabindex="-1" aria-labelledby="balloonColorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="balloonColorModalLabel">
                    编辑气球颜色 <span class="en-text">Edit Balloon Color</span>
                </h5>
                <div class="problem-info ms-3">
                    <span class="problem-letter" id="modalProblemLetter">A</span>
                    <span class="problem-id" id="modalProblemId">1001</span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <!-- 颜色选择器 -->
                    <div class="col-md-6">
                        <label class="form-label">颜色选择器</label>
                        <input type="color" class="form-control form-control-color" id="colorPicker" value="#FF6B6B">
                    </div>
                    
                    <!-- 颜色输入框 -->
                    <div class="col-md-6">
                        <label class="form-label">颜色值输入</label>
                        <input type="text" class="form-control" id="colorInput" placeholder="#FF6B6B 或 red">
                        <div class="form-text">支持十六进制(#FF6B6B)或颜色名称(red,blue)</div>
                    </div>
                </div>
                
                <!-- 预设颜色 -->
                <div class="mt-3">
                    <label class="form-label">预设颜色</label>
                    <div class="preset-colors d-flex flex-wrap gap-2" id="presetColors">
                        <!-- 动态生成预设颜色按钮 -->
                    </div>
                </div>
                
                <!-- 颜色预览对比 -->
                <div class="mt-3">
                    <label class="form-label">颜色预览对比</label>
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="color-comparison-item">
                                <div class="form-label small text-muted">原颜色</div>
                                <div class="color-preview d-flex align-items-center gap-2">
                                    <div class="color-preview-circle" id="originalColorCircle"></div>
                                    <span id="originalColorText">#FF6B6B</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="color-comparison-item">
                                <div class="form-label small text-muted">新颜色</div>
                                <div class="color-preview d-flex align-items-center gap-2">
                                    <div class="color-preview-circle" id="colorPreviewCircle"></div>
                                    <span id="colorPreviewText">#FF6B6B</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                <button type="button" class="btn btn-primary" id="applyColorBtn">应用颜色</button>
            </div>
        </div>
    </div>
</div>

<!-- 气球颜色设置 -->
<div class="form-group mb-3">
    <label class="bilingual-label">气球颜色设置：<span class="en-text">Balloon Colors</span></label>
    <div class="balloon-colors-container">
        <!-- 颜色预览网格 -->
        <div id="balloon_color_preview" class="balloon-preview-grid"></div>
        
        <!-- 颜色输入区域 -->
        <div class="input-group">
            <input type="text" 
                   class="form-control" 
                   id="balloon_colors" 
                   name="balloon_colors"
                   placeholder="输入颜色（如：#FF6B6B,#4ECDC4,#45B7D1）或点击上方颜色块编辑..."
                   {if $edit_mode}value="{$balloon_colors}"{/if}>
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-outline-primary" id="randomizeColorsBtn" 
                        title="重新随机颜色 / Randomize Colors">
                    <i class="bi bi-shuffle"></i>
                </button>
                <button type="button" class="btn btn-outline-secondary" id="clearColorsBtn" 
                        title="清空颜色 / Clear Colors">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        </div>
        
        <div class="form-text">
            <span class="bilingual-inline">
                <span>点击颜色块编辑，或直接输入颜色值（逗号分隔）</span>
                <span class="en-text">Click color blocks to edit, or input color values (comma-separated)</span>
            </span>
        </div>
    </div>
</div>

