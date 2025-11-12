<div class="admin-page-header">
    <div class="admin-page-header-left">
        <div class="admin-page-header-icon">
            <i class="bi bi-gear-fill"></i>
        </div>
        <h1 class="admin-page-header-title">
            <div class="admin-page-header-title-main">
                评测机管理
            </div>
            <div class="admin-page-header-title-right">
                <span class="en-text">
                    Judger Management
                </span>
            </div>
        </h1>
    </div>
</div>

<div class="container-fluid">
    <div class="row g-3">
        <!-- 左侧：评测机列表 -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">评测机列表<span class="en-text text-muted fs-6">Judger List</span></h5>
                    <div class="d-flex gap-2 align-items-center">
                        <button id="batch_reset_password_btn" type="button" class="btn btn-outline-danger btn-sm" title="批量重置密码 / Batch Reset Password">
                            <i class="bi bi-key-fill"></i>
                        </button>
                        <div class="input-group" style="width: 140px;">
                            <input id="judger_password_input" class="form-control form-control-sm" type="password" placeholder="密码 Password" maxlength="30" title="自定义评测机密码，6-30位数字字母，为空则自动生成 (Custom Judger Password, 6-30 alphanumeric characters, auto-generated if empty)">
                            <button class="btn btn-outline-secondary btn-sm" type="button" id="toggle_password_btn" title="显示/隐藏密码 (Show/Hide Password)">
                                <i class="bi bi-eye-slash" id="password_icon"></i>
                            </button>
                        </div>
                        <input id="judger_prefix_input" class="form-control form-control-sm" type="text" placeholder="前缀 Prefix" maxlength="2" style="width: 60px;" title="自定义评测机前缀，至多 2 位小写字母，默认前缀为 j (Custom Judger Prefix, up to 2 lowercase letters, default prefix is j)">
                        <button id="add_judger_btn" type="button" class="btn btn-outline-success btn-sm" title="添加评测机 (Add Judger)">
                            <i class="bi bi-plus-circle"></i>
                        </button>
                        <small class="text-muted" id="judger_count_display">0/99</small>
                    </div>
                </div>
                <div class="card-body p-0">
                    {include file="../../admin/view/judger/judger_list" /}
                </div>
            </div>
        </div>
        
        <!-- 右侧：评测机配置 -->
        <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <h5 class="mb-0 me-2">评测参数配置<span class="en-text text-muted fs-6">Judger Parameter Configuration</span></h5>
                                <i id="header_modification_icon" class="bi bi-exclamation-triangle text-warning" style="display: none;" 
                                   title="配置已修改，请保存 / Configuration modified, please save"></i>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-info btn-sm" id="export_config" title="下载配置 / Export Config">
                                    <i class="bi bi-download"></i>
                                </button>
                                <button type="button" class="btn btn-outline-warning btn-sm" id="import_config" title="上传配置 / Import Config">
                                    <i class="bi bi-upload"></i>
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="get_default_config" title="获取默认配置 / Get Default Config">
                                    <i class="bi bi-arrow-clockwise"></i>
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-sm" id="submit_config" title="保存配置 / Save Config">
                                    <i class="bi bi-check-lg"></i>
                                </button>
                            </div>
                        </div>
                <div class="card-body">
                    <form id="judger_config_form">
                        <!-- 配置数据存储 -->
                        <textarea id="judger_config_data" style="display: none;">{$judgerConfig|json_encode}</textarea>
                        
                        <!-- 隐藏的文件输入框用于上传 -->
                        <input type="file" id="import_file_input" accept=".json" style="display: none;">
                        
                        <!-- 动态生成的配置表单 -->
                        <div id="config_form_container">
                            <!-- 表单将由JavaScript动态生成 -->
                        </div>
                        
                                <!-- 保存按钮 -->
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <small class="text-muted">
                                            <span class="cn-text"><i class="bi bi-keyboard me-1"></i>
                                            快捷键：Ctrl+S 保存配置
                                            </span><span class="en-text">Shortcut: Ctrl+S to save</span>
                                        </small>
                                        <div id="modification_status" class="text-warning small mt-1" style="display: none;">
                                            <i class="bi bi-exclamation-triangle me-1"></i>
                                            <span id="modification_count">0</span> 处配置已修改，请保存
                                            <span class="en-text">configurations modified, please save</span>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <span class="cn-text"><i class="bi bi-save me-1"></i>
                                        保存配置</span><span class="en-text">Save</span>
                                    </button>
                                </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 引入组件 -->
{include file="../../csgoj/view/public/base_csg_switch" /}
{include file="../../csgoj/view/public/base_select" /}


{js href="__STATIC__/csgoj/admin/judge_manage.js" /}
{css href="__STATIC__/csgoj/admin/judge_manage.css" /}