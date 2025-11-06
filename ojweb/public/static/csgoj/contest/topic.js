/**
 * Topic模块统一JavaScript文件
 * 包含提问添加、详情、列表、状态变更等所有功能
 */

// 防止重复初始化
if (window.topicModuleInitialized) {
    console.warn('topic.js already initialized, skipping...');
} else {
    window.topicModuleInitialized = true;

    // 等待DOM加载完成
    document.addEventListener('DOMContentLoaded', function() {
        InitTopicModule();
    });
}

/**
 * 初始化Topic模块
 */
function InitTopicModule() {
    // 根据页面内容判断需要初始化哪些功能
    if (document.getElementById('topic_add_form')) {
        InitTopicAddForm();
    }
    
    if (document.getElementById('topic_reply_form')) {
        InitTopicDetailPage();
    }
    
    if (document.getElementById('topic_table_list_table')) {
        InitTopicListPage();
    }
    
    // 状态变更功能在所有页面都可能需要
    InitTopicChangeStatus();
}

// ==================== 提问添加功能 ====================

/**
 * 初始化提问添加表单
 */
function InitTopicAddForm() {
    const topicAddForm = document.getElementById('topic_add_form');
    if (!topicAddForm) {
        return;
    }

    // 字段配置 - 使用简化的配置，依赖默认消息
    const fieldConfigs = {
        topic_title: {
            rules: {
                required: true,
                minlength: 1,
                maxlength: 64
            }
        },
        topic_content: {
            rules: {
                required: true,
                minlength: 3,
                maxlength: 16384
            }
        }
    };

    // 提交处理函数
    function handleTopicAddSubmit(form) {
        const submitButton = document.getElementById('submit_button');
        if (!submitButton) return;

        // 禁用提交按钮防止重复提交
        submitButton.disabled = true;
        const originalText = submitButton.innerHTML;
        submitButton.innerHTML = '<span class="cn-text"><i class="bi bi-hourglass-split me-1"></i> 提交中</span><span class="en-text">Submitting</span>...';

        // 使用jQuery的ajaxSubmit方法
        $(form).ajaxSubmit(function(ret) {
            if (ret && ret.code == 1) {
                // 提交成功
                if (typeof alerty !== 'undefined') {
                    alerty.success(ret.msg || '提问提交成功', 'Topic submitted successfully');
                } else if (typeof alertify !== 'undefined') {
                    alertify.success(ret.msg || '提问提交成功');
                } else {
                    alert(ret.msg || '提问提交成功');
                }
                
                // 延迟跳转
                setTimeout(function() {
                    const config = window.topicAddConfig || {};
                    const module = config.module || 'csgoj';
                    const contest_controller = config.contest_controller || 'contest';
                    location.href = `/${module}/${contest_controller}/topic_detail?cid=${ret.data.contest_id}&topic_id=${ret.data.topic_id}`;
                }, 1000);
            } else {
                // 提交失败
                if (typeof alerty !== 'undefined') {
                    alerty.alert({
                        message: ret.msg || '提问提交失败',
                        message_en: 'Topic submission failed',
                        title: '提交失败',
                        callback: function() {
                            submitButton.disabled = false;
                            submitButton.innerHTML = originalText;
                        }
                    });
                } else if (typeof alertify !== 'undefined') {
                    alertify.alert(ret.msg || '提问提交失败');
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalText;
                } else {
                    alert(ret.msg || '提问提交失败');
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalText;
                }
            }
        });
    }

    // 初始化表单验证
    if (window.FormValidationTip && window.FormValidationTip.initFormValidation) {
        window.FormValidationTip.initFormValidation('#topic_add_form', fieldConfigs, handleTopicAddSubmit);
    } else {
        console.warn('FormValidationTip not available, falling back to basic validation');
        
        // 简单的回退验证
        topicAddForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const title = document.getElementById('title');
            const content = document.querySelector('textarea[name="topic_content"]');
            
            let isValid = true;
            
            // 基本验证
            if (!title.value.trim()) {
                ShowAlert('请输入提问标题', 'Please enter topic title');
                title.focus();
                isValid = false;
            } else if (title.value.length < 1) {
                ShowAlert('标题不能为空', 'Title cannot be empty');
                title.focus();
                isValid = false;
            } else if (title.value.length > 64) {
                ShowAlert('标题不能超过64个字符', 'Title cannot exceed 64 characters');
                title.focus();
                isValid = false;
            } else if (!content.value.trim()) {
                ShowAlert('请输入提问内容', 'Please enter topic content');
                content.focus();
                isValid = false;
            } else if (content.value.length < 3) {
                ShowAlert('内容不能少于3个字符', 'Content must be at least 3 characters');
                content.focus();
                isValid = false;
            } else if (content.value.length > 16384) {
                ShowAlert('内容不能超过16384个字符', 'Content cannot exceed 16384 characters');
                content.focus();
                isValid = false;
            }
            
            if (isValid) {
                handleTopicAddSubmit(topicAddForm);
            }
        });
    }

    // 清空按钮功能
    const clearButton = document.getElementById('clear_button');
    if (clearButton) {
        clearButton.addEventListener('click', function() {
            const title = document.getElementById('title');
            const content = document.querySelector('textarea[name="topic_content"]');
            const select = document.getElementById('topic-apid');
            
            if (title) title.value = '';
            if (content) content.value = '';
            if (select) select.value = '-1';
        });
    }

    // Tab键缩进功能
    InitTabIndentation();
}

