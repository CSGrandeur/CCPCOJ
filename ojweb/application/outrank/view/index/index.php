<!-- 页面标题 -->
<div class="mb-4">
    <div class="bg-white border border-primary border-opacity-25 rounded-3 px-4 py-3 shadow-sm">
        <div class="d-flex align-items-center gap-3">
            <div class="bg-primary bg-opacity-10 rounded-3 p-3 d-flex align-items-center justify-content-center">
                <i class="bi bi-list-ol text-primary" style="font-size: 2rem;"></i>
            </div>
            <div class="flex-grow-1">
                <h1 class="mb-0 fw-bold text-dark" style="font-size: 2rem; line-height: 1.2;">
                    CCPCOJ 外榜系统
                    <span class="en-text d-block fs-6 fw-normal text-muted mt-1" style="font-size: 0.6em !important; opacity: 0.8;">
                        CCPCOJ Outrank System
                    </span>
                </h1>
            </div>
        </div>
    </div>
</div>

<div id="outrank_toolbar" class="table-toolbar">
    <div class="d-flex align-items-center gap-2" role="form">
        <button id="outrank_refresh" type="button" class="btn btn-outline-secondary toolbar-btn" title="刷新 (Refresh)">
            <i class="bi bi-arrow-clockwise"></i>
        </button>
        <button id="outrank_clear" type="button" class="btn btn-outline-secondary toolbar-btn" title="清空筛选条件 (Clear)">
            <i class="bi bi-eraser"></i>
        </button>
        <?php if(IsAdmin('administrator')): ?>
        {if IsAdmin()}
        <button type="button" class="btn btn-primary toolbar-btn" id="add_outrank_btn" title="添加外榜 (Add Outrank)">
            <span><i class="bi bi-plus-circle"></i> 添加</span> <span class="en-text">Add</span>
        </button>
        {/if}
        <?php endif; ?>
        <div class="toolbar-group">
            <span class="toolbar-label-inline"><span>搜索</span><span class="toolbar-label en-text">Search</span></span>
            <input id="outrank_search_input" name="search" class="form-control toolbar-input outrank_filter" type="text" placeholder="标题/UUID/ID" style="width: 200px;">
        </div>
    </div>
</div>

<table
        id="outrank_list_table"
        class="bootstraptable_refresh_local"
        data-toggle="table"
        data-url="/outrank/index/outrank_list_ajax"
        data-pagination="true"
        data-page-list="[25,50,100]"
        data-page-size="25"
        data-side-pagination="client"
        data-method="get"
        data-striped="true"
        data-search="false"
        data-search-align="left"
        data-sort-name="outrank_id"
        data-sort-order="desc"
        data-pagination-v-align="bottom"
        data-pagination-h-align="left"
        data-pagination-detail-h-align="right"
        data-classes="table-no-bordered table table-hover"
        data-toolbar="#outrank_toolbar"
        data-filter-control="true"
        data-filter-show-clear="true"
        data-cookie="true"
        data-cookie-id-table="outrank-list"
        data-cookie-expire="5mi"
