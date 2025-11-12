/**
 * 文章模块 JavaScript
 * Article Module JavaScript
 */

// 防重复执行检查
if (window.NewsModuleDetail && window.NewsModuleDetail._initialized) {
    console.warn('NewsModuleDetail already initialized, skipping duplicate initialization');
} else {
    // 标记为已初始化
    if (window.NewsModuleDetail) {
        window.NewsModuleDetail._initialized = true;
    }

window.NewsModuleDetail = {
    // 初始化文章详情页
    init: function() {
        // 检查是否已经初始化过
        if (this._initExecuted) {
            console.warn('NewsModuleDetail.init() already executed, skipping');
            return;
        }
        this._initExecuted = true;

        this.initNewsDetail();
        this.initNewsList();
    },

    // 初始化文章详情页
    initNewsDetail: function() {
        
        // 初始化文章编辑页面
        this.initNewsEdit();
    },

    // 初始化文章编辑页面
    initNewsEdit: function() {
        const pageInfo = $('#page_info');
        const editMode = pageInfo.attr('edit_mode');
        const submitButton = $('#submit_button');
        
        if (submitButton.length && pageInfo.length) {
            // 使用简化的表单验证工具
            if (typeof window.FormValidationTip !== 'undefined') {
                window.FormValidationTip.initCommonFormValidation('#news_edit_form', {
                    title: {
                        rules: { required: true, maxlength: 200 },
                        messages: {
                            required: window.FormValidationTip.createBilingualMessage("文章标题不能为空", "Article title is required"),
                            maxlength: window.FormValidationTip.createBilingualMessage("文章标题不能超过200个字符", "Article title cannot exceed 200 characters")
                        }
                    },
                    content: {
                        rules: { required: true, maxlength: 65536 },
                        messages: {
                            required: window.FormValidationTip.createBilingualMessage("内容不能为空", "Content is required"),
                            maxlength: window.FormValidationTip.createBilingualMessage("内容不能超过65536个字符", "Content cannot exceed 65536 characters")
                        }
                    }
                }, function(form) {
                    // 提交处理函数
                    // 使用 button_delay_auto 的 before 状态：禁用按钮，显示提示，但不倒计时
                    button_delay_auto(submitButton, 3, 'before');
                    
                    $(form).ajaxSubmit({
                        success: function(ret) {
                            if(ret['code'] == 1) {
                                if(typeof(ret['data']['alert']) != 'undefined' && ret['data']['alert'] == true){
                                    alerty.alert(ret['msg']);
                                }else{
                                    alerty.success(ret['msg']);
                                }
                                // 使用 button_delay_auto 的 start 状态：开始倒计时
                                button_delay_auto(submitButton, 3, 'start');
                                if(editMode != '1') {
                                    setTimeout(function(){location.href='news_edit?id='+ret['data']['id']}, 500);
                                }
                            }
                            else {
                                alerty.alert(ret['msg']);
                                // 使用 button_delay_auto 的 start 状态：开始倒计时
                                button_delay_auto(submitButton, 3, 'start');
                            }
                            return false;
                        }
                    });
                    return false;
                });
            }
            
            // Ctrl+S 快捷键保存
            $(window).keydown(function(e) {
                if (e.keyCode == 83 && e.ctrlKey) {
                    e.preventDefault();
                    var a = document.createEvent("MouseEvents");
                    a.initEvent("click", true, true);
                    $('#submit_button')[0].dispatchEvent(a);
                }
            });
        }
    },

    // 初始化文章列表页
    initNewsList: function() {
        
        // 初始化文章列表表格
        this.initArticleListTable();
        
        // 初始化文章详情列表表格
        this.initNewsDetailListTable();
    },

    // 初始化文章列表表格
    initArticleListTable: function() {
        const articleListTable = $('#article_list_table');
        const articleListDiv = $('#article_list_div');
        
        if (articleListTable.length && articleListDiv.length) {
            articleListTable.on('post-body.bs.table', function(){
                // 处理rank宽度
                if(articleListTable[0].scrollWidth > articleListDiv.width())
                    articleListDiv.width(articleListTable[0].scrollWidth + 20);
            });
        }
        
        // 禁用F5刷新，改为刷新表格
        $(window).keydown(function(e) {
            if (e.keyCode == 116 && !e.ctrlKey) {
                if(window.event){
                    try{e.keyCode = 0;}catch(e){}
                    e.returnValue = false;
                }
                e.preventDefault();
                $('#article_list_table').bootstrapTable('refresh');
            }
        });
    },

    // 初始化文章详情列表表格
    initNewsDetailListTable: function() {
        const table = $('#news_detail_list_table');
        
        if (table.length) {
            table.on('post-body.bs.table', function(){
                table.bootstrapTable('resetView', {'height': this.scrollHeight + 120});
            });
            table.on('expand-row.bs.table', function(index, row, $detail){
                table.bootstrapTable('resetView', {'height': this.scrollHeight + 120});
            });
            table.on('collapse-row.bs.table', function(index, row, $detail){
                table.bootstrapTable('resetView', {'height': this.scrollHeight + 120});
            });
        }
    },


    // 标记为已初始化
    _initialized: true,
    _initExecuted: false
};

// ========================================
// 文章管理表格 Formatter 函数
// ========================================

// 文章标题formatter - 带自适应宽度和链接
function FormatterNewsTitle(value, row, index, field) {
    // 根据窗口宽度计算合理的标题宽度
    let windowWidth = window.innerWidth;
    let titleWidth;
    
    if (windowWidth >= 1200) {
        titleWidth = Math.min(600, Math.max(300, (windowWidth - 400) * 0.4));
    } else if (windowWidth >= 992) {
        titleWidth = Math.min(300, Math.max(150, (windowWidth - 300) * 0.3));
    } else if (windowWidth >= 768) {
        titleWidth = Math.min(250, Math.max(120, (windowWidth - 200) * 0.4));
    } else {
        titleWidth = Math.min(200, Math.max(100, windowWidth * 0.4));
    }
    
    // 根据状态确定颜色类
    let colorClass = '';
    if (row['defunct'] == '1') {
        colorClass = 'text-muted'; // 隐藏状态用灰色
    } else {
        colorClass = 'text-primary'; // 公开状态用蓝色
    }
    
    return `<a class="text-decoration-none ${colorClass}" title="${value}" href="/admin/news/news_edit?id=${row['news_id']}" style="max-width: ${titleWidth}px; display: inline-block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${value}</a>`;
}

// 文章分类formatter
function FormatterNewsCategory(value, row, index, field) {
    // 使用 NewsModule 的配置
    const categoryConfig = window.NewsModule ? window.NewsModule.getCategory(value) : null;
    
    if (categoryConfig) {
        // 根据分类设置不同的颜色
        const colorMap = {
            'news': 'bg-info',
            'notification': 'bg-warning', 
            'answer': 'bg-success',
            'cpcinfo': 'bg-primary'
        };
        const colorClass = colorMap[value] || 'bg-secondary';
        
        return `<span class="badge ${colorClass}">${categoryConfig.chinese}<span class="en-text">${categoryConfig.english}</span></span>`;
    } else {
        return `<span class="badge bg-secondary">${value}<span class="en-text">Unknown</span></span>`;
    }
}

// 文章状态formatter
function FormatterNewsStatus(value, row, index, field) {
    return createDefunctFormatter({
        idField: 'news_id',
        publicText: '公开',
        hiddenText: '隐藏',
        publicTextEn: 'Public',
        hiddenTextEn: 'Hidden'
    })(value, row, index, field);
}

// 文章编辑formatter
function FormatterNewsEdit(value, row, index, field) {
    if (row.is_admin) {
        return `<a href='/admin/news/news_edit?id=${row.news_id}' class="btn btn-sm btn-outline-primary" title="编辑文章(Edit Article)">
                    <i class="bi bi-pencil-square"></i>
                </a>`;
    } else {
        return `<span class="btn btn-sm btn-outline-secondary disabled" title="无权限编辑(No Permission)">
                    <i class="bi bi-lock"></i>
                </span>`;
    }
}

// 生成分类选项HTML（用于管理后台）
function generateCategoryOptions() {
    if (!window.NewsModule) return '';
    
    let html = '<option value="-1" selected>全部<span class="en-text">All</span></option>';
    const categories = window.NewsModule.getAllCategories();
    
    for (const [key, config] of Object.entries(categories)) {
        html += `<option value="${key}">${config.chinese}</option>`;
    }
    
    return html;
}

// 标签单元格样式函数
function TagCellStyle(value, row, index) {
    return {
        css: {'max-width': '120px'}
    };
}

// 新闻单元格样式函数
function NewsCellStyle(value, row, index) {
    return {
        css: {'max-width': '650px'}
    };
}

// 详情格式化函数
function detailFormatter(index, row) {
    var html = [];
    $.each(row, function (key, value) {
        if(key == 'content')
            html.push("<article class='md_display_div'>" + value + "</article>");
    });
    return html.join('');
}

// 将函数暴露到全局作用域
window.generateCategoryOptions = generateCategoryOptions;
window.TagCellStyle = TagCellStyle;
window.NewsCellStyle = NewsCellStyle;
window.detailFormatter = detailFormatter;

// 页面加载完成后自动初始化
document.addEventListener('DOMContentLoaded', function() {
    // 检查是否在文章相关页面（排除首页，避免与NewsModule冲突）
    if ((window.location.pathname.includes('/news') || 
         window.location.pathname.includes('/notification') ||
         window.location.pathname.includes('/answer') ||
         window.location.pathname.includes('/cpcinfo')) &&
        !window.location.pathname.includes('/index') && 
        window.location.pathname !== '/index') {
        if (window.NewsModuleDetail && !window.NewsModuleDetail._initExecuted) {
            window.NewsModuleDetail.init();
        }
    }
    
    // 如果是管理后台文章页面，初始化分类选项
    if (window.location.pathname.includes('/admin/news')) {
        const categorySelect = document.querySelector('select[name="category"]');
        if (categorySelect && window.generateCategoryOptions) {
            categorySelect.innerHTML = generateCategoryOptions();
        }
    }
});

} // 结束防重复执行检查的 else 块