// ==================== 提问详情功能 ====================

/**
 * 初始化提问详情页面
 */
function InitTopicDetailPage() {
    const config = window.topicDetailConfig || {};
    const cid = config.contest_id;
    const topicId = config.topic_id;
    const replyCnt = config.reply_cnt || 0;

    // 初始化状态按钮
    InitTopicStatusButton();

    // 初始化problem_id显示
    InitTopicProblemDisplay();

    // 初始化用户链接
    InitTopicUserLinks();

    // 初始化回复表单验证
    InitReplyFormValidation();

    // 初始化删除功能
    InitDeleteFunctions(cid, topicId);

    // 初始化回复按钮功能
    InitReplyButton();

    // 初始化常用回复语句功能
    InitCommonReplies();

    // 初始化hash图片加载功能
    if (typeof InitHashImages === 'function') {
        InitHashImages();
    }

    // 初始化Tab键缩进功能
    InitTabIndentation();
}

/**
 * 初始化提问状态按钮
 */
function InitTopicStatusButton() {
    const statusButtonContainer = document.getElementById('topic_status_button');
    if (!statusButtonContainer) return;

    const topicId = statusButtonContainer.getAttribute('data-topic-id');
    const status = parseInt(statusButtonContainer.getAttribute('data-status'));
    const field = statusButtonContainer.getAttribute('data-field');

    // 使用统一的按钮生成函数
    const buttonHtml = GenerateStatusButton(status, topicId);
    statusButtonContainer.innerHTML = buttonHtml;
}

/**
 * 初始化topic详情页面的problem_id显示
 */
function InitTopicProblemDisplay() {
    const config = window.topicDetailConfig || {};
    const problemId = config.problem_id;
    const pidAbc = config.pid_abc;
    
    if (!pidAbc) return;
    
    // 构建显示文本：如果有problem_id则显示括号，否则只显示字母ID
    let displayText = pidAbc;
    if (problemId && problemId !== '') {
        displayText = `${pidAbc}(${problemId})`;
    }
    
    // 更新显示元素
    const displayElement = document.getElementById('topic_problem_display');
    const displayElementEn = document.getElementById('topic_problem_display_en');
    
    if (displayElement) {
        displayElement.textContent = displayText;
    }
    if (displayElementEn) {
        displayElementEn.textContent = displayText;
    }
}

/**
 * 生成用户链接URL
 * @param {string} userId 用户ID
 * @param {string} module 模块名
 * @param {string} contestId 比赛ID
 * @returns {string} 用户链接URL
 */
function GenerateUserUrl(userId, module, contestId) {
    if (module === 'cpcsys') {
        // cpcsys模块：比赛中的账号，使用teaminfo链接
        return `/${module}/contest/teaminfo?cid=${contestId}&team_id=${userId}`;
    } else {
        // csgoj等其他模块：外部账号，使用userinfo链接
        return `/${module}/user/userinfo?user_id=${userId}`;
    }
}

/**
 * 初始化topic详情页面的用户链接
 */
