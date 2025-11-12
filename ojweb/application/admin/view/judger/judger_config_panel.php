<!-- 评测机限制配置模态框 -->
<div class="modal fade" id="judgerConfigModal" tabindex="-1" aria-labelledby="judgerConfigModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="judgerConfigModalLabel">
                    评测限制配置 - <span id="config_display_id"></span><span class="en-text">Judger Limit Configuration</span>
                </h5>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-info btn-sm" id="export_judger_config" title="下载配置 / Export Config">
                        <i class="bi bi-download"></i>
                    </button>
                    <button type="button" class="btn btn-outline-warning btn-sm" id="import_judger_config" title="上传配置 / Import Config">
                        <i class="bi bi-upload"></i>
                    </button>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body">
                <form id="judgerConfigForm" autocomplete="off">
                    <input type="hidden" id="config_user_id" name="user_id">
                    
                    <!-- 隐藏的文件输入框用于上传 -->
                    <input type="file" id="import_judger_file_input" accept=".json" style="display: none;">
                    
                    <!-- 题目限制设置 -->
                    <div class="mb-3">
                        <div id="judger_problem_input"></div>
                    </div>
                    
                    <!-- 黑白名单设置 -->
                    <div class="mb-3">
                        <div class="d-flex align-items-center mb-2">
                            <label class="form-label mb-0 me-3">限制模式<span class="en-text text-muted d-block">Limit Mode</span></label>
                            <div class="csg-switch">
                                <input type="checkbox" class="csg-switch-input" 
                                       data-csg-text-on="白名单" data-csg-text-on-en="Whitelist"
                                       data-csg-text-off="黑名单" data-csg-text-off-en="Blacklist"
                                       name="flg_white" id="config_flg_white">
                            </div>
                        </div>
                        <div class="form-text">
                            <div class="label-text bilingual-inline">黑名单：禁止评测指定题目<span class="en-text">Blacklist: Forbid judging specified problems</span></div>
                            <div class="label-text bilingual-inline">白名单：只允许评测指定题目<span class="en-text">Whitelist: Only allow judging specified problems</span></div>
                        </div>
                    </div>
                    
                    <!-- 启用状态设置 -->
                    <div class="mb-3">
                        <div class="d-flex align-items-center mb-2">
                            <label class="form-label mb-0 me-3">启用状态<span class="en-text text-muted d-block">Enable Status</span></label>
                            <div class="csg-switch">
                                <input type="checkbox" class="csg-switch-input" 
                                       data-csg-text-on="启用" data-csg-text-on-en="Enabled"
                                       data-csg-text-off="关闭" data-csg-text-off-en="Disabled"
                                       name="defunct" id="config_defunct">
                            </div>
                        </div>
                        <div class="form-text">
                            <div class="label-text bilingual-inline">关闭状态的评测机不会参与评测<span class="en-text">Disabled judgers won't participate in judging</span></div>
                        </div>
                    </div>
                    
                    <!-- 语言限制设置 -->
                    <div class="mb-3">
                        <label class="form-label">语言限制<span class="en-text text-muted d-block">Language Limit</span></label>
                        <div class="row g-2" id="config_language_list">
                            <!-- 语言选项将由JavaScript动态生成 -->
                        </div>
                        <div class="form-text">
                            <div class="label-text bilingual-inline">选择该评测机支持的语言<span class="en-text">Select languages supported by this judger</span></div>
                        </div>
                    </div>
                    
                    <!-- 密码修改设置 -->
                    <div class="mb-3">
                        <label for="config_password" class="form-label">
                            修改密码 <span class="en-text text-muted d-block">Change Password</span>
                        </label>
                        <input type="text" class="form-control" id="config_password" name="config_password_field" 
                               placeholder="新密码 (6-30位数字字母)" maxlength="30" 
                               autocomplete="off" data-form-type="other" data-lpignore="true">
                        <div class="form-text">
                            <div class="label-text bilingual-inline">留空表示不修改密码，6-30位数字字母<span class="en-text">Leave empty to keep current password, 6-30 alphanumeric characters</span></div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <span class="label-text bilingual-inline">取消<span class="en-text">Cancel</span></span>
                </button>
                <button type="button" class="btn btn-primary" id="saveJudgerConfig">
                    <span class="label-text bilingual-inline">保存配置<span class="en-text">Save Configuration</span></span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- 引入题目选择器 -->
{include file="contest/problem_selection" /}

<!-- 引入 CSG Switch 组件 -->
{include file="../../csgoj/view/public/base_csg_switch" /}

<script>
// 初始化评测机配置页面的题号输入组件
document.addEventListener('DOMContentLoaded', function() {
    // 创建题号输入组件
    const problemInput = createProblemInput('judger_problem_input', {
        max: 25,
        allowDuplicates: false,
        allowInvalid: false,
        showCount: true,
        showActions: true,
        onChange: function(csv, component) {
            // 可以在这里添加额外的变化处理逻辑
        }
    });
    
    // 设置题目选择器确认回调
    window.onProblemSelectionConfirm = function(problemIds) {
        problemInput.addProblems(problemIds);
    };
    
    // 如果有初始值，设置到组件中
    const initialValue = document.getElementById('config_pro_list')?.value || '';
    if (initialValue) {
        problemInput.setValue(initialValue);
    }
    
    // 初始化数量显示
    if (typeof updateProblemCount === 'function') {
        updateProblemCount();
    }
});
</script>
