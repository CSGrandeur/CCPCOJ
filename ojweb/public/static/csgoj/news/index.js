/**
 * 首页文章模块 JavaScript
 * Home Article Module JavaScript
 */

// 防重复执行检查
if (window.NewsModule && window.NewsModule._initialized) {
    console.warn('NewsModule already initialized, skipping duplicate initialization');
} else {
    // 标记为已初始化
    if (window.NewsModule) {
        window.NewsModule._initialized = true;
    }

window.NewsModule = {
    // 菜单配置
    categories: {
        'news': {
            chinese: '团队文章',
            english: 'Team Articles',
            icon: 'bi-newspaper',
            url: '/index/news'
        },
        'notification': {
            chinese: '通知公告',
            english: 'Notifications',
            icon: 'bi-bell',
            url: '/index/notification'
        },
        'answer': {
            chinese: '解题报告',
            english: 'Solutions',
            icon: 'bi-lightbulb',
            url: '/index/answer'
        },
        'cpcinfo': {
            chinese: '竞赛周边',
            english: 'Contest Info',
            icon: 'bi-trophy',
            url: '/index/cpcinfo'
        }
    },

    // 首页显示的分类（对应后端的 CATEGORY_SHOW_INDEX）
    showInIndex: ['news', 'notification', 'answer', 'cpcinfo'],

    // 获取所有菜单项
    getAllCategories: function() {
        return this.categories;
    },

    // 获取指定菜单项
    getCategory: function(key) {
        return this.categories[key] || null;
    },

    // 获取菜单项的中文名称
    getChineseName: function(key) {
        const category = this.getCategory(key);
        return category ? category.chinese : key;
    },

    // 获取菜单项的英文名称
    getEnglishName: function(key) {
        const category = this.getCategory(key);
        return category ? category.english : key;
    },

    // 获取菜单项的图标
    getIcon: function(key) {
        const category = this.getCategory(key);
        return category ? category.icon : 'bi-question-circle';
    },

    // 获取菜单项的URL
    getUrl: function(key) {
        const category = this.getCategory(key);
        return category ? category.url : '/index/' + key;
    },

    // 生成菜单HTML
    generateMenuHtml: function(currentController) {
        let html = '';
        const categories = this.getAllCategories();
        
        for (const [key, config] of Object.entries(categories)) {
            const isActive = currentController === key ? ' active' : '';
            html += `
                <li class="nav-item">
                    <a class="nav-link${isActive}" href="${config.url}" role="button" aria-haspopup="true" aria-expanded="false">
                        <i class="${config.icon} me-1"></i>${config.chinese}<span class="en-text">${config.english}</span>
                    </a>
                </li>
            `;
        }
        
        return html;
    },

    // 初始化菜单
    initMenu: function(currentController) {
        const menuContainer = document.querySelector('.nav-tabs');
        if (menuContainer) {
            // 检查是否已经插入过动态菜单
            const existingDynamicMenu = menuContainer.querySelector('.nav-item[data-dynamic-menu]');
            if (existingDynamicMenu) {
                return; // 已经插入过，避免重复
            }
            
            // 找到动态菜单的插入位置（在主页和关于之间）
            const homeItem = menuContainer.querySelector('a[href*="index"]').parentElement;
            const aboutItem = menuContainer.querySelector('a[href*="about"]').parentElement;
            
            if (homeItem && aboutItem) {
                // 在主页和关于之间插入动态菜单
                const dynamicMenuHtml = this.generateMenuHtml(currentController);
                homeItem.insertAdjacentHTML('afterend', dynamicMenuHtml);
                
                // 标记已插入的动态菜单项
                const dynamicMenuItems = menuContainer.querySelectorAll('.nav-item');
                dynamicMenuItems.forEach((item, index) => {
                    const link = item.querySelector('a');
                    if (link && (link.href.includes('/index/news') || 
                                link.href.includes('/index/notification') || 
                                link.href.includes('/index/answer') || 
                                link.href.includes('/index/cpcinfo'))) {
                        item.setAttribute('data-dynamic-menu', 'true');
                    }
                });
            }
        }
    },

    // 生成分类新闻列表HTML
    generateCategoryNewsHtml: function(categoryKey, newsList) {
        const categoryConfig = this.getCategory(categoryKey);
        if (!categoryConfig) return '';

        const hasNews = newsList && newsList.length > 0;
        const newsHtml = hasNews ? this.generateNewsListHtml(newsList, categoryKey) : this.generateEmptyNewsHtml();
        
        return `
            <div class="col-md-12 col-lg-6 col-sm-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="${categoryConfig.icon} me-2"></i>${categoryConfig.chinese}
                        </h5>
                        <a href="${categoryConfig.url}" class="btn btn-outline-primary btn-sm">
                            更多<span class="en-text">More</span> 
                        </a>
                    </div>
                    <div class="card-body p-0">
                        ${newsHtml}
                    </div>
                </div>
            </div>
        `;
    },

    // 生成新闻列表HTML
    generateNewsListHtml: function(newsList, categoryKey) {
        let html = '<div class="list-group list-group-flush">';
        
        newsList.forEach(function(news) {
            const isNew = window.NewsModule.isRecentNews(news.time);
            html += `
                <div class="list-group-item">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <a href="/index/${categoryKey}/detail?nid=${news.news_id}" class="text-decoration-none" title="${news.title}">
                                <h6 class="mb-1">${news.title}</h6>
                            </a>
                            <small class="text-muted">
                                ${news.time ? news.time.substring(0, 16) : '0000-00-00 00:00'}
                            </small>
                        </div>
                        ${isNew ? '<span class="badge bg-danger">New</span>' : ''}
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        return html;
    },

    // 生成空文章HTML
    generateEmptyNewsHtml: function() {
        return `
            <div class="card-body text-center text-muted">
                <i class="bi bi-inbox fs-1"></i>
                <p class="mt-2">暂无<span class="en-text">No Articles</span></p>
            </div>
        `;
    },

    // 检查是否为最近文章（15天内）
    isRecentNews: function(timeStr) {
        if (!timeStr) return false;
        const newsTime = new Date(timeStr).getTime();
        const now = Date.now();
        const fifteenDays = 15 * 24 * 60 * 60 * 1000; // 15天的毫秒数
        return (now - newsTime) < fifteenDays;
    },

    // 加载分类文章数据
    loadCategoryNews: function(categoryKey) {
        return new Promise(function(resolve, reject) {
            $.ajax({
                url: `/index/${categoryKey}/category_news_list_ajax`,
                method: 'GET',
                dataType: 'json',
                success: function(data) {
                    // 只取前5条
                    const limitedData = data.slice(0, 5);
                    resolve(limitedData);
                },
                error: function(xhr, status, error) {
                    console.error(`Failed to load articles for category ${categoryKey}:`, error);
                    resolve([]);
                }
            });
        });
    },

    // 初始化首页文章列表
    initIndexNews: function() {
        const container = document.querySelector('.row.g-4');
        if (!container) return;

        // 检查是否已经初始化过
        if (container.hasAttribute('data-news-initialized')) {
            return; // 已经初始化过，避免重复
        }

        // 清空现有内容
        container.innerHTML = '';

        // 为每个分类加载数据并生成HTML
        const promises = this.showInIndex.map(categoryKey => {
            return this.loadCategoryNews(categoryKey).then(articleList => {
                return {
                    categoryKey: categoryKey,
                    newsList: articleList
                };
            });
        });

        Promise.all(promises).then(results => {
            results.forEach(result => {
                const categoryHtml = this.generateCategoryNewsHtml(result.categoryKey, result.newsList);
                container.insertAdjacentHTML('beforeend', categoryHtml);
            });
            // 标记已初始化
            container.setAttribute('data-news-initialized', 'true');
        }).catch(error => {
            console.error('Failed to load some category articles:', error);
        });
    },


    // 初始化模块
    init: function() {
        // 检查是否已经初始化过
        if (this._initExecuted) {
            console.warn('NewsModule.init() already executed, skipping');
            return;
        }
        this._initExecuted = true;

        // 初始化菜单
        if (window.currentController) {
            this.initMenu(window.currentController);
        }

        // 如果是首页，初始化新闻列表（排除管理后台）
        if (window.location.pathname.includes('/index') && 
            !window.location.pathname.includes('/news_list') && 
            !window.location.pathname.includes('/admin/')) {
            this.initIndexNews();
        }

    },

    // 标记为已初始化
    _initialized: true,
    _initExecuted: false
};

// 页面加载完成后自动初始化
document.addEventListener('DOMContentLoaded', function() {
    if (window.NewsModule && !window.NewsModule._initExecuted) {
        window.NewsModule.init();
    }
});


} // 结束防重复执行检查的 else 块