function InitTopicUserLinks() {
    const config = window.topicDetailConfig || {};
    const module = config.module;
    const contestId = config.contest_id;
    const topicUserId = config.topic_user_id;
    
    // 处理topic创建者的用户链接
    const topicUserLink = document.getElementById('topic_user_link');
    if (topicUserLink && topicUserId) {
        topicUserLink.href = GenerateUserUrl(topicUserId, module, contestId);
    }
    
    // 处理回复列表中的用户链接
    const replyUserLinks = document.querySelectorAll('.reply-user-link');
    replyUserLinks.forEach(link => {
        const userId = link.getAttribute('data-user-id');
        if (userId) {
            link.href = GenerateUserUrl(userId, module, contestId);
        }
    });
}

/**
 * 初始化回复表单验证
 */
function InitReplyFormValidation() {
    const replyForm = document.getElementById('topic_reply_form');
    if (!replyForm) return;

    // 字段配置
    const fieldConfigs = {
        topic_content: {
            rules: {
                required: true,
                minlength: 2,
                maxlength: 16384
            }
        }
    };

    // 提交处理函数
    function handleReplySubmit(form) {
        const submitButton = document.getElementById('submit_button');
        if (!submitButton) return;

        // 禁用提交按钮防止重复提交
        submitButton.disabled = true;
        const originalText = submitButton.innerHTML;
        submitButton.innerHTML = '<span class="cn-text"><i class="bi bi-hourglass-split me-1"></i> 提交中</span><span class="en-text">Submitting</span>...';

        // 使用jQuery的ajaxSubmit方法
        $(form).ajaxSubmit(function(ret) {
            if (ret && ret.code == 1) {
                // 提交成功
                const config = window.topicDetailConfig || {};
                const topicId = config.topicId;
                const replyCnt = config.replyCnt || 0;
                
                // 更新本地存储
                let reply_store_key = `topic_reply#${topicId}`;
                csg.store(reply_store_key, replyCnt + 1);
                
                // 刷新页面
                location.reload();
            } else {
                // 提交失败
                if (typeof alerty !== 'undefined') {
                    alerty.alert({
                        message: ret.msg || '回复提交失败',
                        message_en: 'Reply submission failed',
                        title: '提交失败',
                        callback: function() {
                            submitButton.disabled = false;
                            submitButton.innerHTML = originalText;
                        }
                    });
                } else if (typeof alertify !== 'undefined') {
                    alertify.alert(ret.msg || '回复提交失败');
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalText;
                } else {
                    alert(ret.msg || '回复提交失败');
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalText;
                }
            }
        });
    }

    // 初始化表单验证
    if (window.FormValidationTip && window.FormValidationTip.initFormValidation) {
        window.FormValidationTip.initFormValidation('#topic_reply_form', fieldConfigs, handleReplySubmit);
    } else {
        console.warn('FormValidationTip not available, falling back to basic validation');
        
        // 简单的回退验证
        replyForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const content = document.getElementById('topic_reply_content');
            
            if (!content.value.trim()) {
                ShowAlert('请输入回复内容', 'Please enter reply content');
                content.focus();
                return;
            }
            
            if (content.value.length < 2) {
                ShowAlert('回复内容不能少于2个字符', 'Reply content must be at least 2 characters');
                content.focus();
                return;
            }
            
            if (content.value.length > 16384) {
                ShowAlert('回复内容不能超过16384个字符', 'Reply content cannot exceed 16384 characters');
                content.focus();
                return;
            }
            
            handleReplySubmit(replyForm);
        });
    }

    // 清空按钮功能
    const clearButton = document.getElementById('clear_button');
    if (clearButton) {
        clearButton.addEventListener('click', function() {
            const content = document.getElementById('topic_reply_content');
            if (content) content.value = '';
        });
    }
}

/**
 * 初始化删除功能
 */
function InitDeleteFunctions(cid, topicId) {
    // 删除提问功能
    const deleteTopicButtons = document.querySelectorAll('.delete_topic_button');
    deleteTopicButtons.forEach(button => {
        button.addEventListener('click', function() {
            const buttonElement = $(this);
            
            ShowConfirm(
                '相关回复也将被删除，确定要删除吗？',
                'Related reply will also be deleted. Sure to delete?',
                function() {
                    DeleteTopic(buttonElement, cid);
                }
            );
        });
    });

    // 删除回复功能
    const deleteReplyButtons = document.querySelectorAll('.delete_reply_button');
    deleteReplyButtons.forEach(button => {
        button.addEventListener('click', function() {
            const buttonElement = $(this);
            
            ShowConfirm(
                '确定要删除吗？',
                'Sure to delete?',
                function() {
                    DeleteReply(buttonElement, cid);
                }
            );
        });
    });
}