>
    <thead>
    <tr>
        <th data-field="outrank_id" data-align="center" data-valign="middle" data-sortable="true" data-width="80" data-formatter="FormatterOutrankId">ID</th>
        <th data-field="title" data-align="left" data-valign="middle" data-sortable="false" data-formatter="FormatterOutrankTitle">标题<span class="en-text">Title</span></th>
        {if IsAdmin() }
        <th data-field="token" data-align="center" data-valign="middle" data-sortable="false" data-width="120" data-formatter="FormatterOutrankToken">Token</th>
        {/if}
        <th data-field="start_time" data-align="center" data-valign="middle" data-sortable="true" data-width="150" data-formatter="FormatterDateTimeBoth">开始<span class="en-text">Start</span></th>
        <th data-field="end_time" data-align="center" data-valign="middle" data-sortable="true" data-width="150" data-formatter="FormatterDateTimeBoth">结束<span class="en-text">End</span></th>
        <th data-field="updated_at" data-align="center" data-valign="middle" data-sortable="true" data-width="150" data-formatter="FormatterDateTimeBoth">更新<span class="en-text">Updated</span></th>
        <?php if(IsAdmin('administrator')): ?>
        <th data-field="defunct" data-align="center" data-valign="middle" data-formatter="FormatterOutrankStatus" data-width="100">状态<span class="en-text">Status</span></th>
        <th data-field="flg_allow" data-align="center" data-valign="middle" data-formatter="FormatterOutrankAllow" data-width="100">允许推送<span class="en-text">Allow Push</span></th>
        <th data-field="edit" data-align="center" data-valign="middle" data-formatter="FormatterOutrankEdit" data-width="80">操作<span class="en-text">Action</span></th>
        <?php endif; ?>
    </tr>
    </thead>
</table>

<input type="hidden" id="page_info" page_module="outrank">

<!-- Outrank Edit Modal -->
<div class="modal fade" id="outrank_edit_modal" tabindex="-1" aria-labelledby="outrankModalLabel" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="outrankModalLabel">
                    <span class="cn-text"><i class="bi bi-list-ol me-2"></i>添加外榜</span><span class="en-text">Add Outrank</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="outrank_edit_modal_body">
                {include file="../../outrank/view/index/outrank_edit_form"}
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">关闭<span class="en-text">Close</span></button>
                <button type="button" class="btn btn-primary" id="outrank_modal_submit_btn">保存<span class="en-text">Save</span></button>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
