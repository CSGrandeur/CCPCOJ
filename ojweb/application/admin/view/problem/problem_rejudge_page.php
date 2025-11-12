{include file="../../csgoj/view/public/base_csg_switch" /}

<div class="admin-page-header">
    <div class="admin-page-header-left">
        <div class="admin-page-header-icon">
            <i class="bi bi-arrow-clockwise"></i>
        </div>
        <h1 class="admin-page-header-title">
            <div class="admin-page-header-title-main">
                {if isset($rejudge_type) && $rejudge_type == 'contest'}
                    比赛重判
                {else/}
                    题目重判
                {/if}
            </div>
            {if isset($rejudge_type) && $rejudge_type == 'contest'}
            <div class="admin-page-header-title-right">
                <a href="__OJ__/contest/contest?cid={$cid}" class="admin-page-header-id">
                    <i class="bi bi-hash"></i> {$cid}
                </a>
                <span class="en-text">Contest Rejudge</span>
            </div>
            {else/}
            <div class="admin-page-header-title-right">
                <span class="en-text">Problem Rejudge</span>
            </div>
            {/if}
        </h1>
    </div>
</div>

<div class="container">
    {if !isset($rejudge_type) || $rejudge_type != 'contest'}
    <div class="alert alert-info d-flex align-items-start" role="alert">
        <i class="bi bi-info-circle me-2 mt-1"></i>
        <div>
            <p class="mb-1">此处重判将忽略比赛中的提交. 如需重判比赛中的提交，请在具体比赛的控制台中进行操作.</p>
            <p class="mb-0">Solutions in contests will be <strong>ignored</strong>. If you want to rejudge problems in a contest, use contest rejudge.</p>
        </div>
    </div>
    {else/}
    <div class="alert alert-warning d-flex align-items-start" role="alert">
        <i class="bi bi-exclamation-triangle me-2 mt-1"></i>
        <div>
            <p class="mb-1">请谨慎操作重判功能，重判会影响比赛结果</p>
            <p class="mb-1">Please use rejudge function carefully, it will affect contest results</p>
            <p class="mb-1">整场比赛重判是高风险操作，仅支持通过输入所有题目ID实现此功能。</p>
            <p class="mb-0">Full contest rejudge is a high-risk operation, only supported by entering all problem IDs.</p>
        </div>
    </div>
    {/if}

    <form id="problem_rejudge_form" method='post' action="{$submit_url}">
        <div class="row g-4">
            <!-- 结果类型选择区域 -->
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header bg-light">
                        <h4 class="mb-0">重判结果类型<span class="en-text text-muted fs-6">Rejudge Result Types</span></h4>
                    </div>
                    <div class="card-body">
                        <!-- 快速选择按钮 -->
                        <div class="d-grid gap-2 mb-3">
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-outline-danger btn-sm" id="check_res_all" title="全选">
                                    <span class="cn-text"><i class="bi bi-check-all"></i> 全选</span><span class="en-text">All</span>
                                </button>
                                <button type="button" class="btn btn-outline-success btn-sm" id="check_res_non" title="清空">
                                    <span class="cn-text"><i class="bi bi-x-square"></i> 清空</span><span class="en-text">None</span>
                                </button>
                                <button type="button" class="btn btn-outline-warning btn-sm" id="check_res_rev" title="反选">
                                    <span class="cn-text"><i class="bi bi-arrow-repeat"></i> 反选</span><span class="en-text">Reverse</span>
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-sm" id="check_res_dft" title="默认">
                                    <span class="cn-text"><i class="bi bi-arrow-clockwise"></i> 默认</span><span class="en-text">Default</span>
                                </button>
                            </div>
                        </div>

                        <!-- 结果类型复选框 - 双列布局 -->
                        <div class="row g-2">
                            <div class="col-6">
                                <!-- 左侧：主要错误类型 -->
                                <div class="form-check form-check-sm">
                                    <input class="form-check-input rejudge_res_check" type="checkbox" value="4" id="rejudge_res_check_ac" name="rejudge_res_check[]">
                                    <label class="form-check-label text-success fw-bold" for="rejudge_res_check_ac">
                                        通过<span class="en-text text-muted">Accepted (AC)</span>
                                    </label>
                                </div>
                                <div class="form-check form-check-sm">
                                    <input class="form-check-input rejudge_res_check" type="checkbox" value="5" id="rejudge_res_check_pe" name="rejudge_res_check[]" checked>
                                    <label class="form-check-label text-danger" for="rejudge_res_check_pe">
                                        格式错误<span class="en-text text-muted">Presentation Error (PE)</span>
                                    </label>
                                </div>
                                <div class="form-check form-check-sm">
                                    <input class="form-check-input rejudge_res_check" type="checkbox" value="6" id="rejudge_res_check_wa" name="rejudge_res_check[]" checked>
                                    <label class="form-check-label text-danger" for="rejudge_res_check_wa">
                                        答案错误<span class="en-text text-muted">Wrong Answer (WA)</span>
                                    </label>
                                </div>
                                <div class="form-check form-check-sm">
                                    <input class="form-check-input rejudge_res_check" type="checkbox" value="7" id="rejudge_res_check_tle" name="rejudge_res_check[]" checked>
                                    <label class="form-check-label text-warning" for="rejudge_res_check_tle">
                                        超时<span class="en-text text-muted">Time Limit Exceeded (TLE)</span>
                                    </label>
                                </div>
                                <div class="form-check form-check-sm">
                                    <input class="form-check-input rejudge_res_check" type="checkbox" value="8" id="rejudge_res_check_mle" name="rejudge_res_check[]" checked>
                                    <label class="form-check-label text-warning" for="rejudge_res_check_mle">
                                        超内存<span class="en-text text-muted">Memory Limit Exceeded (MLE)</span>
                                    </label>
                                </div>
                                <div class="form-check form-check-sm">
                                    <input class="form-check-input rejudge_res_check" type="checkbox" value="9" id="rejudge_res_check_ole" name="rejudge_res_check[]" checked>
                                    <label class="form-check-label text-warning" for="rejudge_res_check_ole">
                                        输出超限<span class="en-text text-muted">Output Limit Exceeded (OLE)</span>
                                    </label>
                                </div>
                            </div>
                            <div class="col-6">
                                <!-- 右侧：其他类型 -->
                                <div class="form-check form-check-sm">
                                    <input class="form-check-input rejudge_res_check" type="checkbox" value="10" id="rejudge_res_check_re" name="rejudge_res_check[]" checked>
                                    <label class="form-check-label text-warning" for="rejudge_res_check_re">
                                        运行错误<span class="en-text text-muted">Runtime Error (RE)</span>
                                    </label>
                                </div>
                                <div class="form-check form-check-sm">
                                    <input class="form-check-input rejudge_res_check" type="checkbox" value="11" id="rejudge_res_check_ce" name="rejudge_res_check[]" checked>
                                    <label class="form-check-label text-info" for="rejudge_res_check_ce">
                                        编译错误<span class="en-text text-muted">Compilation Error (CE)</span>
                                    </label>
                                </div>
                                <div class="form-check form-check-sm">
                                    <input class="form-check-input rejudge_res_check" type="checkbox" value="90" id="rejudge_res_check_jf" name="rejudge_res_check[]" checked>
                                    <label class="form-check-label text-info" for="rejudge_res_check_jf">
                                        评测失败<span class="en-text text-muted">Judge Failed (JF)</span>
                                    </label>
                                </div>
                                <div class="form-check form-check-sm">
                                    <input class="form-check-input rejudge_res_check" type="checkbox" value="100" id="rejudge_res_check_un" name="rejudge_res_check[]" checked>
                                    <label class="form-check-label text-secondary" for="rejudge_res_check_un">
                                        未知状态<span class="en-text text-muted">Unknown (UN)</span>
                                    </label>
                                </div>
                                <div class="form-check form-check-sm">
                                    <input class="form-check-input rejudge_res_check" type="checkbox" value="2" id="rejudge_res_check_ci" name="rejudge_res_check[]">
                                    <label class="form-check-label text-secondary" for="rejudge_res_check_ci">
                                        编译中<span class="en-text text-muted">Compiling (CI)</span>
                                    </label>
                                </div>
                                <div class="form-check form-check-sm">
                                    <input class="form-check-input rejudge_res_check" type="checkbox" value="3" id="rejudge_res_check_rj" name="rejudge_res_check[]">
                                    <label class="form-check-label text-secondary" for="rejudge_res_check_rj">
                                        评测中<span class="en-text text-muted">Running (RJ)</span>
                                    </label>
                                </div>
                            </div>
                        </div>
            </div>
                </div>
            </div>

            <!-- 重判设置区域 -->
            <div class="col-lg-8">
                <div class="card" style="max-width: 400px;">
                    <div class="card-header bg-light">
                        <h4 class="mb-0">重判设置<span class="en-text text-muted fs-6">Rejudge Settings</span></h4>
                    </div>
                    <div class="card-body">
                        <!-- 开关设置 -->
                        <div class="csg-switch-setting mb-4">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="csg-switch-setting-content">
                                    <div class="csg-switch-setting-title">重判后立刻打开评测状态窗口</div>
                                    <div class="csg-switch-setting-subtitle">Open Status Window After Rejudge Sent</div>
                                </div>
                                <div class="csg-switch-setting-control">
                                    <div class="csg-switch csg-switch-md">
                                        <input type="checkbox" 
                                               id="open_status_window_check" 
                                               name="open_status_window_check" 
                                               class="csg-switch-input"
                                               data-csg-storage="true"
                                               data-csg-storage-key="open_status_window_check"
                                               title="点击切换状态窗口设置">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 输入区域 -->
                        <div class="mb-3">
                            <label for="solution_id" class="form-label">基于提交号<span class="en-text text-muted d-block">By Solution ID</span></label>
                            <input type="text" class="form-control" id="solution_id" name="solution_id" placeholder="Solution ID 10001,10002...">
                        </div>
                        <div class="mb-3">
                            <label for="problem_id" class="form-label">基于题号<span class="en-text text-muted d-block">By Problem ID</span></label>
                            <input type="text" class="form-control" id="problem_id" name="problem_id" placeholder="{if isset($rejudge_type) && $rejudge_type == 'contest'}字母(Alphabet ID) A,B,C...{else/}数字(Numerate ID) 2000,2001...{/if}">
                        </div>

                        <!-- 按钮区域 -->
                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" id="submit_button" class="btn btn-primary bilingual-button">
                                <span class="cn-text"><i class="bi bi-arrow-clockwise me-2"></i>重判</span><span class="en-text">Rejudge</span>
                            </button>
                            <button type="reset" id="reset_button" class="btn btn-warning bilingual-button">
                                <span class="cn-text"><i class="bi bi-arrow-counterclockwise me-2"></i>重置表单</span><span class="en-text">Reset Form</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