/**
 * 删除提问
 */
function DeleteTopic(button, cid) {
    button.attr('disabled', true);
    $.get(
        'topic_del_ajax',
        {
            'cid': cid,
            'topic_id': button.attr('topic_id')
        },
        function(ret) {
            if (ret && ret.code == 1) {
                ShowSuccess(ret.msg || '删除成功', 'Deleted successfully');
                setTimeout(function() {
                    location.href = 'topic_list?cid=' + cid;
                }, 500);
            } else {
                ShowAlert(ret.msg || '删除失败', 'Delete failed');
                button.attr('disabled', false);
            }
        },
        'json'
    );
}

/**
 * 删除回复
 */
function DeleteReply(button, cid) {
    button.attr('disabled', true);
    $.get(
        'topic_del_ajax',
        {
            'cid': cid,
            'topic_id': button.attr('topic_id')
        },
        function(ret) {
            if (ret && ret.code == 1) {
                ShowSuccess(ret.msg || '删除成功', 'Deleted successfully');
                button.parents('.reply_display_div').remove();
            } else {
                ShowAlert(ret.msg || '删除失败', 'Delete failed');
                button.attr('disabled', false);
            }
        },
        'json'
    );
}

/**
 * 初始化回复按钮功能
 */
function InitReplyButton() {
    const replyButton = document.getElementById('reply_button');
    if (replyButton) {
        replyButton.addEventListener('click', function() {
            setTimeout(function() {
                const content = document.getElementById('topic_reply_content');
                if (content) content.focus();
            }, 100);
        });
    }
}

/**
 * 初始化常用回复语句功能
 */