// HTML转义函数
function escapeHtml(text) {
    if (typeof text !== 'string') {
        return '';
    }
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Formatter函数
function FormatterOutrankId(value, row, index, field) {
    // 双击ID列复制UUID（通过 bootstrap-table API 处理）
    return `<span style="cursor: pointer; user-select: none;" title="双击复制UUID (Double-click to copy UUID)">${value}</span>`;
}

function FormatterOutrankTitle(value, row, index, field) {
    return `<a class="text-decoration-none text-primary" href="/outrank/rank?outrank_uuid=${row.outrank_uuid}" data-id="${row.outrank_id}" title="${value}">${value}</a>`;
}

function FormatterOutrankStatus(value, row, index, field) {
    let currentStatus = row.defunct == '0' ? "启用" : "禁用";
    let nextStatus = row.defunct == '0' ? "禁用" : "启用";
    let currentStatusEn = row.defunct == '0' ? "Enabled" : "Disabled";
    let nextStatusEn = row.defunct == '0' ? "Disabled" : "Enabled";
    
    return `
        <button type='button' field='defunct' itemid='${row.outrank_id}' 
            class='change_status btn btn-sm ${row.defunct == '0' ? "btn-success" : "btn-warning"}' 
            status='${row.defunct}' title="点击更改为${nextStatus}状态(Click to change to ${nextStatusEn})">${currentStatus}<span class='en-text'>${currentStatusEn}</span>
        </button>
    `;
}
function FormatterOutrankToken(value, row, index, field) {
    if (!value) return '';
    
    return `
        <div class="d-flex align-items-center gap-2 justify-content-center">
            <i class="bi bi-key-fill text-primary" 
               style="cursor: pointer; font-size: 1.1rem;" 
               title="单击复制Token，双击下载配置 (Click to copy token, double-click to download config)"></i>
            <span class="text-primary" style="font-family: monospace; font-size: 0.85rem;">${escapeHtml(value)}</span>
        </div>
    `;
}
function FormatterOutrankAllow(value, row, index, field) {
    if (!row.is_admin) {
        return `<span class="btn btn-sm btn-outline-secondary disabled" title="无权限 (No Permission)">
                    <i class="bi bi-lock"></i>
                </span>`;
    }
    
    let currentStatus = row.flg_allow == '1' ? "允许推送" : "禁止推送";
    let nextStatus = row.flg_allow == '1' ? "禁止推送" : "允许推送";
    let currentStatusEn = row.flg_allow == '1' ? "Allow Push" : "Disable Push";
    let nextStatusEn = row.flg_allow == '1' ? "Disable Push" : "Allow Push";
    
    return `
        <button type='button' field='flg_allow' itemid='${row.outrank_id}' 
            class='change_allow_status btn btn-sm ${row.flg_allow == '1' ? "btn-success" : "btn-danger"}' 
            status='${row.flg_allow}' title="点击更改为${nextStatus}状态(Click to change to ${nextStatusEn})">
            ${currentStatus}<span class='en-text'>${currentStatusEn}</span>
        </button>
    `;
}

function FormatterOutrankEdit(value, row, index, field) {
    if (row.is_admin) {
        return `
            <button type="button" class="btn btn-sm btn-outline-primary edit-outrank-btn" data-id="${row.outrank_id}" title="编辑 (Edit)">
                <i class="bi bi-pencil-square"></i>
            </button>
            <button type="button" class="btn btn-sm btn-outline-danger delete-outrank" data-id="${row.outrank_id}" title="删除 (Delete)">
                <i class="bi bi-trash"></i>
            </button>
        `;
    } else {
        return `<span class="btn btn-sm btn-outline-secondary disabled" title="无权限 (No Permission)">
                    <i class="bi bi-lock"></i>
                </span>`;
    }
}

// 打开编辑 modal
function openOutrankEditModal(id) {
    const modal = new bootstrap.Modal(document.getElementById('outrank_edit_modal'));
    const modalTitle = $('#outrankModalLabel');
    const submitBtn = $('#outrank_modal_submit_btn');
    
    // 设置标题
    if (id) {
        modalTitle.html('<span class="cn-text"><i class="bi bi-list-ol me-2"></i>编辑外榜</span><span class="en-text">Edit Outrank</span>');
    } else {
        modalTitle.html('<span class="cn-text"><i class="bi bi-list-ol me-2"></i>添加外榜</span><span class="en-text">Add Outrank</span>');
    }
    
    // 显示加载状态
    submitBtn.attr('disabled', true);
    
    // 清空表单
    clearOutrankForm();
    
    // 加载数据
    if (id) {
        // 编辑模式：获取数据并填充表单
        $.get('/outrank/index/outrank_get_ajax', {id: id}, function(res) {
            if (res.code == 1 || res.status == 'success') {
                const data = res.data || {};
                fillOutrankForm(data);
                submitBtn.attr('disabled', false);
                // 初始化表单（编辑模式）
                if (typeof initOutrankEditForm === 'function') {
                    initOutrankEditForm(true);
                }
            } else {
                alerty.error({
                    message: res.msg || '加载数据失败',
                    message_en: res.msg_en || 'Failed to load data'
                });
                modal.hide();
            }
        }, 'json').fail(function(xhr) {
            let errorMsg = '加载数据失败';
            if (xhr.responseJSON && xhr.responseJSON.msg) {
                errorMsg = xhr.responseJSON.msg;
            }
            alerty.error({
                message: errorMsg,
                message_en: 'Failed to load data'
            });
            modal.hide();
        });
    } else {
        // 新增模式：直接初始化表单
        submitBtn.attr('disabled', false);
        if (typeof initOutrankEditForm === 'function') {
            initOutrankEditForm(false);
        }
    }
    
    // Modal 关闭时清理表单
    $('#outrank_edit_modal').off('hidden.bs.modal').on('hidden.bs.modal', function() {
        // 清理表单验证
        const form = $('#outrank_edit_form');
        if (form.length && form.data('formValidationTip')) {
            form.data('formValidationTip').destroy();
        }
        // 清空表单内容
        clearOutrankForm();
    });
    
    modal.show();
}

// 清空表单
function clearOutrankForm() {
    $('#outrank_edit_form')[0].reset();
    // 清除隐藏字段
    $('input[name="outrank_id"]').remove();
    // 隐藏 UUID 字段组
    $('#outrank_uuid_group').hide();
    // 清除验证状态
    $('#outrank_edit_form .is-invalid, #outrank_edit_form .is-valid').removeClass('is-invalid is-valid');
    $('#outrank_edit_form .invalid-feedback').remove();
}

// 填充表单数据
function fillOutrankForm(data) {
    if (!data) return;
    
    // 填充基本字段
    if (data.outrank_id) {
        // 添加隐藏字段
        if ($('input[name="outrank_id"]').length === 0) {
            $('#outrank_edit_form').prepend(`<input type="hidden" name="outrank_id" value="${data.outrank_id}">`);
        } else {
            $('input[name="outrank_id"]').val(data.outrank_id);
        }
    }
    
    if (data.title !== undefined) $('#title').val(data.title || '');
    if (data.outrank_uuid !== undefined) {
        $('#outrank_uuid').val(data.outrank_uuid || '');
        // 显示 UUID 字段组
        $('#outrank_uuid_group').show();
    }
    if (data.token !== undefined) $('#token').val(data.token || '');
    if (data.ckind !== undefined) $('#ckind').val(data.ckind || '');
    if (data.description !== undefined) $('#description').val(data.description || '');
    
    // 填充时间字段（数据库返回的是 datetime 字符串格式，如 '2024-01-01 12:00:00'）
    if (data.start_time) {
        // 将 datetime 字符串转换为 datetime-local 格式 (YYYY-MM-DDTHH:mm)
        const startTimeStr = data.start_time.replace(' ', 'T').slice(0, 16);
        $('#start_time').val(startTimeStr);
    }
    if (data.end_time) {
        // 将 datetime 字符串转换为 datetime-local 格式 (YYYY-MM-DDTHH:mm)
        const endTimeStr = data.end_time.replace(' ', 'T').slice(0, 16);
        $('#end_time').val(endTimeStr);
    }
}

// 初始化
$(document).ready(function() {
    // 初始化工具栏筛选
    initBootstrapTableClientToolbar({
        tableId: 'outrank_list_table',
        prefix: 'outrank',
        filterSelectors: [],
        searchInputId: 'outrank_search_input',
        searchFields: {
            title: 'title',
            outrank_id: 'outrank_id',
            outrank_uuid: 'outrank_uuid'
        }
    });

    // 添加按钮事件
    $('#add_outrank_btn').on('click', function() {
        openOutrankEditModal(null);
    });

    // 编辑按钮事件
    $(document).on('click', '.edit-outrank-btn', function() {
        const id = $(this).data('id');
        openOutrankEditModal(id);
    });

    // 使用 bootstrap-table 的 API 处理单元格点击事件
    const table = $('#outrank_list_table');
    
    // 绑定单元格点击事件的函数
    function bindCellEvents() {
        // 防止重复绑定
        table.off('click-cell.bs.table');
        table.off('dbl-click-cell.bs.table');
        
        // 单击事件处理
        table.on('click-cell.bs.table', async function(e, field, td, row) {
            if (field === 'token') {
                // 单击Token列：复制Token
                const token = row.token;
                if (!token) {
                    alerty.error({
                        message: 'Token不存在',
                        message_en: 'Token not found'
                    });
                    return;
                }
                
                // 使用 global.js 中的 ClipboardWrite 函数
                if (typeof ClipboardWrite === 'function') {
                    const success = await ClipboardWrite(token);
                    if (success) {
                        alerty.success({
                            message: 'Token已复制到剪贴板',
                            message_en: 'Token copied to clipboard'
                        });
                    } else {
                        alerty.error({
                            message: '复制失败，请手动复制',
                            message_en: 'Copy failed, please copy manually'
                        });
                    }
                } else {
                    // 备用方法
                    const textArea = document.createElement('textarea');
                    textArea.value = token;
                    textArea.style.position = 'fixed';
                    textArea.style.opacity = '0';
                    document.body.appendChild(textArea);
                    textArea.select();
                    try {
                        document.execCommand('copy');
                        alerty.success({
                            message: 'Token已复制到剪贴板',
                            message_en: 'Token copied to clipboard'
                        });
                    } catch (err) {
                        alerty.error({
                            message: '复制失败，请手动复制',
                            message_en: 'Copy failed, please copy manually'
                        });
                    }
                    document.body.removeChild(textArea);
                }
            } 

        });
        
        // 双击事件处理
        table.on('dbl-click-cell.bs.table', function(e, field, td, row) {
            if (field === 'token') {
                // 双击Token列：下载配置
                const token = row.token;
                const uuid = row.outrank_uuid;
                
                if (!token || !uuid) {
                    alerty.error({
                        message: '配置信息不完整',
                        message_en: 'Configuration information incomplete'
                    });
                    return;
                }
                
                // 获取当前页面的基础URL
                const baseUrl = window.location.origin;
                const apiUrl = `${baseUrl}/outrank/index/receive_data`;
                
                // 生成配置JSON
                const config = {
                    api_url: apiUrl,
                    outrank_uuid: uuid,
                    outrank_token: token,
                    push_method: 'post',
                    use_zip: true,
                    use_gzip: true, // 兼容旧版本
                    push_real_rank: false,
                    jsonp_chunk_size: 'auto', // JSONP分包大小
                    version: '1.0',
                    export_time: new Date().toISOString()
                };
                
                // 下载JSON文件
                const jsonStr = JSON.stringify(config, null, 2);
                const blob = new Blob([jsonStr], { type: 'application/json' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `outrank_config_${uuid.substring(0, 8)}_${Date.now()}.json`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
                
                alerty.success({
                    message: '配置已下载',
                    message_en: 'Configuration downloaded'
                });
            } else if (field === 'outrank_id') {
                // 双击ID列：复制UUID
                const uuid = row.outrank_uuid;
                if (!uuid) {
                    alerty.error({
                        message: 'UUID不存在',
                        message_en: 'UUID not found'
                    });
                    return;
                }
                
                // 使用 global.js 中的 ClipboardWrite 函数
                if (typeof ClipboardWrite === 'function') {
                    ClipboardWrite(uuid).then(success => {
                        if (success) {
                            alerty.success({
                                message: 'UUID已复制到剪贴板',
                                message_en: 'UUID copied to clipboard'
                            });
                        } else {
                            alerty.error({
                                message: '复制失败，请手动复制',
                                message_en: 'Copy failed, please copy manually'
                            });
                        }
                    });
                } else {
                    // 备用方法
                    const textArea = document.createElement('textarea');
                    textArea.value = uuid;
                    textArea.style.position = 'fixed';
                    textArea.style.opacity = '0';
                    document.body.appendChild(textArea);
                    textArea.select();
                    try {
                        document.execCommand('copy');
                        alerty.success({
                            message: 'UUID已复制到剪贴板',
                            message_en: 'UUID copied to clipboard'
                        });
                    } catch (err) {
                        alerty.error({
                            message: '复制失败，请手动复制',
                            message_en: 'Copy failed, please copy manually'
                        });
                    }
                    document.body.removeChild(textArea);
                }
            }
        });
    }
    
    // 初始化时绑定事件
    bindCellEvents();
    
    // 表格刷新后重新绑定事件
    table.on('refresh.bs.table', function() {
        bindCellEvents();
    });
    
    // 表格加载完成后重新绑定事件
    table.on('load-success.bs.table', function() {
        bindCellEvents();
    });

    // 删除按钮事件
    $(document).on('click', '.delete-outrank', function() {
        let id = $(this).data('id');
        if (confirm('确定要删除此外榜吗？(Are you sure to delete this outrank?)')) {
            $.post('/outrank/index/outrank_delete_ajax', {outrank_id: id}, function(res) {
                if (res.code == 1 || res.status == 'success') {
                    $('#outrank_list_table').bootstrapTable('refresh');
                    alerty.success({
                        message: '删除成功',
                        message_en: 'Deleted successfully'
                    });
                } else {
                    alerty.error({
                        message: res.msg || '删除失败',
                        message_en: res.msg || 'Delete failed'
                    });
                }
            }, 'json').fail(function(xhr) {
                let errorMsg = '删除失败';
                if (xhr.responseJSON && xhr.responseJSON.msg) {
                    errorMsg = xhr.responseJSON.msg;
                }
                alerty.error({
                    message: errorMsg,
                    message_en: errorMsg
                });
            });
        }
    });

    // 状态切换（defunct）- 使用专用接口
    $(document).on('click', '.change_status', function() {
        let id = $(this).attr('itemid');
        let btn = $(this);
        
        // 禁用按钮，防止重复点击
        btn.prop('disabled', true);
        
        $.post('/outrank/index/outrank_toggle_status_ajax', {
            outrank_id: id
        }, function(res) {
            if (res.code == 1 || res.status == 'success') {
                $('#outrank_list_table').bootstrapTable('refresh');
                alerty.success({
                    message: '状态已更新',
                    message_en: 'Status updated'
                });
            } else {
                alerty.error({
                    message: res.msg || '操作失败',
                    message_en: res.msg || 'Operation failed'
                });
                btn.prop('disabled', false);
            }
        }, 'json').fail(function(xhr) {
            let errorMsg = '操作失败';
            if (xhr.responseJSON && xhr.responseJSON.msg) {
                errorMsg = xhr.responseJSON.msg;
            }
            alerty.error({
                message: errorMsg,
                message_en: errorMsg
            });
            btn.prop('disabled', false);
        });
    });

    // 允许推送状态切换（flg_allow）- 使用专用接口
    $(document).on('click', '.change_allow_status', function() {
        let id = $(this).attr('itemid');
        let currentStatus = $(this).attr('status');
        let statusText = currentStatus == '1' ? '禁止推送' : '允许推送';
        let statusTextEn = currentStatus == '1' ? 'Disable Push' : 'Allow Push';
        let btn = $(this);
        
        if (!confirm(`确定要更改为${statusText}吗？(Are you sure to change to ${statusTextEn}?)`)) {
            return;
        }
        
        // 禁用按钮，防止重复点击
        btn.prop('disabled', true);
        
        $.post('/outrank/index/outrank_toggle_allow_ajax', {
            outrank_id: id
        }, function(res) {
            if (res.code == 1 || res.status == 'success') {
                $('#outrank_list_table').bootstrapTable('refresh');
                alerty.success({
                    message: '推送状态已更新',
                    message_en: 'Push status updated'
                });
            } else {
                alerty.error({
                    message: res.msg || '操作失败',
                    message_en: res.msg || 'Operation failed'
                });
                btn.prop('disabled', false);
            }
        }, 'json').fail(function(xhr) {
            let errorMsg = '操作失败';
            if (xhr.responseJSON && xhr.responseJSON.msg) {
                errorMsg = xhr.responseJSON.msg;
            }
            alerty.error({
                message: errorMsg,
                message_en: errorMsg
            });
            btn.prop('disabled', false);
        });
    });

    // F5刷新处理
    $(window).keydown(function(e) {
        if (e.keyCode == 116 && !e.ctrlKey) {
            if(window.event){
                try{e.keyCode = 0;}catch(e){}
                e.returnValue = false;
            }
            e.preventDefault();
            $('#outrank_list_table').bootstrapTable('refresh');
        }
    });
});
</script>

