<?php $edit_mode = isset($outrank) && $outrank; ?>
<script type="text/javascript" src="__STATIC__/js/form_validate_tip.js"></script>

<form id="outrank_edit_form" method='post' action="__OUTRANK__/index/outrank_addedit_ajax">
    {if $edit_mode }
        <input type="hidden" value="{$outrank['outrank_id']}" name="outrank_id">
    {/if}

    <div class="form-group mb-3">
        <label for="title" class="bilingual-label">外榜标题：<span class="en-text">Outrank Title</span></label>
        <input type="text" class="form-control" placeholder="Outrank Title..." name="title" id="title" {if $edit_mode}value="{$outrank['title']|htmlspecialchars}"{/if}>
    </div>

    {if $edit_mode }
    <div class="form-group mb-3">
        <label for="outrank_uuid" class="bilingual-label">UUID：<span class="en-text">UUID</span></label>
        <input type="text" class="form-control" id="outrank_uuid" name="outrank_uuid" value="{$outrank['outrank_uuid']|htmlspecialchars}" readonly>
        <small class="form-text text-muted">自动生成 (Auto-generated)</small>
    </div>
    {/if}

    <div class="form-group mb-3">
        <label for="token" class="bilingual-label">Token：<span class="en-text">Token</span></label>
        <div class="input-group">
            <input type="text" class="form-control" id="token" name="token" placeholder="Token for validating push data..." value="{if $edit_mode}{$outrank['token']|htmlspecialchars}{/if}">
            <button type="button" class="btn btn-outline-secondary" id="generate_token_btn" title="生成Token (Generate Token)">
                <span><i class="bi bi-arrow-clockwise"></i> 生成</span> <span class="en-text">Generate</span>
            </button>
        </div>
        <small class="form-text text-muted">用于验证推送数据的token，留空将自动生成 (Token for validating push data, auto-generated if empty)</small>
    </div>

    <div class="form-group mb-3">
        <label for="ckind" class="bilingual-label">类型：<span class="en-text">Kind</span></label>
        <input type="text" class="form-control" placeholder="Kind..." name="ckind" id="ckind" {if $edit_mode}value="{$outrank['ckind']|htmlspecialchars}"{/if}>
    </div>

    <div class="form-group mb-3">
        <label for="description" class="bilingual-label">描述：<span class="en-text">Description</span></label>
        <textarea class="form-control" placeholder="Description..." rows="3" name="description" id="description">{if $edit_mode}{$outrank['description']|htmlspecialchars}{/if}</textarea>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="form-group mb-3">
                <label for="start_time" class="bilingual-label">开始时间：<span class="en-text">Start Time</span></label>
                <input type="datetime-local" class="form-control" id="start_time" name="start_time" value="<?php echo isset($outrank) && $outrank['start_time'] ? date('Y-m-d\TH:i', strtotime($outrank['start_time'])) : ''; ?>">
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group mb-3">
                <label for="end_time" class="bilingual-label">结束时间：<span class="en-text">End Time</span></label>
                <input type="datetime-local" class="form-control" id="end_time" name="end_time" value="<?php echo isset($outrank) && $outrank['end_time'] ? date('Y-m-d\TH:i', strtotime($outrank['end_time'])) : ''; ?>">
            </div>
        </div>
    </div>
</form>