function InitCommonReplies() {
    const quickReplyButton = document.getElementById('quick_reply_button');
    const quickReplyModal = document.getElementById('quickReplyModal');
    const quickReplyList = document.getElementById('quick-reply-list');
    
    if (!quickReplyButton || !quickReplyModal || !quickReplyList) return;

    // ICPC常见回复语句 - 中英双语配置
    const commonReplies = [
        { value_zh: "无回复", value_en: "No response", text_zh: "无回复", text_en: "No response" },
        { value_zh: "请认真读题", value_en: "Please read the problem carefully", text_zh: "请认真读题", text_en: "Please read the problem carefully" },
        { value_zh: "请查看公告", value_en: "Please check the announcement", text_zh: "请查看公告", text_en: "Please check the announcement" },
        { value_zh: "请查看通知", value_en: "Please check the message", text_zh: "请查看通知", text_en: "Please check the message" },
        { value_zh: "是的", value_en: "Yes", text_zh: "是的", text_en: "Yes" },
        { value_zh: "不是", value_en: "No", text_zh: "不是", text_en: "No" },
        { value_zh: "存在", value_en: "Exists", text_zh: "存在", text_en: "Exists" },
        { value_zh: "不存在", value_en: "Not exist", text_zh: "不存在", text_en: "Not exist" },
        { value_zh: "请检查输入格式", value_en: "Please check input format", text_zh: "请检查输入格式", text_en: "Please check input format" },
        { value_zh: "请检查输出格式", value_en: "Please check output format", text_zh: "请检查输出格式", text_en: "Please check output format" },
        { value_zh: "注意大小写", value_en: "Pay attention to case sensitivity", text_zh: "注意大小写", text_en: "Pay attention to case sensitivity" },
        { value_zh: "注意空格", value_en: "Pay attention to spaces", text_zh: "注意空格", text_en: "Pay attention to spaces" },
        { value_zh: "注意换行", value_en: "Pay attention to line breaks", text_zh: "注意换行", text_en: "Pay attention to line breaks" },
        { value_zh: "请重新提交", value_en: "Please resubmit", text_zh: "请重新提交", text_en: "Please resubmit" },
        { value_zh: "题目描述正确", value_en: "Problem description is correct", text_zh: "题目描述正确", text_en: "Problem description is correct" },
        { value_zh: "数据范围正确", value_en: "Data range is correct", text_zh: "数据范围正确", text_en: "Data range is correct" },
        { value_zh: "请使用标准输入输出", value_en: "Please use standard input/output", text_zh: "请使用标准输入输出", text_en: "Please use standard input/output" },
        { value_zh: "请使用64位整数", value_en: "Please use 64-bit integers", text_zh: "请使用64位整数", text_en: "Please use 64-bit integers" },
        { value_zh: "时间复杂度要求正确", value_en: "Time complexity requirement is correct", text_zh: "时间复杂度要求正确", text_en: "Time complexity requirement is correct" },
        { value_zh: "请检查边界条件", value_en: "Please check boundary conditions", text_zh: "请检查边界条件", text_en: "Please check boundary conditions" },
        { value_zh: "请检查特殊情况", value_en: "Please check special cases", text_zh: "请检查特殊情况", text_en: "Please check special cases" }
    ];

    // 动态生成快捷回复选项
    quickReplyList.innerHTML = '';
    commonReplies.forEach((reply, index) => {
        const col = document.createElement('div');
        col.className = 'col-md-6 mb-2';
        
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-outline-primary w-100 text-start';
        button.style.height = 'auto';
        button.style.minHeight = '60px';
        button.style.whiteSpace = 'normal';
        
        // 生成中英双语显示文本
        button.innerHTML = `
            <div class="fw-bold">${reply.text_zh}</div>
            <div class="small text-muted">${reply.text_en}</div>
        `;
        
        // 点击事件
        button.addEventListener('click', function() {
            InsertQuickReply(reply.value_zh, reply.value_en);
            // 关闭modal
            const modal = bootstrap.Modal.getInstance(quickReplyModal);
            if (modal) {
                modal.hide();
            }
        });
        
        col.appendChild(button);
        quickReplyList.appendChild(col);
    });

    // 快捷回复按钮点击事件
    quickReplyButton.addEventListener('click', function() {
        const modal = new bootstrap.Modal(quickReplyModal);
        modal.show();
    });

    // 插入快捷回复内容
    function InsertQuickReply(zhText, enText) {
        const topicReplyContent = document.getElementById('topic_reply_content');
        if (!topicReplyContent) return;
        
        const replyText = `${zhText}<span class="en-text">${enText}</span>`;
        
        // 如果输入框已经有内容，在前面加入两个换行
        if (topicReplyContent.value.trim()) {
            topicReplyContent.value = '\n\n' + replyText + topicReplyContent.value;
        } else {
            topicReplyContent.value = replyText;
        }
        
        // 聚焦到输入框
        topicReplyContent.focus();
    }
}


/**
 * 生成状态按钮HTML
 */
function GenerateStatusButton(status, topicId) {
    let statusText, statusTextEn, btnClass, iconClass, titleText, titleTextEn;
    
    switch(status) {
        case 0:
            statusText = '私有';
            statusTextEn = 'Private';
            btnClass = 'btn-warning';
            iconClass = 'bi-lock';
            titleText = '点击更改为公开状态';
            titleTextEn = 'Click to change to public';
            break;
        case 1:
            statusText = '公开';
            statusTextEn = 'Public';
            btnClass = 'btn-success';
            iconClass = 'bi-unlock';
            titleText = '点击更改为私有状态';
            titleTextEn = 'Click to change to private';
            break;
        case -1:
            statusText = '已删除';
            statusTextEn = 'Deleted';
            btnClass = 'btn-secondary';
            iconClass = 'bi-trash';
            titleText = '已删除状态';
            titleTextEn = 'Deleted status';
            break;
        default:
            statusText = '未知';
            statusTextEn = 'Unknown';
            btnClass = 'btn-secondary';
            iconClass = 'bi-question';
            titleText = '未知状态';
            titleTextEn = 'Unknown status';
    }
    
    return `<button type='button' field='public_show' topic_id='${topicId}' class='topic_change_status btn btn-sm ${btnClass}' status='${status}' title="${titleText}(${titleTextEn})">${statusText}<span class="en-text">${statusTextEn}</span></button>`;
}
// ==================== 提问列表功能 ====================

/**
 * 初始化提问列表页面
 */
