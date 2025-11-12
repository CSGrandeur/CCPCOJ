{include file="../../csgoj/view/public/base_csg_switch" /}

<div class="admin-page-header">
    <div class="admin-page-header-left">
        <div class="admin-page-header-icon">
            <i class="bi bi-laptop"></i>
        </div>
        <h1 class="admin-page-header-title">
            <div class="admin-page-header-title-main">
                客户端管理
            </div>
            <div class="admin-page-header-title-right">
                <a href="__CPC__/contest/contest?cid={$contest['contest_id']}" class="admin-page-header-id">
                    <i class="bi bi-hash"></i> {$contest['contest_id']}
                </a>
                <span class="en-text">
                    Client Management
                </span>
            </div>
        </h1>
    </div>
    <div class="admin-page-header-right">
        <button type="button" id="collect_mode_toggle_btn" 
                class="btn btn-sm collect-mode-toggle" 
                data-status="{$flg_collect_team_id}"
                data-invalid="{$is_collect_mode_invalid ? 1 : 0}"
                data-contest-id="{$contest['contest_id']}"
                {if $flg_collect_team_id == 1 && $is_collect_mode_invalid}
                title="当前：收集模式已开启但已无效（比赛前10分钟自动无效） / Current: Collect Mode Enabled but Invalid (automatically disabled 10 minutes before contest) | 点击切换为关闭 / Click to disable"
                {elseif $flg_collect_team_id == 1}
                title="当前：收集模式已开启 / Current: Collect Mode Enabled | 点击切换为关闭 / Click to disable"
                {else /}
                title="当前：收集模式已关闭 / Current: Collect Mode Disabled | 点击切换为开启 / Click to enable"
                {/if}
                style="margin-right: 0.5rem;">
            {if $flg_collect_team_id == 1}
            <span class="cn-text"><i class="bi bi-toggle-on"></i> 收集模式</span><span class="en-text">Collect Mode</span>
            {else /}
            <span class="cn-text"><i class="bi bi-toggle-off"></i> 收集模式</span><span class="en-text">Collect Mode</span>
            {/if}
        </button>
        <button type="button" class="btn btn-outline-info btn-sm" data-bs-toggle="collapse" data-bs-target="#client_help_div" aria-expanded="false" aria-controls="navbar">
            <span class="cn-text"><i class="bi bi-question-circle me-1"></i>
            帮助</span><span class="en-text">Help</span>
        </button>
    </div>
</div>
<div class="container">
    <article id="client_help_div" class="alert alert-info collapse">
        <h5 class="bilingual-inline">
            文本输入模式
            <span class="en-text">Text Input Mode</span>
        </h5>
        <p>每行一个客户端，该客户端信息由制表符<code>\t</code>隔开。信息从左到右依次为：</p>
        <p>队伍号、IP地址、SSH用户名、SSH密码、SSH RSA密钥、SSH端口，例如：</p>
        <p><code>team001[\t]192.168.1.100[\t]admin[\t]password123[\t][\t]22</code></p>
        <p>SSH配置说明：</p>
        <ul>
            <li>如果所有SSH字段都为空，则不需要SSH配置</li>
            <li>如果提供SSH配置，则SSH用户名和端口为必填项</li>
            <li>SSH密码和RSA密钥至少需要提供一个</li>
            <li>SSH端口默认为22</li>
        </ul>
        
        <h5 class="bilingual-inline">
            Excel导入模式
            <span class="en-text">Excel Import Mode</span>
        </h5>
        <p>点击"下载模板"按钮下载Excel模板，填写信息后上传即可批量导入。</p>
        <p>模板包含所有必要字段，支持批量编辑客户端信息。</p>
    </article>
    
    {if $contestStatus != 2}
    
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0 bilingual-inline">
                        <span class="cn-text"><i class="bi bi-pencil-square me-2"></i>
                        文本输入模式
                        </span><span class="en-text">Text Input Mode</span>
                    </h5>
                </div>
                <div class="card-body">
                    <form id="client_manage_form" method='post' action="__CPC__/admin/contest_client_save_ajax?cid={$contest['contest_id']}">
                        <div class="mb-3">
                            <textarea id="client_description" class="form-control" 
                                placeholder="每行一个客户端，用制表符分隔... / One client per line, separated by tabs..." 
                                rows="3" name="client_description"></textarea>
                        </div>
                        <button type="button" id="parse_data_btn" class="btn btn-outline-primary w-100">
                            <span><i class="bi bi-search me-1"></i>解析数据</span>
                            <span class="en-text">Parse Data</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0 bilingual-inline">
                        <span class="cn-text"><i class="bi bi-file-earmark-excel me-2"></i>
                        Excel导入模式
                        </span><span class="en-text">Excel Import Mode</span>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <button type="button" id="download_template_btn" class="btn btn-success w-100">
                            <span><i class="bi bi-download me-1"></i>下载模板</span>
                            <span class="en-text">Download Template</span>
                        </button>
                    </div>
                    <div class="mb-3">
                        <button type="button" id="excel_file_btn" class="btn btn-outline-primary w-100">
                            <span><i class="bi bi-file-earmark-excel me-1"></i>选择Excel文件</span>
                            <span class="en-text">Select Excel File</span>
                        </button>
                        <input type="file" id="excel_file_input" style="display: none;" accept=".xlsx,.xls" />
                        <div class="form-text">
                            <span class="bilingual-inline">
                                选择文件后将自动解析并预览数据
                                <span class="en-text">File will be parsed and previewed automatically after selection</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    {else /}
    <div class="alert alert-danger">
        <h3 class="bilingual-inline">
            <span class="cn-text"><i class="bi bi-exclamation-triangle me-2"></i>
            比赛已结束，不允许调整客户端
            </span><span class="en-text">Contest ended, client management not allowed</span>
        </h3>
    </div>
    {/if}

