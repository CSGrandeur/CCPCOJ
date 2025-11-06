{include file="../../csgoj/view/public/base_csg_switch" /}

<div class="admin-page-header">
    <div class="admin-page-header-left">
        <div class="admin-page-header-icon">
            <i class="bi bi-people-fill"></i>
        </div>
        <h1 class="admin-page-header-title">
            <div class="admin-page-header-title-main">
                {if $action == 'contest_staffgen'}
                    工作人员生成
                {else /}
                    队伍生成
                {/if}
            </div>
            <div class="admin-page-header-title-right">
                <a href="__CPC__/contest/contest?cid={$contest['contest_id']}" class="admin-page-header-id">
                    <i class="bi bi-hash"></i> {$contest['contest_id']}
                </a>
                <span class="en-text">
                    {if $action == 'contest_staffgen'}
                        Staff Generator
                    {else /}
                        Team Generator
                    {/if}
                </span>
            </div>
        </h1>
    </div>
    <div class="admin-page-header-right">
        <button type="button" class="btn btn-outline-info btn-sm" data-bs-toggle="collapse" data-bs-target="#teamgen_help_div" aria-expanded="false" aria-controls="navbar">
            <span class="cn-text"><i class="bi bi-question-circle me-1"></i>
            帮助</span><span class="en-text">Help</span>
        </button>
    </div>