function InitTopicListPage() {
    // 获取页面元素 - 使用topic前缀避免ID冲突
    const tableListTable = $('#topic_table_list_table');

    // 使用全局配置对象 - 从PHP模板传入
    const config = window.TopicListConfig || {};
    if (!config.contest_id) {
        console.warn('TopicListConfig not found or incomplete');
        return;
    }

    // 设置内部使用的配置对象
    window.topicListConfig = {
        contest_id: config.contest_id,
        user_id: config.user_id,
        module: config.module || 'csgoj',
        contest_controller: config.contest_controller || 'contest',
        action: config.action || 'topic_list'
    };

    // 初始化客户端筛选功能
    InitTopicClientFilter();

    // 初始化"全部已读"功能
    InitMarkAllRead();

    // 键盘事件处理
    $(window).keydown(function(e) {
        if (e.keyCode == 116 && !e.ctrlKey) {
            if (window.event) {
                try {
                    e.keyCode = 0;
                } catch (e) {}
                e.returnValue = false;
            }
            e.preventDefault();
            tableListTable.bootstrapTable('refresh');
        }
    });
}

/**
 * 初始化"全部已读"功能
 */
function InitMarkAllRead() {
    const table = $('#topic_table_list_table');
    const markAllReadButton = $('#topic_mark_all_read');
    
    // 检查缓存与表格数据是否一致的函数
    window.checkMarkAllReadButton = function() {
        const tableData = table.bootstrapTable('getData');
        let hasUnread = false;
        
        // 检查每一行数据
        tableData.forEach(function(row) {
            if (row.topic_id && row.reply !== undefined) {
                const reply_store_key = `topic_reply#${row.topic_id}`;
                const reply_cache = csg.store(reply_store_key);
                
                // 如果缓存为null或与表格数据不一致，则存在未读
                if (reply_cache === null || reply_cache != row.reply) {
                    hasUnread = true;
                }
            }
        });
        
        // 根据检查结果更新按钮状态
        if (hasUnread) {
            markAllReadButton.prop('disabled', false);
        } else {
            markAllReadButton.prop('disabled', true);
        }
    };
    
    // 点击"全部已读"按钮
    markAllReadButton.on('click', function() {
        const tableData = table.bootstrapTable('getData');
        
        // 收集所有需要更新的topic
        const topicsToUpdate = [];
        tableData.forEach(function(row) {
            if (row.topic_id && row.reply !== undefined) {
                const reply_store_key = `topic_reply#${row.topic_id}`;
                const reply_cache = csg.store(reply_store_key);
                
                // 如果缓存与表格数据不一致，则需要更新
                if (reply_cache === null || reply_cache != row.reply) {
                    topicsToUpdate.push({
                        topic_id: row.topic_id,
                        reply_cnt: row.reply
                    });
                }
            }
        });
        
        // 如果没有需要更新的，直接返回
        if (topicsToUpdate.length === 0) {
            return;
        }
        
        // 使用ShowConfirm确认（内部使用alerty.confirm）
        ShowConfirm(
            `确定要将 ${topicsToUpdate.length} 个提问标记为已读吗？`,
            `Are you sure to mark ${topicsToUpdate.length} topics as read?`,
            function() {
                // 更新所有缓存
                topicsToUpdate.forEach(function(topic) {
                    const reply_store_key = `topic_reply#${topic.topic_id}`;
                    csg.store(reply_store_key, topic.reply_cnt);
                });
                
                // 刷新表格渲染（使用refreshOptions，不重新加载数据）
                const currentData = table.bootstrapTable('getData');
                table.bootstrapTable('refreshOptions', {
                    data: currentData
                });
                
                // 更新按钮状态
                markAllReadButton.prop('disabled', true);
            }
        );
    });
    
    // 初始检查按钮状态（在表格首次加载后）
    $(document).ready(function() {
        // 延迟检查，等待表格数据加载
        setTimeout(function() {
            if (window.checkMarkAllReadButton) {
                window.checkMarkAllReadButton();
            }
        }, 500);
    });
}

/**
 * 初始化提问列表的客户端筛选功能
 */
