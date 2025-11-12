<div class="admin-page-header">
    <div class="admin-page-header-left">
        <div class="admin-page-header-icon">
            <i class="bi bi-person-plus"></i>
        </div>
        <h1 class="admin-page-header-title">
            <div class="admin-page-header-title-main">
                用户生成
            </div>
            <div class="admin-page-header-title-right">
                <span class="en-text">
                    User Generator
                </span>
            </div>
        </h1>
    </div>
    <div class="admin-page-header-right">
        <button type="button" class="btn btn-outline-info btn-sm" data-bs-toggle="collapse" data-bs-target="#usergen_help_div" aria-expanded="false" aria-controls="navbar">
            <span class="cn-text"><i class="bi bi-question-circle me-1"></i>
            帮助</span><span class="en-text">Help</span>
        </button>
    </div>
</div>

<div class="container">
    <article id="usergen_help_div" class="alert alert-info collapse">
        <h5 class="bilingual-inline">
            文本输入模式
            <span class="en-text">Text Input Mode</span>
        </h5>
        <p>每行一个用户，信息由制表符<code>\t</code>隔开。信息从左到右依次为：</p>
        <p>User ID（比如学号）、姓名、学校（或学院/班级）、邮箱、预设密码，例如：</p>
        <p><code>202200000000\t郭大侠\t大数据与互联网学院\tcsgrandeur@qq.com\t123456</code></p>
        <p>除 User ID 外，其它信息可为空，但制表符<code>\t</code>分隔符必须有，信息是以第几个分隔符来对应的。</p>
        <p>比如 <code>202200000000\t\t测试\tqq@qq.com</code>，即会生成一个类似这样的账号：</p>
        <div class="table-responsive">
            <table class="table table-sm table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>User ID</th><th>Name</th><th>School</th><th>Email</th><th>Password</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td>202200000000</td><td>null</td><td>测试</td><td>qq@qq.com</td><td>V395JKQB</td></tr>
                </tbody>
            </table>
        </div>
        
        <h5 class="bilingual-inline">
            Excel导入模式
            <span class="en-text">Excel Import Mode</span>
        </h5>
        <p>点击"下载模板"按钮下载Excel模板，填写信息后上传即可批量导入。</p>
        <p>模板包含所有必要字段，支持中英双语表头。</p>
    </article>
    
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
                    <form id="usergen_form" method='post' action="__ADMIN__/usermanager/usergen_ajax">
                        <div class="mb-3">
                            <textarea id="user_description" class="form-control" 
                                placeholder="每行一个用户，用制表符分隔... / One user per line, separated by tabs..." 
                                rows="3" name="user_description"></textarea>
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

    <div id="usergen_toolbar" class="mb-3">
        <div class="toolbar-container">
            <div class="toolbar-left">
                <h5 id="toolbar_title">
                    <span class="cn-text"><i class="bi bi-database me-2"></i>
                    实际数据</span><span class="en-text">Actual Data</span>
                </h5>
                <small class="text-muted" id="toolbar_subtitle" style="display: none;">
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
            id="usergen_table"
            data-toggle="table"
            data-buttons-align="left"
            data-sort-name="user_id"
            data-sort-order="asc"
            data-unique-id="row_index"
            data-toolbar="#usergen_toolbar"
            data-toolbar-align="right"
            data-pagination="false"
            data-method="get"
            class="table table-striped table-hover"
            style="table-layout: fixed; word-wrap: break-word;"
        >
            <thead>
            <tr>
                <th data-field="idx" data-align="center" data-valign="middle" data-sortable="false" data-width="60" data-formatter="FormatterIndex">序号<span class="en-text">Index</span></th>
                <th data-field="user_id" data-align="center" data-valign="middle" data-sortable="true" data-width="120" data-cell-style="cellStyleUserId">用户ID<span class="en-text">User ID</span></th>
                <th data-field="nick" data-align="left" data-valign="middle" data-width="160">姓名<span class="en-text">Name</span></th>
                <th data-field="school" data-align="center" data-valign="middle" data-width="200">学校<span class="en-text">School</span></th>
                <th data-field="email" data-align="center" data-valign="middle">邮箱<span class="en-text">Email</span></th>
                <th data-field="password" data-align="center" data-valign="middle" data-width="100">密码<span class="en-text">Password</span></th>
                <th data-field="validation_errors" data-align="center" data-valign="middle" data-width="60" data-formatter="FormatterValidationErrors" data-visible="false" data-sortable="true">错误信息<span class="en-text">Validation Errors</span></th>
                <th data-field="delete" data-align="center" data-valign="middle" data-width="60" data-formatter="FormatterDel">删除<span class="en-text">Del(Dbl Click)</span></th>
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
</div>

<script type="text/javascript">
    // 序号格式化函数
    function FormatterIndex(value, row, index) {
        return index + 1;
    }
    
    // ID列样式函数 - 强制不换行
    function cellStyleUserId(value, row, index, field) {
        return {
            classes: 'text-nowrap',
            css: {
                'white-space': 'nowrap',
                'overflow': 'hidden',
                'text-overflow': 'ellipsis'
            }
        };
    }
    
    var USERGEN_CONFIG = {
        action: "usergen"
    }
    
    $(function() {
        // 初始化用户生成器
        UsergenInit();
    });
</script>
{include file="../../csgoj/view/public/js_exceljs" /}
{css href="__STATIC__/css/bilingual.css" /}
{js href="__STATIC__/js/bilingual.js" /}
{js href="__STATIC__/csgoj/contest/teamgen.js" /}

<style type="text/css">
    #usergen_table {
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
    
    
    /* 无数据状态样式 */
    .toolbar-no-data {
        background-color: rgba(108, 117, 125, 0.1) !important;
        border-left: 4px solid #6c757d !important;
        padding: 0.75rem 1rem !important;
        border-radius: 0.375rem !important;
    }
    
    .toolbar-no-data #toolbar_title {
        color: #6c757d !important;
        font-size: 1rem !important;
        font-weight: 500 !important;
    }
    
    .toolbar-no-data #toolbar_title i {
        color: #6c757d !important;
    }
</style>