</div>
<div class="container">
    <article id="teamgen_help_div" class="alert alert-info collapse">
        {if $action == 'contest_staffgen'}
            <h5 class="bilingual-inline">
                文本输入模式
                <span class="en-text">Text Input Mode</span>
            </h5>
            <p>每行一个工作人员，该工作人员信息由制表符<code>\t</code>隔开。信息从左到右依次为：</p>
            <p>账号、姓名、房间、权限、密码，例如：</p>
            <p><code>admin01[\t]郭大侠[\t]A区[\t]admin[\t]123456</code></p>
            <p>可用权限如下：</p>
            <ul>
                <li><code>admin</code>: 监考员，可管理部分比赛配置</li>
                <li><code>printer</code>: 打印管理员，负责打印机</li>
                <li><code>balloon_manager</code>: 气球管理员，建议只设置一名</li>
                <li><code>balloon_sender</code>: 气球配送员，可查看气球队列和领取气球任务</li>
                <li><code>watcher</code>: 观察员，外榜、直播用</li>
            </ul>
            <p>对于printer和balloon_sender，如不指定房间，则由使用者自己进行过滤。如指定房间，则使用者只能处理对应房间的数据。</p>
        {else /}
            <h5 class="bilingual-inline">
                文本输入模式
                <span class="en-text">Text Input Mode</span>
            </h5>
        <p>每行一个队伍，该队伍信息由制表符<code>\t</code>隔开（从Excel文档复制即可）。信息从左到右依次为：</p>
            <p>队号（纯数字）、队名、队名英文、学校、地区、队员、教练、房间（如果有的话）、队伍类型（0普通/1女队/2打星）、预设密码，例如：</p>
            <p><code>001[\t]XX大学一队[\t]XX University Team 1[\t]XX大学[\t]中国[\t]队员一、队员二、队员三[\t]教练名[\t]机房A[\t]0[\t]123456</code></p>
            <p>队号、密码、房间、队名英文、地区可为空，由系统自动生成，但"<code>\t</code>"分隔符必须有，信息是以第几个分隔符来对应的。</p>
        <p>比如 <code>[\t][\t]测试[\t][\t]333[\t]</code>，即会自动生成一个这样的队伍：</p>
            <div class="table-responsive">
                <table class="table table-sm table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Team ID</th><th>Team Name</th><th>Team Name EN</th><th>School</th><th>Region</th>
                            <th>Member</th><th>Coach</th><th>Room</th><th>Team Kind</th><th>Password</th>
                        </tr>
                    </thead>
            <tbody>
                        <tr><td>team0001</td><td>一队</td><td>null</td><td>测试</td><td>null</td><td>null</td><td>333</td><td></td><td>0</td><td>V395JKQB</td></tr>
            </tbody>
        </table>
            </div>
        <p>与报名系统导出的表格对应，可从excel表格复制对应的列直接粘贴在这里生成队伍。</p>
        {/if}
        
        <h5 class="bilingual-inline">
            Excel导入模式
            <span class="en-text">Excel Import Mode</span>
        </h5>
        <p>点击"下载模板"按钮下载Excel模板，填写信息后上传即可批量导入。</p>
        <p>模板包含所有必要字段，{if $action == 'contest_staffgen'}权限列提供下拉选择{else /}地区列提供下拉选择，支持中英文队名{/if}。</p>
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
    <form id="contest_teamgen_form" method='post' action="__CPC__/admin/contest_teamgen_ajax?cid={$contest['contest_id']}">
                        <div class="mb-3">
                            <textarea id="team_description" class="form-control" 
                                placeholder="每行一个{if $action == 'contest_staffgen'}工作人员{else /}队伍{/if}，用制表符分隔... / One {if $action == 'contest_staffgen'}staff{else /}team{/if} per line, separated by tabs..." 
                                rows="3" name="team_description"></textarea>
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
    
    <!-- 全局设置 -->
    <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0 bilingual-inline">
                    <span class="cn-text"><i class="bi bi-gear me-2"></i>
                    全局设置
                    </span><span class="en-text">Global Settings</span>
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="csg-switch-setting mb-4">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="csg-switch-setting-content">
                                    <div class="csg-switch-setting-title">
                                        <span class="cn-text"><i class="bi bi-exclamation-triangle me-1 text-warning"></i>
                                        重新生成所有{if $action == 'contest_staffgen'}工作人员{else /}队伍{/if}
                                        </span><span class="en-text">Regenerate All {if $action == 'contest_staffgen'}Staff{else /}Teams{/if}</span>
                                    </div>
                                    <div class="csg-switch-setting-subtitle">
                                        开启后，提交数据时将清除所有现有{if $action == 'contest_staffgen'}工作人员{else /}队伍{/if}，重新生成新的{if $action == 'contest_staffgen'}工作人员{else /}队伍{/if}数据。
                                        <span class="en-text">When enabled, all existing {if $action == 'contest_staffgen'}staff{else /}teams{/if} will be cleared and new {if $action == 'contest_staffgen'}staff{else /}teams{/if} data will be generated when submitting data.</span>
                                    </div>
                                </div>
                                <div class="csg-switch-setting-control">
                                    <div class="csg-switch csg-switch-md">
                                        <input type="checkbox" 
                                               id="reset_team" 
                                               name="reset_team" 
                                               class="csg-switch-input"
                                               data-csg-storage="true"
                                               data-csg-storage-key="reset_team"
                                               title="点击切换重新生成队伍设置">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="password_seed" class="form-label bilingual-inline">
                                密码种子
                                <span class="en-text">Password Seed</span>
                            </label>
                            <input type="number" id="password_seed" name="password_seed" class="form-control" 
                                placeholder="留空使用随机种子 / Leave empty for random seed" 
                                min="1" max="999999999">
                        </div>
                        <div class="form-text">
                            <div >
                                设置种子可确保相同team_id的密码固定不变，留空则每次生成随机密码
                                <span class="en-text">Set seed to ensure consistent passwords for same team_id, leave empty for random passwords</span>
                            </div>
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
            比赛已结束，不允许调整{if $action == 'contest_staffgen'}工作人员{else /}队伍{/if}
            </span><span class="en-text">Contest ended, {if $action == 'contest_staffgen'}staff generation{else /}team generation{/if} not allowed</span>
        </h3>
    </div>
    {/if}



<div id="teamgen_toolbar" class="mb-3">
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
            <button type="button" class="btn btn-primary bilingual-button" id="export_teamgen_pageteam_btn">
                <span><i class="bi bi-download me-1"></i>导出密码条</span>
                <span class="en-text">Export Password Sheet</span>
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
    id="teamgen_table"
    data-toggle="table"
    data-buttons-align="left"
    data-sort-name="team_id"
    data-sort-order="asc"
    data-unique-id="row_index"
    data-toolbar="#teamgen_toolbar"
    data-toolbar-align="right"
    data-pagination="false"
    data-method="get"
        class="table table-striped table-hover"
        style="table-layout: fixed; word-wrap: break-word;"