function InitTopicClientFilter() {
    const table = $('#topic_table_list_table');
    // 工具栏按钮事件处理
    $(document).ready(function() {
        // 刷新按钮
        $('#topic_refresh').click(function() {
            table.bootstrapTable('refresh');
        });
        
        // 清空筛选条件按钮
        $('#topic_clear').click(function() {
            // 清空所有输入框
            $('#topic_toolbar input.topic_filter').val('');
            // 重置所有下拉框
            $('#topic_toolbar select.topic_filter').val('-1');
            // 清空bootstrap-table的筛选
            table.bootstrapTable('filterBy', {});
        });
        
        // 筛选条件变化时应用筛选
        $('.topic_filter').on('input change', function() {
            // 输入框使用防抖处理
            if ($(this).is('input')) {
                clearTimeout(window.topicFilterTimeout);
                window.topicFilterTimeout = setTimeout(function() {
                    ApplyTopicClientFilter();
                    // 筛选后检查按钮状态
                    if (window.checkMarkAllReadButton) {
                        window.checkMarkAllReadButton();
                    }
                }, 300); // 300ms 防抖
            } else {
                // 下拉框立即应用
                ApplyTopicClientFilter();
                // 筛选后检查按钮状态
                if (window.checkMarkAllReadButton) {
                    window.checkMarkAllReadButton();
                }
            }
        });
        
        // 表格加载完成后初始化Bootstrap 5 tooltips
        table.on('post-body.bs.table', function(){
            // 表格刷新后重新初始化 tooltip
            if (window.autoTooltips) {
                window.autoTooltips.refresh();
            }
            // 检查并更新"全部已读"按钮状态
            if (window.checkMarkAllReadButton) {
                window.checkMarkAllReadButton();
            }
        });
    });
    
    // 应用客户端筛选
    function ApplyTopicClientFilter() {
        // 构建筛选数据
        let filterData = {};
        
        // 获取所有筛选条件
        $('#topic_toolbar .topic_filter').each(function() {
            const $this = $(this);
            const name = $this.attr('name');
            const value = $this.val();
            
            if (value && value !== '' && value !== '-1') {
                filterData[name] = value;
            }
        });
        
        // 应用筛选，使用自定义筛选算法
        table.bootstrapTable('filterBy', filterData, {
            filterAlgorithm: function(row, filters) {
                let match = true;
                
                // 检查filters是否存在
                if (!filters) {
                    return match;
                }
                
                // 处理topic_id筛选
                if (filters.topic_id !== undefined && filters.topic_id !== '') {
                    if (!row.topic_id.toString().includes(filters.topic_id)) {
                        match = false;
                    }
                }
                
                // 处理user_id筛选
                if (filters.user_id !== undefined && filters.user_id !== '') {
                    if (!row.user_id.toLowerCase().includes(filters.user_id.toLowerCase())) {
                        match = false;
                    }
                }
                
                // 处理title筛选
                if (filters.title !== undefined && filters.title !== '') {
                    if (!row.title.toLowerCase().includes(filters.title.toLowerCase())) {
                        match = false;
                    }
                }
                
                // 处理apid（题目ID）筛选
                if (filters.apid !== undefined && filters.apid !== '-1') {
                    if (row.pid_abc !== filters.apid) {
                        match = false;
                    }
                }
                
                return match;
            }
        });
    }
}

/**
 * 提问标题格式化器
 */
function FormatterTopicTitle(value, row, index, field) {
    return `<a class="text-decoration-none text-primary" title="${value}" href="/${window.topicListConfig.module}/contest/topic_detail?topic_id=${row.topic_id}&cid=${row.contest_id}">${value}</a>`;
}

/**
 * 题目ID格式化器
 */
function FormatterTopicProblem(value, row, index, field) {
    // 使用新的pid_abc字段
    const pidAbc = row.pid_abc;
    const problemId = row.problem_id;
    
    if (pidAbc === 'All') {
        return `<span class="text-muted">全部<span class="en-text">All</span></span>`;
    }
    
    // 构建显示文本：如果有problem_id则显示括号，否则只显示字母ID
    let displayText = pidAbc;
    if (problemId && problemId !== '') {
        displayText = `${pidAbc}(${problemId})`;
    }
    
    return `<a href="/${window.topicListConfig.module}/contest/problem?cid=${row.contest_id}&pid=${pidAbc}" class="text-decoration-none">${displayText}</a>`;
}

/**
 * 用户ID格式化器
 */
