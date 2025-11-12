<!-- 公告栏模板 - 响应式设计 -->
<!-- 大屏幕：侧边栏显示 -->
<div class="d-none d-lg-block col-lg-4">
    <div class="card border-primary shadow-sm">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center py-3">
            <div class="d-flex align-items-center">
                <i class="bi bi-megaphone me-2"></i>
                <h3 class="card-title mb-0 ">公告<span class="en-text">Announcement</span></h3>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-light" data-bs-toggle="modal" data-bs-target="#announcement_modal">
                    <span class="cn-text"><i class="bi bi-eye me-1"></i>
                    打开</span><span class="en-text">Open</span>
                </button>
                {if $isContestAdmin || isset($proctorAdmin) && $proctorAdmin }
                    <button class="btn btn-sm btn-outline-light" data-bs-toggle="modal" data-bs-target="#announcement_edit_modal">
                        <span class="cn-text"><i class="bi bi-pencil-square me-1"></i>
                        修改</span><span class="en-text">Change</span>
                    </button>
                {/if}
            </div>
        </div>
        <div class="card-body p-3" style="min-height:400px; max-height:450px; overflow-y: auto; background-color: #f8f9fa;">
            <article class="md_display_div" id="contest_notification_div">
            {$contest['description']}
            </article>
        </div>
    </div>
</div>

<!-- 小屏幕：悬浮按钮 -->
<div class="d-lg-none">
    <button class="btn btn-primary position-fixed" id="announcement_float_btn" 
            data-bs-toggle="modal" data-bs-target="#announcement_modal"
            style="bottom: 20px; right: 20px; z-index: 1000; border-radius: 50%; width: 60px; height: 60px; box-shadow: 0 4px 12px rgba(0,0,0,0.3);">
        <i class="bi bi-megaphone fs-4"></i>
    </button>
</div>

<!-- 公告Modal -->
<div class="modal fade" id="announcement_modal" tabindex="-1" aria-labelledby="announcement_modal_label" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title bilingual-inline" id="announcement_modal_label">
                    <span class="cn-text"><i class="bi bi-megaphone me-2"></i>
                    公告</span><span class="en-text">Announcement</span>
                </h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <article class="md_display_div" id="announcement_modal_content">
                    {$contest['description']}
                    </article>
                </div>
                {if $isContestAdmin || isset($proctorAdmin) && $proctorAdmin }
                <div class="border-top pt-3">
                    <h4 class="bilingual-inline">编辑公告<span class="en-text">Edit Announcement</span></h4>
                    <textarea class="form-control mt-2" rows="10" id="announcement_edit_text" placeholder="请输入公告内容..."></textarea>
                </div>
                {/if}
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    关闭<span class="en-text">Close</span>
                </button>
                {if $isContestAdmin || isset($proctorAdmin) && $proctorAdmin }
                <button type="button" class="btn btn-primary" id="announcement_save_btn">
                    保存<span class="en-text">Save</span>
                </button>
                {/if}
            </div>
        </div>
    </div>
</div>

<!-- 编辑公告Modal -->
{if $isContestAdmin || isset($proctorAdmin) && $proctorAdmin }
<div class="modal fade" id="announcement_edit_modal" tabindex="-1" aria-labelledby="announcement_edit_modal_label" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title bilingual-inline" id="announcement_edit_modal_label">
                    <span class="cn-text"><i class="bi bi-pencil-square me-2"></i>
                    编辑公告</span><span class="en-text">Edit Announcement</span>
                </h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="announcement_info" cid="{$contest['contest_id']}">
                <textarea class="form-control" rows="20" id="announcement_edit_textarea"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    取消<span class="en-text">Cancel</span>
                </button>
                <button type="button" class="btn btn-primary" id="announcement_edit_save_btn">
                    保存更改<span class="en-text">Save Changes</span>
                </button>
            </div>
        </div>
    </div>
</div>
{/if}

<script type="text/javascript">
$(document).ready(function() {
    var announcement_info = $('#announcement_info');
    var announcement_modal = $('#announcement_modal');
    var announcement_edit_modal = $('#announcement_edit_modal');
    var announcement_edit_textarea = $('#announcement_edit_textarea');
    var announcement_save_btn = $('#announcement_save_btn');
    var announcement_edit_save_btn = $('#announcement_edit_save_btn');
    var contest_notification_div = $('#contest_notification_div');
    var announcement_modal_content = $('#announcement_modal_content');
    
    // 悬浮按钮点击事件
    $('#announcement_float_btn').on('click', function() {
        // 更新Modal内容
        announcement_modal_content.html(contest_notification_div.html());
    });
    
    // 编辑Modal显示时加载内容
    announcement_edit_modal.on('show.bs.modal', function(e) {
        $.get('description_md_ajax', {
            'cid': announcement_info.attr('cid')
        }, function(ret) {
            if (ret['code'] == 1) {
                announcement_edit_textarea.val(ret['data']);
            }
        });
    });
    
    // 保存按钮点击事件
    announcement_edit_save_btn.on('click', function() {
        var notification = $.trim(announcement_edit_textarea.val());
        if (notification.length == 0) {
            alertify.confirm(
                '公告内容已清空，确定要提交吗？<span class="en-text">Announcement is cleared. Are you sure to submit?</span>',
                function() {
                    changeNotification(notification);
                },
                function() {
                    alertify.message('已取消<span class="en-text">Canceled</span>');
                }
            );
        } else if (notification.length < 16384) {
            changeNotification(notification);
        } else {
            alertify.error('公告内容过长<span class="en-text">Announcement too long</span>');
        }
    });
    
    // 修改公告函数
    function changeNotification(notification) {
        announcement_edit_save_btn.attr('disabled', 'disabled');
        announcement_edit_save_btn.html('保存中<span class="en-text">Saving</span>...');
        
        $.post('description_change_ajax', {
            'cid': announcement_info.attr('cid'),
            'description_md': notification
        }, function(ret) {
            if (ret.code == 1) {
                alertify.success(ret.msg);
                contest_notification_div.html(ret.data);
                announcement_modal_content.html(ret.data);
                announcement_edit_modal.modal('hide');
                button_delay(announcement_edit_save_btn, 3, '保存更改', '保存更改', 'Save Changes');
            } else {
                alertify.error(ret['msg']);
                button_delay(announcement_edit_save_btn, 3, '保存更改', '保存更改', 'Save Changes');
            }
        });
    }
    
    // 快捷键支持
    announcement_edit_textarea.on('keydown', function(e) {
        if (e.ctrlKey && e.keyCode == 13) {
            e.preventDefault();
            announcement_edit_save_btn.trigger('click');
        }
    });
});
</script>