<div id="client_toolbar" class="mb-3">
    <div class="toolbar-container">
        <div class="toolbar-left">
            <h5 id="toolbar_title">
                <span class="cn-text"><i class="bi bi-table me-2"></i>
                数据预览</span><span class="en-text">Data Preview</span>
            </h5>
            <small class="text-muted" id="toolbar_subtitle">
                <span class="bilingual-inline">
                    请检查数据后执行导入
                    <span class="en-text">Please review data before importing</span>
                </span>
            </small>
        </div>
        <div class="toolbar-right">
            <button type="button" class="btn btn-success bilingual-button" id="execute_import_btn" style="display: none;">
                <span><i class="bi bi-check-circle me-1"></i>执行导入</span>
                <span class="en-text">Execute Import</span>
            </button>
            <button type="button" class="btn btn-danger bilingual-button" id="batch_delete_btn" style="display: none;">
                <span><i class="bi bi-trash me-1"></i>批量删除</span>
                <span class="en-text">Batch Delete</span>
            </button>
            <button type="button" class="btn btn-primary bilingual-button" id="batch_check_connect_btn" style="display: none;">
                <span><i class="bi bi-wifi me-1"></i>批量确认连接</span>
                <span class="en-text">Batch Check Connect</span>
            </button>
            <button type="button" class="btn btn-warning bilingual-button" id="batch_lock_btn" style="display: none;">
                <span><i class="bi bi-lock me-1"></i>批量锁屏</span>
                <span class="en-text">Batch Lock</span>
            </button>
            <button type="button" class="btn btn-info bilingual-button" id="batch_unlock_btn" style="display: none;">
                <span><i class="bi bi-unlock me-1"></i>批量解锁</span>
                <span class="en-text">Batch Unlock</span>
            </button>
            <button type="button" class="btn btn-info bilingual-button" id="export_standard_btn">
                <span><i class="bi bi-download me-1"></i>导出标准数据</span>
                <span class="en-text">Export Standard Data</span>
            </button>
        </div>
    </div>
    <div id="error_summary" class="alert alert-warning mt-2" style="display: none;">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <span id="error_count">0</span> 行数据有错误，请检查后重新导入
        <span class="en-text">rows have errors, please check and re-import</span>
    </div>
</div>

<div class="table-responsive">
<table
    id="client_table"
    data-toggle="table"
    data-buttons-align="left"
    data-sort-name="team_id_bind"
    data-sort-order="asc"
    data-unique-id="client_id"
    data-toolbar="#client_toolbar"
    data-toolbar-align="right"
    data-pagination="false"
    data-method="get"
    data-url="__CPC__/admin/contest_client_list_ajax?cid={$contest['contest_id']}"
    data-multiple-select-row="true"
    data-click-to-select="true"
    data-maintain-meta-data="true"
    class="table table-striped table-hover"
    style="table-layout: fixed; word-wrap: break-word;"