function FormatterTopicUser(value, row, index, field) {
    const module = window.topicListConfig.module;
    const contestId = window.topicListConfig.contest_id;
    
    const userUrl = GenerateUserUrl(value, module, contestId);
    return `<a href="${userUrl}" class="text-decoration-none">${value}</a>`;
}

/**
 * 提问状态格式化器
 */
function FormatterTopicStatus(value, row, index, field) {
    if (!row.is_admin) {
        return '';
    }
    
    const status = parseInt(value);
    return GenerateStatusButton(status, row.topic_id);
}

/**
 * 回复数量格式化器
 */
function FormatterTopicReply(value, row, index, field) {
    let reply_store_key = `topic_reply#${row.topic_id}`;
    let reply_cache = csg.store(reply_store_key);
    if (reply_cache === null || reply_cache != value) {
        return `<strong style="color:red;">${value}</strong>`;
    }
    return value;
}

// 将函数暴露到全局作用域
window.FormatterTopicTitle = FormatterTopicTitle;
window.FormatterTopicProblem = FormatterTopicProblem;
window.FormatterTopicUser = FormatterTopicUser;
window.FormatterTopicStatus = FormatterTopicStatus;
window.FormatterTopicReply = FormatterTopicReply;
window.GenerateStatusButton = GenerateStatusButton;

// ==================== 状态变更功能 ====================

/**
 * 初始化提问状态变更功能
 */
function InitTopicChangeStatus() {
    // 移除之前的事件监听器
    $(document).off('click', '.topic_change_status');
    
    // 添加新的事件监听器
    $(document).on('click', '.topic_change_status', function() {
        const button = $(this);
        const cid = window.topicDetailConfig?.contest_id || window.topicListConfig?.contest_id;
        
        if (!cid) {
            console.error('Contest ID not found');
            return;
        }

        $.get(
            'topic_change_status_ajax',
            {
                'cid': cid,
                'topic_id': button.attr('topic_id'),
                'status': button.attr('status') == '0' ? '1' : '0'
            },
            function(ret) {
                if (ret && ret.code == 1) {
                    const data = ret.data;
                    
                    ShowSuccess('状态更新成功', 'Status updated successfully');
                    
                    // 更新按钮状态（使用统一的按钮生成函数）
                    const newStatus = parseInt(data.status);
                    const newButtonHtml = GenerateStatusButton(newStatus, button.attr('topic_id'));
                    
                    // 更新当前按钮
                    button.replaceWith(newButtonHtml);
                    
                    // 如果是详情页面，同时更新详情页面的状态按钮
                    const detailStatusContainer = document.getElementById('topic_status_button');
                    if (detailStatusContainer) {
                        detailStatusContainer.innerHTML = newButtonHtml;
                    }
                } else {
                    ShowAlert('状态更新失败', ret.msg);
                }
            },
            'json'
        );
    });
}

// ==================== 通用工具函数 ====================

/**
 * 初始化Tab键缩进功能
 */
function InitTabIndentation() {
    const textareas = document.querySelectorAll('textarea');
    textareas.forEach(textarea => {
        textarea.addEventListener('keydown', function(e) {
            if (e.keyCode === 9) {
                e.preventDefault();
                const indent = '    ';
                const start = this.selectionStart;
                const end = this.selectionEnd;
                const selected = window.getSelection().toString();
                const newSelected = indent + selected.replace(/\n/g, '\n' + indent);
                this.value = this.value.substring(0, start) + newSelected + this.value.substring(end);
                this.setSelectionRange(start + indent.length, start + newSelected.length);
            }
        });
    });
}

/**
 * 显示警告消息
 */
function ShowAlert(message, message_en) {
    if (typeof alerty !== 'undefined') {
        alerty.alert({
            message: message,
            message_en: message_en,
            title: '提示'
        });
    } else if (typeof alertify !== 'undefined') {
        alertify.alert(message);
    } else {
        alert(message);
    }
}

/**
 * 显示成功消息
 */
function ShowSuccess(message, message_en) {
    if (typeof alerty !== 'undefined') {
        alerty.success(message, message_en);
    } else if (typeof alertify !== 'undefined') {
        alertify.success(message);
    } else {
        alert(message);
    }
}

/**
 * 显示确认对话框
 */
function ShowConfirm(message, message_en, callback) {
    alerty.confirm({
        message: message,
        message_en: message_en,
        title: '确认',
        callback: callback
    });
}