<script type="text/javascript">
let DEFAULT_REJUDGE_RES = new Set(["5", "6", "7", "8", "9", "10", "11", "90", "100"]);

    $(document).ready(function() {
        // 快速选择按钮事件
        $('#check_res_all').click(function() {
            $('.rejudge_res_check').each(function() {
                this.checked = true;
            });
        });

        $('#check_res_non').click(function() {
            $('.rejudge_res_check').each(function() {
                this.checked = false;
            });
        });

        $('#check_res_rev').click(function() {
            $('.rejudge_res_check').each(function() {
                this.checked = !this.checked;
            });
        });

        $('#check_res_dft').click(function() {
            $('.rejudge_res_check').each(function() {
                this.checked = DEFAULT_REJUDGE_RES.has(this.value);
            });
        });

    // 监听AC复选框变化，显示警告
    $('#rejudge_res_check_ac').on('change', function() {
        if (this.checked) {
            alerty.alert({
                message: '请慎重重判AC的提交！',
                message_en: 'Be careful to rejudge accepted solutions!'
            });
        }
        });
    });

    // 通用重判JavaScript逻辑
    $(document).ready(function(){
        // 获取配置，支持题目重判和比赛重判
        var rejudgeConfig = window.rejudgeConfig || {
            module: "admin",
            submit_url: "/admin/problem/problem_rejudge_ajax"
        };
        
        // 使用Bootstrap5原生表单验证
        $('#problem_rejudge_form').on('submit', function(e) {
            e.preventDefault();
            
            var solution_id = $.trim($('#solution_id').val());
            var problem_id = $.trim($('#problem_id').val());
            
            // 清除之前的验证状态
            $('#solution_id, #problem_id').removeClass('is-invalid is-valid');
            $('.invalid-feedback').remove();
            
            // 验证输入
            var hasError = false;
            
            // 检查是否只填写了一个输入
            var inputCount = 0;
            if(solution_id.length > 0) inputCount++;
            if(problem_id.length > 0) inputCount++;
            
            if(inputCount > 1) {
                alerty.alert({
                    message: '请在题号和提交号之间选择一项',
                    message_en: 'Please only fill in one input'
                });
                return false;
            }
            
            if(inputCount === 0) {
                alerty.alert({
                    message: '请提供提交ID或题目ID',
                    message_en: 'Please give Solution ID or Problem ID for rejudging'
                });
                return false;
            }
            
            // 验证solution_id格式
            if(solution_id.length > 0) {
                if(!/^\d+$/.test(solution_id) || solution_id.length > 10) {
                    $('#solution_id').addClass('is-invalid');
                    $('#solution_id').after('<div class="invalid-feedback">请输入有效的提交ID</div>');
                    hasError = true;
                }
            }
            
            // 验证problem_id格式 - 支持多个ID用逗号分隔
            if(problem_id.length > 0) {
                // 检查是否包含逗号分隔的多个ID
                if(problem_id.includes(',')) {
                    var problemIds = problem_id.split(',');
                    for(var i = 0; i < problemIds.length; i++) {
                        var id = problemIds[i].trim();
                        if(!/^[A-Za-z0-9]+$/.test(id) || id.length > 5) {
                            $('#problem_id').addClass('is-invalid');
                            $('#problem_id').after('<div class="invalid-feedback">请输入有效的题目ID，多个ID用逗号分隔</div>');
                            hasError = true;
                            break;
                        }
                    }
                } else {
                    // 单个ID验证
                    if(!/^[A-Za-z0-9]+$/.test(problem_id) || problem_id.length > 5) {
                        $('#problem_id').addClass('is-invalid');
                        $('#problem_id').after('<div class="invalid-feedback">请输入有效的题目ID</div>');
                        hasError = true;
                    }
                }
            }
            
            if(hasError) return false;
            
            // 检查是否选择了AC结果进行重判
            let ac_warning = "";
            let ac_warning_en = "";
            if(document.querySelector('#rejudge_res_check_ac').checked) {
                ac_warning = "请慎重重判AC的提交，确认？";
                ac_warning_en = "Be careful to rejudge accepted solutions, confirm?";
            }
            
            // 根据输入类型显示确认对话框
            if(solution_id.length > 0) {
                if(ac_warning != '') {
                    alerty.confirm({
                        message: ac_warning,
                        message_en: ac_warning_en,
                        callback: function() {
                            SubmitRejudge($('#problem_rejudge_form')[0]);
                        }
                    });
                } else {
                    SubmitRejudge($('#problem_rejudge_form')[0]);
                }
            }
            else if(problem_id.length > 0) {
                let problem_warning = ac_warning + "基于题号评测时间较久，确认？";
                let problem_warning_en = ac_warning_en + "Rejudge by problem_id may take a long time, sure to rejudge?";
                
                alerty.confirm({
                    message: problem_warning,
                    message_en: problem_warning_en,
                    callback: function(){
                        SubmitRejudge($('#problem_rejudge_form')[0]);
                    }
                });
            }
            
            return false;
        });
    });

    function SubmitRejudge(form) {
        var submit_button = $('#submit_button');
        var submit_text = '重判';
        var submit_en_text = 'Rejudge';
        
        // 禁用按钮并显示加载状态
        submit_button.prop('disabled', true);
        // submit_button.html('<span class="cn-text"><i class="bi bi-arrow-clockwise me-2"></i>处理中...</span><span class="en-text">Processing...</span>');

        // 使用配置的提交URL
        var rejudgeConfig = window.rejudgeConfig || {
            module: "admin",
            submit_url: "/admin/problem/problem_rejudge_ajax"
        };
        
        // 动态设置表单action
        $(form).attr('action', rejudgeConfig.submit_url);

        $(form).ajaxSubmit({
            success: function(ret) {
                if (ret["code"] == 1) {
                    alerty.success({
                        message: ret.msg,
                        message_en: ret.msg_en || ret.msg
                    });
                    button_delay(submit_button, 1, '重判', null, submit_en_text);

                    // 检查是否需要打开状态窗口
                    if ($('#open_status_window_check').is(':checked')) {
                        setTimeout(function() {
                            window.open(ret['data']);
                        }, 300);
                    }
                } else {
                    alerty.alert({
                        message: ret.msg,
                        message_en: ret.msg_en || ret.msg
                    });
                    button_delay(submit_button, 1, submit_text, null, submit_en_text);
                }
                return false;
            },
        error: function() {
            alerty.alert({
                message: '提交失败，请重试',
                message_en: 'Submission failed, please try again'
            });
            button_delay(submit_button, 3, submit_text, null, submit_en_text);
            }
        });
    }