>
    <thead>
    <tr>
        <th data-field="state" data-checkbox="true" data-width="50"></th>
        <th data-field="idx" data-align="center" data-valign="middle" data-sortable="false" data-width="80" data-formatter="FormatterIdx">序号<span class="en-text">Index</span></th>
        <th data-field="team_id_bind" data-align="center" data-valign="middle" data-sortable="true" data-width="120">队伍号<span class="en-text">Team ID</span></th>
        <th data-field="ip_bind" data-align="center" data-valign="middle" data-sortable="true" data-width="150">IP地址<span class="en-text">IP Address</span></th>
        <!-- <th data-field="ssh_user" data-align="center" data-valign="middle" data-width="100">SSH用户<span class="en-text">SSH User</span></th>
        <th data-field="ssh_port" data-align="center" data-valign="middle" data-width="80">SSH端口<span class="en-text">SSH Port</span></th>
        <th data-field="connect_status" data-align="center" data-valign="middle" data-width="100" data-formatter="FormatterConnectStatus">连接状态<span class="en-text">Connect Status</span></th>
        <th data-field="lock_status" data-align="center" data-valign="middle" data-width="100" data-formatter="FormatterLockStatus">锁屏状态<span class="en-text">Lock Status</span></th>
        <th data-field="ssh_actions" data-align="center" data-valign="middle" data-width="200" data-formatter="FormatterSshActions" data-sortable="false" data-visible="true">SSH操作<span class="en-text">SSH Actions</span></th> -->
        <th data-field="validation_errors" data-align="center" data-valign="middle" data-width="60" data-formatter="FormatterValidationErrors" data-visible="false" data-sortable="true">错误信息<span class="en-text">Validation Errors</span></th>
        {if $contestStatus != 2}
        <th data-field="modify" data-align="center" data-valign="middle" data-width="70" data-formatter="FormatterModify" data-sortable="false">修改<span class="en-text">Modify</span></th>
        <th data-field="delete" data-align="center" data-valign="middle" data-width="60" data-formatter="FormatterDel" data-sortable="false">删除<span class="en-text">Del(Dbl Click)</span></th>
        {/if}
    </tr>
    </thead>
</table>
</div>

