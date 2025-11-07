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
    data-sort-name="client_id"
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
        <th data-field="client_id" data-align="center" data-valign="middle" data-sortable="true" data-width="80">ID</th>
        <th data-field="team_id_bind" data-align="center" data-valign="middle" data-sortable="true" data-width="120">队伍号<span class="en-text">Team ID</span></th>
        <th data-field="ip_bind" data-align="center" data-valign="middle" data-sortable="true" data-width="150">IP地址<span class="en-text">IP Address</span></th>
        <th data-field="ssh_user" data-align="center" data-valign="middle" data-width="100">SSH用户<span class="en-text">SSH User</span></th>
        <th data-field="ssh_port" data-align="center" data-valign="middle" data-width="80">SSH端口<span class="en-text">SSH Port</span></th>
        <th data-field="connect_status" data-align="center" data-valign="middle" data-width="100" data-formatter="FormatterConnectStatus">连接状态<span class="en-text">Connect Status</span></th>
        <th data-field="lock_status" data-align="center" data-valign="middle" data-width="100" data-formatter="FormatterLockStatus">锁屏状态<span class="en-text">Lock Status</span></th>
        <th data-field="ssh_actions" data-align="center" data-valign="middle" data-width="200" data-formatter="FormatterSshActions" data-sortable="false" data-visible="true">SSH操作<span class="en-text">SSH Actions</span></th>
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
        client_list_url: "/cpcsys/admin/contest_client_list_ajax?cid=<?php echo $contest['contest_id']; ?>",
        client_save_url: "/cpcsys/admin/contest_client_save_ajax?cid=<?php echo $contest['contest_id']; ?>",
        client_del_url: "/cpcsys/admin/contest_client_del_ajax?cid=<?php echo $contest['contest_id']; ?>",
        client_ssh_url: "/cpcsys/admin/contest_client_ssh_ajax?cid=<?php echo $contest['contest_id']; ?>"
    }
    
    TextAllowTab('client_description');
    
    $(function() {
        ClientManageInit();
    });
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
    
    .fixed-table-toolbar {
        margin: 0 !important;
        padding: 0 !important;
    }
    
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
</style>