>
    <thead>
    <tr>
            <th data-field="idx" data-align="center" data-valign="middle" data-sortable="false" data-width="60" data-formatter="FormatterIndex">序号<span class="en-text">Index</span></th>
            <th data-field="team_id" data-align="center" data-valign="middle" data-sortable="true" data-width="120" data-cell-style="cellStyleTeamId">账号<span class="en-text">ID</span></th>
            <th data-field="name" data-align="left" data-valign="middle" data-width="160">姓名<span class="en-text">Name</span></th>
            {if $action == 'contest_teamgen'}
            <th data-field="name_en" data-align="left" data-valign="middle" >副语言队名<span class="en-text">Secondary Language Name</span></th>
            <th data-field="school" data-align="center" data-valign="middle" data-width="150">学校/组织<span class="en-text">School/Organization</span></th>
            <th data-field="region" data-align="center" data-valign="middle" data-formatter="FormatterRegion">国家/地区<span class="en-text">Country/Region</span></th>
            <th data-field="tmember" data-align="center" data-valign="middle">队员<span class="en-text">Member</span></th>
            <th data-field="coach" data-align="center" data-valign="middle" data-width="80">教练<span class="en-text">Coach</span></th>
            <th data-field="tkind" data-align="center" data-valign="middle" data-formatter="FormatterTkind" data-width="60">类型<span class="en-text">Tkind</span></th>
            {/if}
            <th data-field="room" data-align="center" data-valign="middle">房间/区域<span class="en-text">Room/Area</span></th>
            {if $action == 'contest_staffgen'}
            <th data-field="privilege" data-align="center" data-valign="middle" data-width="150">权限<span class="en-text">Privilege</span></th>
            {/if}
            <th data-field="password" data-align="center" data-valign="middle" data-width="100">密码<span class="en-text">Password</span></th>
        <th data-field="validation_errors" data-align="center" data-valign="middle" data-width="60" data-formatter="FormatterValidationErrors" data-visible="false" data-sortable="true" >错误信息<span class="en-text">Validation Errors</span></th>
        {if ($action == 'contest_teamgen' || $action == 'contest_staffgen') && $contestStatus != 2}
        <th data-field="modify" data-align="center" data-valign="middle" data-width="70" data-formatter="FormatterModify" data-sortable="false">修改<span class="en-text">Modify</span></th>
        <th data-field="delete" data-align="center" data-valign="middle" data-width="60" data-formatter="FormatterDel">删除<span class="en-text">Del(Dbl Click)</span></th>
        {/if}
    </tr>
    </thead>
</table>
</div>

<!-- 错误详情模态框 -->
<div class="modal fade" id="errorDetailModal" tabindex="-1" aria-labelledby="errorDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title bilingual-inline" id="errorDetailModalLabel">
                    <span class="cn-text"><i class="bi bi-exclamation-triangle me-2"></i>
                    数据验证错误详情
                    </span><span class="en-text">Data Validation Error Details</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="error_detail_content">
                    <!-- 错误详情内容将在这里动态填充 -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <span class="cn-text"><i class="bi bi-x-circle me-1"></i>
                    关闭</span><span class="en-text">Close</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    
    var TEAMGEN_CONFIG = {
        contest_id: "<?php echo $contest['contest_id']; ?>",
        contest_title: "<?php echo htmlspecialchars($contest['title'], ENT_QUOTES, 'UTF-8'); ?>",
        action: "<?php echo $action; ?>",
        ttype: "<?php echo $action == 'contest_staffgen' ? '1' : '0'; ?>",
        teamgen_data_url: "/cpcsys/admin/teamgen_list_ajax?cid=<?php echo $contest['contest_id']; ?>&ttype=<?php echo $action == 'contest_staffgen' ? '1' : '0'; ?>"
    }
    TextAllowTab('team_description');
    
    $(function() {
        // 初始化页面
        if (TEAMGEN_CONFIG.action === 'contest_staffgen') {
            StaffgenInit();
        } else {
            TeamgenInit();
        }
        
        // 数据加载已改为手动处理，无需监听事件
    });
</script>


{include file="../../csgoj/view/public/js_exceljs" /}
{css href="__STATIC__/css/bilingual.css" /}
{js href="__STATIC__/js/bilingual.js" /}
{js href="__STATIC__/csgoj/contest/teamgen.js" /}
{if $action == 'contest_teamgen'}
{include file="../../cpcsys/view/admin/team_modify" /}
{/if}

<style type="text/css">
    #teamgen_table {
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
    

</style>