<script type="text/javascript">
    var CLIENT_MANAGE_CONFIG = {
        contest_id: "<?php echo $contest['contest_id']; ?>",
        contest_title: "<?php echo htmlspecialchars($contest['title'], ENT_QUOTES, 'UTF-8'); ?>",
        contest_status: <?php echo $contestStatus; ?>,
        contest_start_time: <?php echo $contest['start_time'] ? strtotime($contest['start_time']) : 0; ?>,
        client_list_url: "/cpcsys/admin/contest_client_list_ajax?cid=<?php echo $contest['contest_id']; ?>",
        client_save_url: "/cpcsys/admin/contest_client_save_ajax?cid=<?php echo $contest['contest_id']; ?>",
        client_del_url: "/cpcsys/admin/contest_client_del_ajax?cid=<?php echo $contest['contest_id']; ?>",
        client_ssh_url: "/cpcsys/admin/contest_client_ssh_ajax?cid=<?php echo $contest['contest_id']; ?>"
    }
    
    TextAllowTab('client_description');
    
    $(function() {
        ClientManageInit();
        
        // 初始化收集模式按钮样式（根据无效化状态）
        var collectModeBtn = $('#collect_mode_toggle_btn');
        var btnStatus = parseInt(collectModeBtn.attr('data-status'));
        var btnInvalid = parseInt(collectModeBtn.attr('data-invalid'));
        
        if (btnStatus == 1) {
            if (btnInvalid == 1) {
                collectModeBtn.removeClass('btn-outline-secondary btn-success').addClass('btn-warning');
            } else {
                collectModeBtn.removeClass('btn-outline-secondary btn-warning').addClass('btn-success');
            }
        } else {
            collectModeBtn.removeClass('btn-success btn-warning').addClass('btn-outline-secondary');
        }
        
        // 收集模式切换按钮事件
        $('#collect_mode_toggle_btn').on('click', function() {
            var btn = $(this);
            var currentStatus = parseInt(btn.attr('data-status'));
            var newStatus = currentStatus == 1 ? 0 : 1;
            
            // 如果要开启收集模式，显示确认对话框（红色醒目）
            if (newStatus == 1) {
                // 计算是否已经在无效化时间内（比赛前10分钟）
                var now = Math.floor(Date.now() / 1000);
                var startTime = CLIENT_MANAGE_CONFIG.contest_start_time || 0;
                var timeDiff = startTime - now;
                var isAlreadyInvalid = timeDiff < 600; // 600秒 = 10分钟
                
                // 构建提示信息
                var warningMessage = '';
                var warningMessageEn = '';
                
                if (isAlreadyInvalid) {
                    warningMessage = '<div class="alert alert-warning border-warning mb-3"><div class="alerty-bilingual"><div class="alerty-primary"><strong><i class="bi bi-exclamation-triangle-fill me-2"></i>当前时间已在比赛前10分钟内，收集模式开启后将立即无效！</strong></div><div class="alerty-secondary"><strong><i class="bi bi-exclamation-triangle-fill me-2"></i>Current time is within 10 minutes before the contest starts. Collect mode will be invalid immediately after enabling!</strong></div></div></div>';
                }
                
                var mainMessage = '<div class="alert alert-danger border-danger"><div class="alerty-bilingual"><div class="alerty-primary"><strong>收集模式将取代比赛登录页内容，提供账号收集功能；赛前10分钟将自动无效，但仍建议选手使用前手动关闭收集模式！</strong></div><div class="alerty-secondary"><strong>Collect mode will replace the contest login page content and provide account collection functionality. It will automatically become invalid 10 minutes before the contest starts, but it is still recommended to manually disable collect mode before contestants use it!</strong></div></div></div>';
                
                // 创建红色醒目的确认对话框
                var modalId = 'alerty-modal-' + Date.now();
                var modalHtml = `
                    <div class="modal fade" id="${modalId}" tabindex="-1" aria-labelledby="${modalId}Label" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
                        <div class="modal-dialog">
                            <div class="modal-content border-danger">
                                <div class="modal-header bg-danger text-white">
                                    <h5 class="modal-title bilingual-inline" id="${modalId}Label">
                                        <span class="cn-text"><i class="bi bi-exclamation-triangle-fill me-2"></i>警告</span>
                                        <span class="en-text">Warning</span>
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    ${warningMessage}
                                    ${mainMessage}
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                                    <button type="button" class="btn btn-danger" id="alerty-confirm-btn">确定</button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                document.body.insertAdjacentHTML('beforeend', modalHtml);
                var modalElement = document.getElementById(modalId);
                var modal = new bootstrap.Modal(modalElement, {
                    backdrop: 'static',
                    keyboard: false
                });
                
                modalElement.querySelector('#alerty-confirm-btn').addEventListener('click', function() {
                    modal.hide();
                    toggleCollectMode(btn, newStatus);
                });
                
                modalElement.addEventListener('hidden.bs.modal', function() {
                    modalElement.remove();
                });
                
                modal.show();
            } else {
                toggleCollectMode(btn, newStatus);
            }
        });
    });
    
    function toggleCollectMode(btn, newStatus) {
        $.get('/cpcsys/admin/contest_collect_mode_toggle_ajax?cid=' + CLIENT_MANAGE_CONFIG.contest_id, function(ret) {
            if (ret && ret.code == 1) {
                // 更新按钮状态
                btn.attr('data-status', newStatus);
                
                // 计算是否已无效化（比赛前10分钟）
                var now = Math.floor(Date.now() / 1000);
                var startTime = CLIENT_MANAGE_CONFIG.contest_start_time || 0;
                var timeDiff = startTime - now;
                var isInvalid = (newStatus == 1 && timeDiff < 600); // 600秒 = 10分钟
                
                btn.attr('data-invalid', isInvalid ? 1 : 0);
                
                if (newStatus == 1) {
                    if (isInvalid) {
                        btn.removeClass('btn-outline-secondary btn-success').addClass('btn-warning');
                        btn.html('<span class="cn-text"><i class="bi bi-toggle-on"></i> 收集模式</span><span class="en-text">Collect Mode</span>');
                        btn.attr('title', '当前：收集模式已开启但已无效（比赛前10分钟自动无效） / Current: Collect Mode Enabled but Invalid (automatically disabled 10 minutes before contest) | 点击切换为关闭 / Click to disable');
                    } else {
                        btn.removeClass('btn-outline-secondary btn-warning').addClass('btn-success');
                        btn.html('<span class="cn-text"><i class="bi bi-toggle-on"></i> 收集模式</span><span class="en-text">Collect Mode</span>');
                        btn.attr('title', '当前：收集模式已开启 / Current: Collect Mode Enabled | 点击切换为关闭 / Click to disable');
                    }
                } else {
                    btn.removeClass('btn-success btn-warning').addClass('btn-outline-secondary');
                    btn.html('<span class="cn-text"><i class="bi bi-toggle-off"></i> 收集模式</span><span class="en-text">Collect Mode</span>');
                    btn.attr('title', '当前：收集模式已关闭 / Current: Collect Mode Disabled | 点击切换为开启 / Click to enable');
                }
                alerty.success('账号收集模式' + ret.data.status_str, 'Collect mode ' + ret.data.status_str);
            } else {
                alerty.error(ret?.msg || '切换失败', ret?.msg || 'Toggle failed');
            }
        }, 'json').fail(function() {
            alerty.error('切换失败，请重试', 'Toggle failed, please try again');
        });
    }
</script>

{include file="../../csgoj/view/public/js_exceljs" /}
{css href="__STATIC__/css/bilingual.css" /}
{js href="__STATIC__/js/bilingual.js" /}
{js href="__STATIC__/cpcsys/admin/client_manage.js" /}

<style type="text/css">
    #client_table {
        font-family: 'Simsun', 'Microsoft Yahei Mono', 'Lato', "PingFang SC", "Microsoft YaHei", sans-serif;
        word-wrap: break-word;
    }
    
    
    /* 针对Bootstrap Table生成的工具栏结构进行修复 */
    .fixed-table-toolbar {
        margin: 0 !important;
        padding: 0 !important;
    }
    
    .fixed-table-toolbar .bs-bars {
        float: none !important;
        display: block !important;
        width: 100% !important;
    }
    
    .fixed-table-toolbar .bs-bars.float-right {
        float: none !important;
    }
    
    /* 自定义工具栏样式 - 强制左对齐 */
    .toolbar-container {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        width: 100%;
        margin: 0;
        padding: 0;
    }
    
    .toolbar-left {
        flex: 1;
        margin: 0;
        padding: 0;
        text-align: left;
    }
    
    .toolbar-right {
        flex-shrink: 0;
        display: flex;
        gap: 0.5rem;
        align-items: center;
        margin: 0;
        padding: 0;
    }
    
    /* 强制标题左对齐，移除所有默认间距 */
    #toolbar_title {
        margin: 0 !important;
        padding: 0 !important;
        text-align: left !important;
        line-height: 1.2;
    }
    
    #toolbar_subtitle {
        margin: 0 !important;
        padding: 0 !important;
        text-align: left !important;
        display: block;
        margin-top: 0.25rem !important;
    }
    
    /* 确保图标不产生额外间距 */
    #toolbar_title i {
        margin-right: 0.5rem;
    }
    
    /* 实际数据状态样式 */
    .toolbar-actual-data {
        background-color: rgba(13, 110, 253, 0.1) !important;
        border-left: 4px solid #0d6efd !important;
        padding: 0.75rem 1rem !important;
        border-radius: 0.375rem !important;
    }
    
    .toolbar-actual-data #toolbar_title {
        color: #0d6efd !important;
        font-size: 1.1rem !important;
        font-weight: 600 !important;
    }
    
    .toolbar-actual-data #toolbar_title i {
        color: #0d6efd !important;
    }
    
    /* 数据预览状态样式 */
    .toolbar-preview-data {
        background-color: rgba(255, 193, 7, 0.1) !important;
        border-left: 4px solid #ffc107 !important;
        padding: 0.75rem 1rem !important;
        border-radius: 0.375rem !important;
    }
    
    .toolbar-preview-data #toolbar_title {
        color: #b45309 !important;
        font-size: 1.1rem !important;
        font-weight: 600 !important;
    }
    
    .toolbar-preview-data #toolbar_title i {
        color: #b45309 !important;
    }
    
    .connect-status-icon {
        display: inline-block;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        margin-right: 5px;
    }
    
    .connect-status-10min { background-color: #28a745; }
    .connect-status-30min { background-color: #ffc107; }
    .connect-status-2hour { background-color: #fd7e14; }
    .connect-status-old { background-color: #dc3545; }
    .connect-status-unknown { background-color: #6c757d; }
    
    /* 收集模式按钮样式 */
    .collect-mode-toggle {
        transition: all 0.3s ease;
    }
    
    .collect-mode-toggle[data-status="1"] {
        background-color: #198754;
        border-color: #198754;
        color: white;
    }
    
    /* 收集模式已开启但已无效化（比赛前10分钟） */
    .collect-mode-toggle[data-status="1"][data-invalid="1"] {
        background-color: #ffc107;
        border-color: #ffc107;
        color: #000;
    }
    
    .collect-mode-toggle[data-status="0"] {
        background-color: transparent;
        border-color: #6c757d;
        color: #6c757d;
    }
    
    /* 确认对话框红色醒目样式 */
    .alerty-modal .modal-content .alert-danger,
    .alerty-modal .modal-content .alert-warning {
        border-left: 4px solid #dc3545;
        background-color: #f8d7da;
    }
</style>