<script type="text/javascript">
// 生成随机Token
function generateToken() {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    let token = '';
    for (let i = 0; i < 32; i++) {
        token += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return token;
}

// 生成下一天的日期时间
function getTomorrowDateTime(hour, minute) {
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    tomorrow.setHours(hour, minute, 0, 0);
    
    const year = tomorrow.getFullYear();
    const month = String(tomorrow.getMonth() + 1).padStart(2, '0');
    const day = String(tomorrow.getDate()).padStart(2, '0');
    const hours = String(tomorrow.getHours()).padStart(2, '0');
    const minutes = String(tomorrow.getMinutes()).padStart(2, '0');
    
    return `${year}-${month}-${day}T${hours}:${minutes}`;
}

// 初始化表单（在 modal 显示时调用）
function initOutrankEditForm(editMode) {
    // 如果是新增模式，自动填充时间和token
    if (!editMode) {
        // 自动填充开始和结束时间（下一天的9:00~14:00）
        $('#start_time').val(getTomorrowDateTime(9, 0));
        $('#end_time').val(getTomorrowDateTime(14, 0));
        
        // 如果token为空，自动生成
        if (!$('#token').val()) {
            $('#token').val(generateToken());
        }
    }

    // Token生成按钮
    $('#generate_token_btn').off('click').on('click', function() {
        $('#token').val(generateToken());
        alerty.success({
            message: 'Token已生成',
            message_en: 'Token generated'
        });
    });

    // 表单验证
    if (typeof window.FormValidationTip === 'undefined') {
        console.error('FormValidationTip not loaded');
        return;
    }

    // 清除之前的验证
    if ($('#outrank_edit_form').data('formValidationTip')) {
        $('#outrank_edit_form').data('formValidationTip').destroy();
    }

    window.FormValidationTip.initCommonFormValidation('#outrank_edit_form', {
        title: {
            rules: { required: true, maxlength: 255 },
            messages: {
                required: window.FormValidationTip.createBilingualMessage("外榜标题不能为空", "Outrank title is required"),
                maxlength: window.FormValidationTip.createBilingualMessage("外榜标题不能超过255个字符", "Outrank title cannot exceed 255 characters")
            }
        },
        token: {
            rules: { required: true, minlength: 8, maxlength: 255 },
            messages: {
                required: window.FormValidationTip.createBilingualMessage("Token不能为空", "Token is required"),
                minlength: window.FormValidationTip.createBilingualMessage("Token长度不能少于8个字符", "Token must be at least 8 characters"),
                maxlength: window.FormValidationTip.createBilingualMessage("Token不能超过255个字符", "Token cannot exceed 255 characters")
            }
        },
        ckind: {
            rules: { maxlength: 32 },
            messages: {
                maxlength: window.FormValidationTip.createBilingualMessage("类型不能超过32个字符", "Kind cannot exceed 32 characters")
            }
        },
        description: {
            rules: { maxlength: 65536 },
            messages: {
                maxlength: window.FormValidationTip.createBilingualMessage("描述不能超过65536个字符", "Description cannot exceed 65536 characters")
            }
        }
    }, function(form) {
        // 提交处理函数（验证通过后执行）
        const submitButton = $('#outrank_modal_submit_btn');
        submitButton.attr('disabled', true);
        const originalText = submitButton.html();
        submitButton.html('Waiting...');
        
        // 如果token为空，自动生成
        const tokenInput = $('#token');
        if (!tokenInput.val() || tokenInput.val().trim() === '') {
            tokenInput.val(generateToken());
        }

        $(form).ajaxSubmit({
            success: function(ret) {
                if(ret['code'] == 1 || ret['status'] == 'success') {
                    const message = ret['msg'] || ret['message'] || '操作成功';
                    alerty.success({
                        message: message,
                        message_en: ret['msg_en'] || 'Operation successful'
                    });
                    
                    // 关闭 modal 并刷新表格
                    const modalElement = document.getElementById('outrank_edit_modal');
                    if (modalElement) {
                        const modal = bootstrap.Modal.getInstance(modalElement);
                        if (modal) {
                            modal.hide();
                        } else {
                            $(modalElement).modal('hide');
                        }
                    }
                    $('#outrank_list_table').bootstrapTable('refresh');
                } else {
                    const message = ret['msg'] || ret['message'] || '操作失败';
                    alerty.error({
                        message: message,
                        message_en: ret['msg_en'] || 'Operation failed'
                    });
                    submitButton.attr('disabled', false);
                    submitButton.html(originalText);
                }
                return false;
            },
            error: function(xhr, status, error) {
                alerty.error({
                    message: '请求失败：' + error,
                    message_en: 'Request failed: ' + error
                });
                submitButton.attr('disabled', false);
                submitButton.html(originalText);
            }
        });
        return false;
    });
    
    // 绑定 modal 提交按钮（在 form 外面）
    // 注意：需要在表单验证初始化之后绑定，这样点击按钮时会触发表单的 submit 事件
    // initCommonFormValidation 会拦截 submit 事件进行验证，验证通过后执行上面的回调函数
    $('#outrank_modal_submit_btn').off('click').on('click', function() {
        // 手动触发表单的 submit 事件，让 initCommonFormValidation 进行验证
        $('#outrank_edit_form').submit();
    });
}
</script>