</script>

<style>
    /* CSG Switch组件样式已通过base_csg_switch.php引入 */

    /* 结果类型复选框样式增强 - 双列布局优化 */
    .form-check {
        margin-bottom: 0.5rem;
        padding-left: 1.5rem;
    }

    .form-check-sm {
        margin-bottom: 0.25rem;
        padding-left: 1.25rem;
    }

    .form-check-input {
        margin-top: 0.125rem;
        margin-left: -1.5rem;
    }

    .form-check-sm .form-check-input {
        margin-left: -1.25rem;
    }

    .form-check-label {
        cursor: pointer;
        font-weight: 500;
        transition: all 0.15s ease-in-out;
    }

    .form-check-sm .form-check-label {
        font-size: 0.9rem;
        line-height: 1.4;
    }

    .form-check-sm .en-text {
        font-size: 0.8rem;
    }

    .form-check-input:checked+.form-check-label {
        font-weight: 600;
    }

    /* 快速选择按钮组样式 */
    .btn-group .btn {
        border-radius: 0.375rem;
        margin-right: 0.25rem;
        transition: all 0.15s ease-in-out;
    }

    .btn-group .btn:last-child {
        margin-right: 0;
    }

    .btn-group .btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    /* 卡片样式增强 */
    .card {
        border: 1px solid rgba(0, 0, 0, 0.125);
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        transition: all 0.15s ease-in-out;
    }

    .card:hover {
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    }

    .card-header {
        background-color: #f8f9fa;
        border-bottom: 1px solid rgba(0, 0, 0, 0.125);
        font-weight: 600;
    }


    /* 响应式优化 */
    @media (max-width: 768px) {
        .btn-group {
            flex-direction: column;
        }

        .btn-group .btn {
            margin-right: 0;
            margin-bottom: 0.25rem;
        }

        .btn-group .btn:last-child {
            margin-bottom: 0;
        }

        .form-check {
            padding-left: 1.25rem;
        }

        .form-check-input {
            margin-left: -1.25rem;
        }

        /* 双列布局在小屏幕上变为单列 */
        .row.g-2 .col-6 {
            flex: 0 0 100%;
            max-width: 100%;
        }

        /* CSG Switch组件已包含响应式设计 */
    }

    /* 表单验证样式 */
    .is-invalid {
        border-color: #dc3545;
        box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
    }

    .is-valid {
        border-color: #198754;
        box-shadow: 0 0 0 0.2rem rgba(25, 135, 84, 0.25);
    }

    .invalid-feedback {
        display: block;
        width: 100%;
        margin-top: 0.25rem;
        font-size: 0.875em;
        color: #dc3545;
    }

</style>