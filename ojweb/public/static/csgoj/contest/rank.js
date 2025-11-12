/**
 * 统一榜单系统
 * 整合队伍排名、 学校排名、滚榜功能
 * 
 * 依赖：需要先加载 rank_tool.js（工具函数库）
 * 
 * 使用示例：
 * RankSystemInit('my-container', {
 *     key: 'contest_123',
 *     cid_list: '123,456',
 *     api_url: '/csgoj/contest/contest_data_ajax',
 *     team_photo_url: '/upload/contest_attach/abc/team_photo',
 *     school_badge_url: '/static/image/school_badge',
 *     rank_mode: 'roll'
 * });
 */
// #########################################
//  RankSystem 主类
// #########################################
if(typeof RankSystem == 'undefined') {
    class RankSystem {
        constructor(containerId, config = {}) {
            this.containerId = containerId;
            // 配置优先级：实例化参数 > window.RANK_CONFIG
            const globalConfig = window.RANK_CONFIG || {};
            this.config = RankToolMergeConfig(globalConfig, config);
            // 默认配置
            const defaultConfig = {
                flg_show_page_contest_title: true,       // 正常模式时是否显示比赛标题
                flg_show_fullscreen_contest_title: true, // 全屏模式时是否显示比赛标题
                flg_rank_cache: true,                    // 是否启用榜单数据缓存
                flg_show_time_progress: true,            // 是否显示时间进度条（默认显示）
                flg_show_controls_toolbar: true,         // 是否显示按钮功能区（默认显示）
                flg_show_team_id: false                 // 是否在校名前显示team_id（默认false）
            };
            this.config = RankToolMergeConfig(defaultConfig, this.config);
            
            this.key = this.config.key || `rank_${Date.now()}`; // 缓存键，用于localStorage等
            this.data = null;
            this.currentMode = this.GetInitialMode(); // 从URL anchor参数读取初始模式
            this.starMode = 0; // 0: 打星不排名, 1: 不含打星, 2: 打星参与排名
            this.isFullscreen = false;
            this.autoRefresh = false;
            this.autoScroll = false;
            this.showTimeOverlay = false; // 全屏时间遮罩层显示状态（倒计时功能）
            this.timeOverlayInterval = null; // 时间遮罩层更新定时器
            this.refreshInterval = null; // 自动刷新定时器
            this.autoScrollInterval = null; // 自动滚动定时器
            // 动画基础持续时间（毫秒）
            this.baseAnimationDuration = 1000; // 基础动画持续时间，用于排序动画
            this.minAnimationDuration = 300; // 最小动画持续时间
            this.maxAnimationDuration = 2000; // 最大动画持续时间
            // 时间回放功能
            this.timeReplayMode = false;
            this.replayTime = null; // 回放时间点
            this.contestStartTime = null;
            // 加载状态标志
            this.isInitialLoad = true; // 是否为初始加载
            this.contestEndTime = null;
            // 后端时间差（毫秒）
            this.backendTimeDiff = this.config.backend_time_diff || 0;
            // 时间进度条自动更新定时器
            this.timeProgressInterval = null;
            // Tooltip配置系统
            this.tooltipTemplates = {
                'coach': {
                    cn: '教练：{name}',
                    en: 'Coach: {name}'
                },
                'player': {
                    cn: '选手：{name}',
                    en: 'Player: {name}'
                }
            };
            // 专用tooltip处理函数注册表
            this.specialTooltipHandlers = {
                'problem-item': this.GenerateProblemItemTooltip.bind(this)
            };
            // 数据映射
            this.teamMap = {};
            this.solutionMap = {};
            this.problemMap = {};
            this.rankList = [];
            this.schoolMap = {};
            this.map_fb = { global: {}, regular: {} }; // 一血记录
            // DOM元素
            this.elements = {};
            this.container = null;
            // 旗帜映射缓存
            this._flagMapping = null;
            this._flagMappingPromise = null;
            // HTML配置
            this.htmlConfigs = {
                headerControls: {
                    starModeOptions: [
                        { value: '0', label: '打星不排名', label_en: 'Star No Rank', icon: RankToolGenerateIcon('star-half', '打星不排名', 'Star No Rank') },
                        { value: '1', label: '不含打星', label_en: 'Exclude Star', icon: RankToolGenerateIcon('moon-stars-fill', '不含打星', 'Exclude Star') },
                        { value: '2', label: '打星参与排名', label_en: 'Star Participate', icon: RankToolGenerateIcon('star-fill', '打星参与排名', 'Star Participate') }
                    ],
                    buttons: [
                        { id: 'refresh-btn', icon: RankToolGenerateIconOnly('refresh'), label: '刷新', label_en: 'Refresh', class: 'refresh-btn' },
                        { id: 'summary-btn', icon: RankToolGenerateIconOnly('info'), label: '统计', label_en: 'Summary', class: 'summary-btn' },
                        { id: 'team-rank-btn', icon: RankToolGenerateIconOnly('player'), label: '队伍排名', label_en: 'Team Rank', class: 'active' },
                        { id: 'school-rank-btn', icon: RankToolGenerateIconOnly('school'), label: ' 学校/组织 排名', label_en: 'School Rank', class: '' },
                        { id: 'help-btn', icon: RankToolGenerateIconOnly('question-circle'), label: '帮助', label_en: 'Help', class: 'help-btn' },
                        { id: 'fullscreen-btn', icon: RankToolGenerateIconOnly('fullscreen'), label: '全屏', label_en: 'Fullscreen', class: 'fullscreen-btn' }
                    ]
                }
            };
            this.Init();
        }
        // 图标系统（已迁移到 rank_tool.js，使用 RankTool 函数）
        // 从配置或URL anchor参数获取初始模式
        GetInitialMode() {
            const validModes = ['team', 'school', 'roll']; // 支持 roll 模式，用于专门的滚榜页面
            // 如果配置中提供了rank_mode，优先使用
            if (this.config.rank_mode && validModes.includes(this.config.rank_mode)) {
                return this.config.rank_mode;
            }
            // 否则从URL anchor参数读取
            const hash = window.location.hash;
            const params = new URLSearchParams(hash.substring(1));
            const mode = params.get('rank_mode');
            if (validModes.includes(mode)) {
                return mode;
            }
            // // 尝试从localStorage获取上次的模式
            // const savedMode = localStorage.getItem(`${this.key}_mode`);
            // if (validModes.includes(savedMode)) {
            //     return savedMode;
            // }
            return 'team'; // 默认模式
        }
        // 更新URL anchor参数
        UpdateAnchor(mode) {
            // 如果配置中提供了rank_mode，则不更新URL anchor
            if (this.config.rank_mode) {
                // 只保存到IndexedDB
                this.cache.set(`${this.key}_mode`, mode, 24 * 60 * 60 * 1000); // 24小时过期
                return;
            }
            const hash = window.location.hash;
            const params = new URLSearchParams(hash.substring(1));
            params.set('rank_mode', mode);
            // 更新URL但不触发页面跳转
            const newHash = '#' + params.toString();
            if (window.location.hash !== newHash) {
                window.history.replaceState(null, null, newHash);
            }
            // 保存到IndexedDB
            this.cache.set(`${this.key}_mode`, mode, 24 * 60 * 60 * 1000); // 24小时过期
        }
        Init() {
            // 找到指定的容器
            this.container = document.getElementById(this.containerId);
            // 初始化缓存管理器（如果还没有初始化）
            if (!this.cache) {
                this.cache = new IndexedDBCache('csgoj_rank', 'logotable');
                this.logoCache = new IndexedDBCache('csgoj_rank', 'logotable');
            }
            if (!this.container) {
                // console.warn(`Rank container with id "${this.containerId}" not found, running in external mode`);
                // 不提供 container，表示外部调用模式
                this.externalMode = true;
                return;
            }
            this.externalMode = false;
            // 添加rank-system类以应用基础样式
            this.container.classList.add('rank-system');
            // 清理之前的DOM和状态
            this.Cleanup();
            this.CreateHTML();
            this.InitElements();
            this.BindEvents();
            this.InitializeMode(); // 初始化模式状态
            // 先初始化缓存，然后加载数据
            this.cache.init().then(() => {
                this.InitLazyLoaders();
                this.LoadData();
            });
        }
        // 清理之前的DOM和状态
        Cleanup() {
            // 清理时间进度条自动更新定时器
            this.StopTimeProgressAutoUpdate();
            // 清理时间遮罩层定时器
            this.StopTimeOverlay();
            // 清理观察器
            if (this.logoObserver) {
                this.logoObserver.disconnect();
                this.logoObserver = null;
            }
            if (this._flagObserver) {
                this._flagObserver.disconnect();
                this._flagObserver = null;
            }
            // 清理DOM事件监听器
            if (this.elements) {
                // 移除所有事件监听器
                Object.values(this.elements).forEach(element => {
                    if (element && element.removeEventListener) {
                        // 这里可以添加具体的事件清理逻辑
                    }
                });
            }
            // 清理header元素（从容器外部移除）
            if (this.container) {
                const oldHeader = this.GetHeaderElement();
                if (oldHeader) {
                    oldHeader.remove();
                }
            }
            // 清理全屏状态
            if (this.isFullscreen) {
                this.container.classList.remove('fullscreen');
                this.isFullscreen = false;
            }
            // 清理tooltip
            if (this.globalTooltip) {
                this.globalTooltip.remove();
                this.globalTooltip = null;
                this.globalTooltipContent = null;
            }
            // 清理tooltip相关状态
            if (this.tooltipTimeouts) {
                Object.values(this.tooltipTimeouts).forEach(timeout => clearTimeout(timeout));
                this.tooltipTimeouts = {};
            }
            // 重置状态
            this.rankList = [];
            this.schoolList = [];
            this.data = null;
            this.elements = {};
        }
        // #########################################
        //  初始化和配置模块
        // #########################################
        // 初始化模式状态
        InitializeMode() {
            // 更新按钮状态（如果按钮存在）
            if (this.elements.teamRankBtn) {
                this.elements.teamRankBtn.classList.toggle('active', this.currentMode === 'team');
            }
            if (this.elements.schoolRankBtn) {
                this.elements.schoolRankBtn.classList.toggle('active', this.currentMode === 'school');
            }
            // 显示/隐藏 学校/组织 信息
            if (this.elements.schoolInfo) {
                this.elements.schoolInfo.style.display = this.currentMode === 'school' ? 'block' : 'none';
            }
            // 更新页面标题
            this.UpdatePageTitle();
        }
        // #########################################
        //  HTML生成和DOM操作模块
        // #########################################
        // HTML生成工具方法（已迁移到 rank_tool.js）
        CreateBilingualText(label, label_en) {
            return RankToolGenerateBilingualText(label, label_en);
        }
        GenerateSelectOptions(options) {
            return options.map(option => 
                `<option value="${option.value}">${this.CreateBilingualText(option.label, option.label_en)}</option>`
            ).join('');
        }
        GenerateButtons(buttons, withText = false) {
            if (withText) {
                // 带文字的按钮（用于下拉菜单）
                return buttons.map(button => 
                    `<div id="${button.id}" class="control-btn ${button.class} with-text" role="button" tabindex="0" ${RankToolGenerateBilingualAttributes(button.label, button.label_en)}>
                        ${button.icon}
                        <span class="button-text">${this.CreateBilingualText(button.label, button.label_en)}</span>
                    </div>`
                ).join('');
            } else {
                // 仅图标按钮（用于工具栏）
                return buttons.map(button => 
                    `<div id="${button.id}" class="control-btn ${button.class}" role="button" tabindex="0" ${RankToolGenerateBilingualAttributes(button.label, button.label_en)}>${button.icon}</div>`
                ).join('');
            }
        }
        // 生成自定义下拉组件
        GenerateCustomSelect(options, selectId, currentValue = '0', withText = false) {
            const currentOption = options.find(opt => opt.value === currentValue) || options[0];
            const btnClass = withText ? '' : 'icon-only';
            const btnContent = withText 
                ? `${RankToolGenerateIconOnly(RankToolGetIconKeyFromOption(currentOption))}<span class="option-text">${this.CreateBilingualText(currentOption.label, currentOption.label_en)}</span>`
                : RankToolGenerateIconOnly(RankToolGetIconKeyFromOption(currentOption));
            return `
                <div class="custom-select-container" id="${selectId}-container">
                    <div class="custom-select-btn ${btnClass}" id="${selectId}-btn" role="button" tabindex="0" ${RankToolGenerateBilingualAttributes(currentOption.label, currentOption.label_en)}>
                        ${btnContent}
                    </div>
                    <div class="custom-select-dropdown" id="${selectId}-dropdown">
                        ${options.map(option => `
                            <div class="custom-select-option ${option.value === currentValue ? 'selected' : ''}" 
                                data-value="${option.value}" 
                                ${RankToolGenerateBilingualAttributes(option.label, option.label_en)}>
                                ${RankToolGenerateIconOnly(RankToolGetIconKeyFromOption(option))}
                                <span class="option-text">${option.label}<en-text>${option.label_en}</en-text></span>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        }
        GenerateAwardItems(items) {
            return items.map(item => 
                `<div class="award-item">
                    <span class="award-label">${this.CreateBilingualText(item.label, item.label_en)}</span>
                    <span id="${item.id}" class="award-value"></span>
                </div>`
            ).join('');
        }
        CreateHTML() {
            // 清空容器
            this.container.innerHTML = '';
            // 创建页面头部（控制按钮）- 插入到容器外部
            this.CreateHeader();
            // 创建内容包装器（用于全屏时的居中对齐）
            const contentWrapper = document.createElement('div');
            contentWrapper.className = 'rank-content-wrapper';
            // 创建榜单容器（包含表格表头和数据）
            this.CreateRankContainer();
            // 将rank-container放入包装器
            const rankContainer = this.container.querySelector('.rank-container');
            if (rankContainer) contentWrapper.appendChild(rankContainer);
            // 将包装器添加到容器
            this.container.appendChild(contentWrapper);
            // 创建加载提示
            this.CreateLoading();
            // 创建模态框
            this.CreateModals();
        }
        CreateHeader() {
            const config = this.htmlConfigs.headerControls;
            const header = document.createElement('div');
            header.className = 'rank-header';
            // 根据配置和当前模式决定是否显示比赛标题
            const shouldShowTitle = this.isFullscreen 
                ? this.config.flg_show_fullscreen_contest_title 
                : this.config.flg_show_page_contest_title;
            const titleHtml = shouldShowTitle 
                ? `<h1 id="rank-page-title">${this.CreateBilingualText('比赛榜单', 'Contest Ranking')}</h1>`
                : '';
            
            // 根据配置决定是否显示时间进度条
            const showTimeProgress = this.config.flg_show_time_progress !== false;
            // 根据配置决定是否显示按钮功能区
            const showControlsToolbar = this.config.flg_show_controls_toolbar !== false;
            
            // 生成时间轴HTML（如果启用）
            const timeProgressHtml = showTimeProgress ? `
                        <!-- 时间轴区域（左侧） -->
                        <div class="time-progress-wrapper">
                            <div class="time-display-group">
                                <span id="time-progress-current" class="time-current">00:00:00</span>
                                <span id="time-progress-total" class="time-total">00:00:00</span>
                            </div>
                            <div class="time-progress-bar-container">
                                <input type="range" id="time-progress-slider" class="time-progress-slider" min="0" max="100" value="100">
                                <div class="time-progress-track"></div>
                                <div class="time-progress-track-outline"></div>
                            </div>
                            <button id="time-reset-btn" class="time-reset-btn" title="重置到最新时间">
                                <i class="bi bi-arrow-clockwise"></i>
                            </button>
                        </div>
            ` : '';
            
            // 生成按钮功能区HTML（如果启用）
            const controlsToolbarHtml = showControlsToolbar ? `
                        <!-- 控制按钮区域（右侧，宽屏时显示） -->
                        <div class="controls-toolbar" id="header-controls">
                            <div class="toolbar-group">
                                <div class="toolbar-item">
                                    ${this.GenerateCustomSelect(config.starModeOptions, 'star-mode', '0')}
                                </div>
                                <div class="toolbar-item">
                                    ${this.GenerateButtons([config.buttons[0]])}
                                </div>
                            </div>
                            <div class="toolbar-group">
                                ${this.GenerateButtons(config.buttons.slice(1, 3))}
                            </div>
                            <div class="toolbar-group">
                                ${this.GenerateButtons(config.buttons.slice(3))}
                            </div>
                        </div>
                        
                        <!-- 折叠按钮（窄屏时显示） -->
                        <button class="controls-toggle-btn" id="controls-toggle-btn" title="更多选项">
                            <i class="bi bi-three-dots"></i>
                        </button>
                        
                        <!-- 折叠下拉菜单（窄屏时使用） -->
                        <div class="controls-dropdown" id="controls-dropdown">
                            <div class="toolbar-group">
                                <div class="toolbar-item">
                                    ${this.GenerateCustomSelect(config.starModeOptions, 'star-mode-dropdown', '0', true)}
                                </div>
                                <div class="toolbar-item">
                                    ${this.GenerateButtons([config.buttons[0]], true)}
                                </div>
                            </div>
                            <div class="toolbar-group">
                                ${this.GenerateButtons(config.buttons.slice(1, 3), true)}
                            </div>
                            <div class="toolbar-group">
                                ${this.GenerateButtons(config.buttons.slice(3), true)}
                            </div>
                        </div>
            ` : '';
            
            // 只有当至少一个区域需要显示时，才渲染 controls-time-section
            const controlsTimeSectionHtml = (showTimeProgress || showControlsToolbar) ? `
                <div class="controls-time-section">
                    <div class="controls-time-container">
                        ${timeProgressHtml}
                        ${controlsToolbarHtml}
                    </div>
                </div>
            ` : '';
            
            
            header.innerHTML = `
                <div class="header-content">
                    <div class="title-section">
                        ${titleHtml}
                    </div>
                </div>
                ${controlsTimeSectionHtml}
            `;
            // 将 header 插入到容器外部（在容器之前）
            this.container.insertAdjacentElement('beforebegin', header);
            
            // 检查 header 是否为空，如果为空则隐藏
            this.UpdateHeaderVisibility();
        }
        
        /**
         * 获取 header 元素（从容器外部查找）
         */
        GetHeaderElement() {
            let header = this.container.previousElementSibling;
            if (!header || !header.classList.contains('rank-header')) {
                // 如果前一个兄弟节点不是 header，尝试查找
                const containerParent = this.container.parentNode;
                if (containerParent) {
                    header = Array.from(containerParent.children).find(el => 
                        el.classList.contains('rank-header') && 
                        el.nextElementSibling === this.container
                    );
                }
                if (!header) {
                    // 最后尝试全局查找（使用 ID）
                    const headerId = `#${this.containerId}`;
                    const allHeaders = document.querySelectorAll('.rank-header');
                    for (const h of allHeaders) {
                        if (h.nextElementSibling && h.nextElementSibling.id === this.containerId) {
                            header = h;
                            break;
                        }
                    }
                }
            }
            return header;
        }
        
        /**
         * 更新 header 的可见性：如果 header 没有内容，则隐藏它
         */
        UpdateHeaderVisibility() {
            const header = this.GetHeaderElement();
            if (!header) return;
            
            // 检查是否有实际内容
            const titleSection = header.querySelector('.title-section');
            const controlsTimeSection = header.querySelector('.controls-time-section');
            
            // 检查 title-section 是否有实际内容（h1 标签或有文本内容）
            let hasTitle = false;
            if (titleSection) {
                const h1 = titleSection.querySelector('h1');
                if (h1) {
                    // 有 h1 标签，检查是否有文本内容
                    hasTitle = h1.textContent.trim().length > 0;
                } else {
                    // 没有 h1，检查整个 title-section 是否有文本内容
                    // 排除空白字符和空 en-text 标签
                    const textContent = Array.from(titleSection.childNodes)
                        .filter(node => node.nodeType === Node.TEXT_NODE)
                        .map(node => node.textContent)
                        .join('')
                        .trim();
                    hasTitle = textContent.length > 0;
                }
            }
            
            // 检查 controls-time-section 是否存在且有实际内容（子元素）
            const hasControls = controlsTimeSection && controlsTimeSection.children.length > 0;
            
            // 如果没有任何内容，隐藏 header（设置为 display: none 而不是高度 0，更彻底）
            if (!hasTitle && !hasControls) {
                header.style.display = 'none';
            } else {
                header.style.display = '';
            }
        }
        CreateRankContainer() {
            // 创建rank容器
            const container = document.createElement('div');
            container.className = 'rank-container';
            // 使用统一的CreateHeaderRow方法创建表头
            const tableHeaderRow = this.CreateHeaderRow();
            // 添加rank-grid
            const rankGrid = document.createElement('div');
            rankGrid.id = 'rank-grid';
            rankGrid.className = 'rank-grid';
            container.appendChild(tableHeaderRow);
            container.appendChild(rankGrid);
            this.container.appendChild(container);
            // 为整个rank容器注册hover事件，动态处理tooltip
            this.SetupDynamicTooltips(this.container);
        }
        // #########################################
        //  Tooltip和交互功能模块
        // #########################################
        // 设置动态tooltip处理
        SetupDynamicTooltips(container) {
            // 使用事件委托处理hover事件
            const throttle = (fn, gap = 16) => {
                let last = 0;
                return function(...args) {
                    const now = Date.now();
                    if (now - last >= gap) { last = now; return fn.apply(this, args); }
                };
            };
            
            // 处理鼠标悬停事件
            container.addEventListener('mouseover', (e) => {
                // 滚榜状态下，不显示tooltip
                if (this.currentMode === 'roll' && this.isRolling) {
                    return;
                }
                
                // 查找有tooltip属性的元素（包括自身和父元素）
                let target = e.target;
                let titlecn = null;
                let titleen = null;
                while (target && target !== container) {
                    // 优先检查专用tooltip处理函数
                    const specialHandler = this.HasSpecialTooltipHandler(target);
                    if (specialHandler) {
                        const content = this.GetSpecialTooltipContent(target, specialHandler);
                        if (content) {
                            titlecn = content.titlecn;
                            titleen = content.titleen;
                            break;
                        }
                    }
                    // 回退到传统属性方式
                    titlecn = target.getAttribute('title-cn');
                    titleen = target.getAttribute('title-en');
                    if (titlecn || titleen) {
                        break;
                    }
                    target = target.parentElement;
                }
                if (titlecn || titleen) {
                    // 初始化tooltipTimeouts
                    if (!this.tooltipTimeouts) {
                        this.tooltipTimeouts = {};
                    }
                    // 清除之前的延迟
                    if (this.tooltipTimeouts[target]) {
                        clearTimeout(this.tooltipTimeouts[target]);
                    }
                    // 延迟显示tooltip，传递鼠标事件
                    this.tooltipTimeouts[target] = setTimeout(() => {
                        this.ShowTooltipForElement(target, titlecn, titleen, e);
                    }, 300);
                }
            });
            
            // 处理点击和双击事件 - 使用延迟区分单击和双击
            let clickTimeout = null;
            let clickCount = 0;
            
            container.addEventListener('click', (e) => {
                clickCount++;
                
                // 清除之前的延迟
                if (clickTimeout) {
                    clearTimeout(clickTimeout);
                }
                
                // 延迟处理单击事件，给双击事件机会
                clickTimeout = setTimeout(() => {
                    if (clickCount === 1) {
                        // 单击事件 - 点击空白区域显示队伍ID
                        const rankRow = e.target.closest('.rank-row');
                        if (rankRow) {
                            const rowId = rankRow.getAttribute('data-row-id');
                            if (rowId) {
                                // 检查是否点击在空白区域（没有其他tooltip元素）
                                const hasTooltipElement = e.target.closest('[title-cn], [title-en], .control-btn, .custom-select-btn, .rank-item, .solve-item, .penalty-item, .problem-item');
                                if (!hasTooltipElement) {
                                    // 滚榜状态下，不显示tooltip
                                    if (this.currentMode === 'roll' && this.isRolling) {
                                        return;
                                    }
                                    // 显示队伍ID的tooltip
                                    const teamId = rowId;
                                    this.ShowTooltipForElement(rankRow, `队伍ID: ${teamId}`, `Team ID: ${teamId}`, e);
                                }
                            }
                        }
                    }
                    clickCount = 0;
                }, 300); // 300ms延迟，给双击事件足够时间
            });
            
            container.addEventListener('dblclick', async (e) => {
                // 清除单击延迟
                if (clickTimeout) {
                    clearTimeout(clickTimeout);
                    clickTimeout = null;
                }
                clickCount = 0;
                
                // 双击事件 - 复制tooltip信息
                let target = e.target;
                let titlecn = null;
                let titleen = null;
                
                // 首先检查是否点击在rank-row空白区域
                const rankRow = e.target.closest('.rank-row');
                if (rankRow) {
                    const rowId = rankRow.getAttribute('data-row-id');
                    if (rowId) {
                        const hasTooltipElement = e.target.closest('[title-cn], [title-en], .control-btn, .custom-select-btn, .rank-item, .solve-item, .penalty-item, .problem-item');
                        if (!hasTooltipElement) {
                            // 空白区域双击，复制队伍ID
                            const teamId = rowId;
                            const success = await this.CopyToClipboard(teamId);
                            if (success) {
                                this.ShowCopySuccessBubble(e);
                            }
                            return;
                        }
                    }
                }
                
                // 特判：检查是否双击在副语言队名上
                const teamNameEn = e.target.closest('.team-name-en');
                if (teamNameEn) {
                    const rankRow = teamNameEn.closest('.rank-row');
                    if (rankRow) {
                        const rowId = rankRow.getAttribute('data-row-id');
                        if (rowId) {
                            // 从数据中获取副语言队名
                            const teamData = this.rankData.find(item => item.team.team_id == rowId);
                            if (teamData && teamData.team.name_en) {
                                const success = await this.CopyToClipboard(teamData.team.name_en);
                                if (success) {
                                    this.ShowCopySuccessBubble(e);
                                }
                                return;
                            }
                        }
                    }
                }
                
                // 查找有tooltip属性的元素
                while (target && target !== container) {
                    // 优先检查专用tooltip处理函数
                    const specialHandler = this.HasSpecialTooltipHandler(target);
                    if (specialHandler) {
                        const content = this.GetSpecialTooltipContent(target, specialHandler);
                        if (content) {
                            titlecn = content.titlecn;
                            titleen = content.titleen;
                            break;
                        }
                    }
                    // 回退到传统属性方式
                    titlecn = target.getAttribute('title-cn');
                    titleen = target.getAttribute('title-en');
                    if (titlecn || titleen) {
                        break;
                    }
                    target = target.parentElement;
                }
                
                // 如果找到tooltip内容，复制到剪贴板
                if (titlecn || titleen) {
                    const copyText = titlecn || titleen;
                    const success = await this.CopyToClipboard(copyText);
                    if (success) {
                        this.ShowCopySuccessBubble(e);
                    }
                }
            });
            container.addEventListener('mouseout', (e) => {
                // 滚榜状态下，不处理tooltip
                if (this.currentMode === 'roll' && this.isRolling) {
                    return;
                }
                
                // 查找有tooltip属性的元素（包括自身和父元素）
                let target = e.target;
                let titlecn = null;
                let titleen = null;
                while (target && target !== container) {
                    // 优先检查专用tooltip处理函数
                    const specialHandler = this.HasSpecialTooltipHandler(target);
                    if (specialHandler) {
                        const content = this.GetSpecialTooltipContent(target, specialHandler);
                        if (content) {
                            titlecn = content.titlecn;
                            titleen = content.titleen;
                            break;
                        }
                    }
                    // 回退到传统属性方式
                    titlecn = target.getAttribute('title-cn');
                    titleen = target.getAttribute('title-en');
                    if (titlecn || titleen) {
                        break;
                    }
                    target = target.parentElement;
                }
                if (titlecn || titleen) {
                    // 清除延迟
                    if (this.tooltipTimeouts && this.tooltipTimeouts[target]) {
                        clearTimeout(this.tooltipTimeouts[target]);
                    }
                    // 延迟隐藏tooltip
                    setTimeout(() => {
                        this.HideGlobalTooltip();
                    }, 50);
                }
            });
            container.addEventListener('mousemove', throttle((e) => {
                // 滚榜状态下，不处理tooltip位置更新
                if (this.currentMode === 'roll' && this.isRolling) {
                    return;
                }
                
                // 查找有tooltip属性的元素（包括自身和父元素）
                let target = e.target;
                let titlecn = null;
                let titleen = null;
                while (target && target !== container) {
                    // 优先检查专用tooltip处理函数
                    const specialHandler = this.HasSpecialTooltipHandler(target);
                    if (specialHandler) {
                        const content = this.GetSpecialTooltipContent(target, specialHandler);
                        if (content) {
                            titlecn = content.titlecn;
                            titleen = content.titleen;
                            break;
                        }
                    }
                    // 回退到传统属性方式
                    titlecn = target.getAttribute('title-cn');
                    titleen = target.getAttribute('title-en');
                    if (titlecn || titleen) {
                        break;
                    }
                    target = target.parentElement;
                }
                if ((titlecn || titleen) && this.globalTooltip && this.globalTooltip.style.display !== 'none') {
                    this.UpdateTooltipPosition(this.globalTooltip, target, e);
                }
            }));
        }
        // 处理元素中的tooltip (已废弃，使用全局tooltip)
        ProcessElementForTooltips(element) {
            // 不再需要，使用全局tooltip处理
        }
        CreateLoading() {
            const loading = document.createElement('div');
            loading.id = 'loading';
            loading.className = 'loading-overlay';
            loading.innerHTML = '<div class="loading-spinner">初始化中...<en-text>Initializing...</en-text></div>';
            this.container.appendChild(loading);
        }
        CreateModals() {
            // 统计模态框
            const summaryModal = document.createElement('div');
            summaryModal.id = 'summary-modal';
            summaryModal.className = 'modal-overlay';
            summaryModal.style.display = 'none';
            summaryModal.innerHTML = `
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>${this.CreateBilingualText('统计数据', 'Statistics')}</h3>
                        <button id="close-summary" class="close-btn">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div id="summary-content"></div>
                    </div>
                </div>
            `;
            this.container.appendChild(summaryModal);
            
            // 快捷键帮助模态框
            const helpModal = document.createElement('div');
            helpModal.id = 'rank-help-modal';
            helpModal.className = 'modal-overlay';
            helpModal.style.display = 'none';
            helpModal.innerHTML = `
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>${this.CreateBilingualText('快捷键说明', 'Keyboard Shortcuts')}</h3>
                        <button id="close-rank-help" class="close-btn">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="roll-help-content">
                            <div class="roll-help-section">
                                <div class="roll-help-section-title">${this.CreateBilingualText('基本控制', 'Basic Control')}</div>
                                <div class="roll-help-items">
                                    <div class="roll-help-item">
                                        <code>F5</code>
                                        <span>${this.CreateBilingualText('刷新数据', 'Refresh Data')}</span>
                                    </div>
                                    <div class="roll-help-item">
                                        <code>A</code> / <code>a</code>
                                        <span>${this.CreateBilingualText('开启/关闭自动刷新', 'Toggle Auto Refresh')}</span>
                                    </div>
                                    <div class="roll-help-item">
                                        <code>B</code> / <code>b</code>
                                        <span>${this.CreateBilingualText('开启/关闭自动滚动', 'Toggle Auto Scroll')}</span>
                                    </div>
                                    <div class="roll-help-item">
                                        <code>T</code> / <code>t</code>
                                        <span>${this.CreateBilingualText('显示/隐藏倒计时（仅全屏模式）', 'Toggle Countdown (Fullscreen Only)')}</span>
                                    </div>
                                </div>
                            </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            this.container.appendChild(helpModal);
            
            // 全屏时间遮罩层
            const timeOverlay = document.createElement('div');
            timeOverlay.id = 'time-overlay';
            timeOverlay.className = 'time-overlay';
            timeOverlay.style.display = 'none';
            timeOverlay.innerHTML = `
                <div class="time-overlay-content">
                    <div id="time-overlay-text" class="time-overlay-text">00:00:00</div>
                </div>
            `;
            this.container.appendChild(timeOverlay);
        }
        InitElements() {
            // 获取 header 元素（在容器外部）
            const header = this.GetHeaderElement();
            
            this.elements = {
                pageTitle: header ? header.querySelector('#rank-page-title') : null,
                starMode: header ? header.querySelector('#star-mode') : null,
                refreshBtn: header ? header.querySelector('#refresh-btn') : null,
                summaryBtn: header ? header.querySelector('#summary-btn') : null,
                teamRankBtn: header ? header.querySelector('#team-rank-btn') : null,
                schoolRankBtn: header ? header.querySelector('#school-rank-btn') : null,
                fullscreenBtn: header ? header.querySelector('#fullscreen-btn') : null,
                rankGrid: this.container.querySelector('#rank-grid'),
                loading: this.container.querySelector('#loading'),
                summaryModal: this.container.querySelector('#summary-modal'),
                summaryContent: this.container.querySelector('#summary-content'),
                closeSummary: this.container.querySelector('#close-summary'),
                closeAward: this.container.querySelector('#close-award'),
                helpModal: this.container.querySelector('#rank-help-modal'),
                closeHelp: this.container.querySelector('#close-rank-help'),
                helpBtn: header ? header.querySelector('#help-btn') : null,
                timeOverlay: this.container.querySelector('#time-overlay'),
                timeOverlayText: this.container.querySelector('#time-overlay-text'),
                // 响应式折叠相关
                headerControls: header ? header.querySelector('#header-controls') : null,
                foldBtn: header ? header.querySelector('#fold-btn') : null,
                controlsDropdown: header ? header.querySelector('#controls-dropdown') : null,
                starModeDropdown: header ? header.querySelector('#star-mode-dropdown') : null,
                // 自定义下拉组件
                starModeBtn: header ? header.querySelector('#star-mode-btn') : null,
                starModeDropdown: header ? header.querySelector('#star-mode-dropdown') : null,
                starModeDropdownBtn: header ? header.querySelector('#star-mode-dropdown-btn') : null,
                starModeDropdownDropdown: header ? header.querySelector('#star-mode-dropdown-dropdown') : null
            };
            // 创建 学校/组织 信息元素
            this.CreateSchoolInfo();
        }
        // 初始化所有懒加载功能
        InitLazyLoaders() {
            this.InitSchoolLogoLoader();
            this.InitFlagLoader();
        }
        // 通用懒加载初始化方法
        InitImageLoader(config) {
            const {
                type,
                baseUrl,
                fetchFn,
                calculateFn,
                onSuccess,
                onError,
                selector,
                attributeName,
                observerProperty,
                baseProperty
            } = config;
            // 设置基础URL
            this[baseProperty] = baseUrl;
            // 使用统一的缓存管理器
            const getFn = (key) => this.logoCache.get(key);
            const setFn = (key, val, expire) => this.logoCache.set(key, val, expire);
            // 创建懒加载观察器
            this[observerProperty] = this.CreateImageLazyLoader({
                type: type,
                getFn: getFn,
                setFn: setFn,
                baseUrl: baseUrl,
                fetchFn: fetchFn,
                calculateFn: calculateFn,
                onSuccess: onSuccess,
                onError: onError
            });
            // 延迟观察，确保DOM元素已创建
            setTimeout(() => {
                this.container.querySelectorAll(selector).forEach(element => {
                    const identifier = element.getAttribute(attributeName);
                    if (identifier && !element.dataset.observed) {
                        this[observerProperty].observe(element);
                        element.dataset.observed = 'true';
                    }
                });
            }, 100);
        }
        // 初始化学校logo懒加载
        InitSchoolLogoLoader() {
            this.InitImageLoader({
                type: 'logo',
                baseUrl: this.config.school_badge_url || '/static/image/school_badge',
                fetchFn: this.FetchSchoolLogoDataUrl.bind(this),
                onError: () => {
                    // logo 加载失败时保持透明，不处理
                },
                selector: '.school-logo',
                attributeName: 'data-school',
                observerProperty: '_logoObserver',
                baseProperty: '_logoBase'
            });
        }
        // 初始化旗帜懒加载
        InitFlagLoader() {
            this.InitImageLoader({
                type: 'flag',
                baseUrl: this.config.region_flag_url || '/static/image/region_flag',
                fetchFn: this.FetchFlagDataUrl.bind(this),
                calculateFn: this.CalculateFlagUrl.bind(this),
                onSuccess: (element, dataUrl) => {
                    // 旗帜加载成功，显示旗帜
                    this.ShowFlag(element);
                },
                onError: (element) => {
                    // // 旗帜加载失败，显示地区名
                    // this.ShowRegionText(element, element.getAttribute('data-flag'));
                },
                selector: 'img.flag-icon',
                attributeName: 'data-flag',
                observerProperty: '_flagObserver',
                baseProperty: '_flagBase'
            });
        }
        async FetchSchoolLogoDataUrl(fileKey) {
            // 检查离线图片数据（优先使用，避免CORS问题）
            if (window.OFFLINE_IMAGES?.school_badge) {
                // fileKey格式：baseUrl/school，需要提取school名
                const baseUrl = this.config.school_badge_url || '/static/image/school_badge';
                // 移除baseUrl和末尾的.jpg（如果有）
                let schoolKey = fileKey.replace(baseUrl + '/', '').replace(/\.jpg$/, '');
                // schoolKey可能是URL编码后的，先尝试直接查找
                let base64Data = window.OFFLINE_IMAGES.school_badge[schoolKey];
                // 如果没找到，尝试URL编码后查找
                if (!base64Data && schoolKey !== encodeURIComponent(schoolKey)) {
                    schoolKey = encodeURIComponent(schoolKey);
                    base64Data = window.OFFLINE_IMAGES.school_badge[schoolKey];
                }
                // 如果还是没找到，尝试解码后查找（处理已经编码的key）
                if (!base64Data) {
                    try {
                        const decodedKey = decodeURIComponent(schoolKey);
                        base64Data = window.OFFLINE_IMAGES.school_badge[decodedKey];
                    } catch (e) {
                        // 解码失败，继续尝试在线加载
                    }
                }
                if (base64Data) {
                    return base64Data; // 直接返回base64 data URL
                }
            }
            
            // 离线模式没有找到，尝试在线加载（用于在线模式的fallback）
            const tryList = ['jpg'];
            for (let i = 0; i < tryList.length; i++) {
                const url = `${fileKey}.${tryList[i]}`;
                try {
                    const resp = await fetch(url, { cache: 'force-cache' });
                    if (!resp.ok) continue;
                    const blob = await resp.blob();
                    const dataUrl = await new Promise((resolve, reject) => {
                        const reader = new FileReader();
                        reader.onloadend = () => resolve(reader.result);
                        reader.onerror = reject;
                        reader.readAsDataURL(blob);
                    });
                    return dataUrl;
                } catch (e) {
                    // try next ext
                }
            }
            throw new Error('No valid school logo');
        }
        CreateSchoolInfo() {
            const schoolInfo = document.createElement('div');
            schoolInfo.id = 'school-info';
            schoolInfo.className = 'school-info';
            schoolInfo.style.display = 'none';
            schoolInfo.innerHTML = `<h4>每校Top <span id="top-team-count">1</span> 队伍（合并）计入${this.CreateBilingualText('', 'Top <span id="top-team-count-en">1</span> teams per school (merged)')}</h4>`;
            // 插入到榜单容器之前
            const rankContainer = this.container.querySelector('.rank-container');
            if (rankContainer && rankContainer.parentNode) {
                rankContainer.parentNode.insertBefore(schoolInfo, rankContainer);
            } else {
                // 如果找不到rank-container，直接添加到容器中
                this.container.appendChild(schoolInfo);
            }
            this.elements.schoolInfo = schoolInfo;
            this.elements.topTeamCount = this.container.querySelector('#top-team-count');
            this.elements.topTeamCountEn = this.container.querySelector('#top-team-count-en');
        }
        // #########################################
        //  事件绑定模块
        // #########################################
        BindEvents() {
            this.BindHeaderEvents();
            // 模态框关闭
            if (this.elements.closeSummary) {
                this.elements.closeSummary.addEventListener('click', () => this.HideModal('summary'));
            }
            if (this.elements.closeHelp) {
                this.elements.closeHelp.addEventListener('click', () => this.HideModal('help'));
            }
            // 帮助按钮
            if (this.elements.helpBtn) {
                this.AddButtonEventListeners(this.elements.helpBtn, () => this.ShowHelp());
            }
            // 点击模态框背景关闭
            if (this.elements.helpModal) {
                this.elements.helpModal.addEventListener('click', (e) => {
                    if (e.target === this.elements.helpModal) this.HideModal('help');
                });
            }
            // 页面可见性：后台暂停自动刷新
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    if (this.refreshInterval) { clearInterval(this.refreshInterval); this.refreshInterval = null; }
                } else {
                    if (!this.refreshInterval && this.autoRefresh) {
                        this.refreshInterval = setInterval(() => this.LoadData(), 60000);
                    }
                }
            });
        }
        // 绑定header相关事件
        BindHeaderEvents() {
            // 模式切换 - 添加键盘支持（通过querySelectorAll处理所有匹配的按钮）
            // 这样宽屏工具栏和下拉菜单中的按钮都能正常工作
            // 打星模式 - 自定义下拉组件（宽屏）
            this.SetupCustomSelect('star-mode', (value) => {
                this.starMode = parseInt(value);
                this.UpdateRank();
            });
            // 打星模式 - 自定义下拉组件（窄屏下拉菜单）
            this.SetupCustomSelect('star-mode-dropdown', (value) => {
                this.starMode = parseInt(value);
                this.UpdateRank();
            });
            // 刷新按钮 - 添加键盘支持（同时处理宽屏和下拉菜单中的按钮）
            // header 现在在容器外部，使用 document.querySelectorAll
            const refreshButtons = document.querySelectorAll('#refresh-btn');
            refreshButtons.forEach(btn => this.AddButtonEventListeners(btn, () => this.RefreshData()));
            // 统计按钮 - 添加键盘支持（同时处理宽屏和下拉菜单中的按钮）
            const summaryButtons = document.querySelectorAll('#summary-btn');
            summaryButtons.forEach(btn => this.AddButtonEventListeners(btn, () => this.ShowSummary()));
            // 全屏按钮 - 添加键盘支持（同时处理宽屏和下拉菜单中的按钮）
            const fullscreenButtons = document.querySelectorAll('#fullscreen-btn');
            fullscreenButtons.forEach(btn => this.AddButtonEventListeners(btn, () => this.ToggleFullscreen()));
            // 帮助按钮 - 添加键盘支持（同时处理宽屏和下拉菜单中的按钮）
            const helpButtons = document.querySelectorAll('#help-btn');
            helpButtons.forEach(btn => this.AddButtonEventListeners(btn, () => this.ShowHelp()));
            
            // 模式切换按钮（同时处理宽屏和下拉菜单中的按钮）
            const teamRankButtons = document.querySelectorAll('#team-rank-btn');
            teamRankButtons.forEach(btn => this.AddButtonEventListeners(btn, () => this.SwitchMode('team')));
            const schoolRankButtons = document.querySelectorAll('#school-rank-btn');
            schoolRankButtons.forEach(btn => this.AddButtonEventListeners(btn, () => this.SwitchMode('school')));
            // 为按钮添加tooltip
            this.AddButtonTooltips();
            // 时间进度条事件绑定（如果启用，不包含初始化）
            if (this.config.flg_show_time_progress !== false) {
                this.BindTimeProgressEvents();
            }
            // 全屏事件绑定
            this.BindFullscreenEvents();
            
            // 折叠按钮事件处理（header 现在在容器外部）
            const header = this.GetHeaderElement();
            const toggleBtn = header ? header.querySelector('#controls-toggle-btn') : null;
            const dropdown = header ? header.querySelector('#controls-dropdown') : null;
            
            if (toggleBtn && dropdown) {
                // 点击切换下拉菜单
                toggleBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    dropdown.classList.toggle('show');
                });
                
                // 点击外部关闭下拉菜单
                const closeDropdown = (e) => {
                    if (!dropdown.contains(e.target) && e.target !== toggleBtn && !toggleBtn.contains(e.target)) {
                        dropdown.classList.remove('show');
                    }
                };
                document.addEventListener('click', closeDropdown);
                
                // 窗口大小改变时，如果从窄屏变为宽屏，自动关闭下拉菜单
                let resizeTimer = null;
                window.addEventListener('resize', () => {
                    clearTimeout(resizeTimer);
                    resizeTimer = setTimeout(() => {
                        if (window.innerWidth > 1024) {
                            dropdown.classList.remove('show');
                        }
                    }, 100);
                });
            }
            
        }
        // 为div按钮添加事件监听器（点击和键盘）
        AddButtonEventListeners(button, callback) {
            if (!button) return;
            // 点击事件
            button.addEventListener('click', callback);
            // 键盘事件
            button.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    callback();
                }
            });
        }
        // 设置自定义下拉组件
        SetupCustomSelect(selectId, onChange) {
            // header 现在在容器外部，使用 document.querySelector
            const btn = document.querySelector(`#${selectId}-btn`);
            const dropdown = document.querySelector(`#${selectId}-dropdown`);
            if (!btn || !dropdown) return;
            // 按钮点击事件
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.ToggleCustomSelect(selectId);
            });
            // 键盘事件
            btn.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    this.ToggleCustomSelect(selectId);
                }
            });
            // 为自定义选择按钮单独处理tooltip
            btn.addEventListener('mouseover', (e) => {
                const titlecn = btn.getAttribute('title-cn');
                const titleen = btn.getAttribute('title-en');
                if (titlecn || titleen) {
                    // 初始化tooltipTimeouts
                    if (!this.tooltipTimeouts) {
                        this.tooltipTimeouts = {};
                    }
                    // 清除之前的延迟
                    if (this.tooltipTimeouts[btn]) {
                        clearTimeout(this.tooltipTimeouts[btn]);
                    }
                    // 延迟显示tooltip
                    this.tooltipTimeouts[btn] = setTimeout(() => {
                        this.ShowTooltipForElement(btn, titlecn, titleen);
                    }, 300);
                }
            });
            btn.addEventListener('mouseout', (e) => {
                // 清除延迟
                if (this.tooltipTimeouts && this.tooltipTimeouts[btn]) {
                    clearTimeout(this.tooltipTimeouts[btn]);
                }
                // 延迟隐藏tooltip
                setTimeout(() => {
                    this.HideGlobalTooltip();
                }, 100);
            });
            // 选项点击事件
            dropdown.addEventListener('click', (e) => {
                const option = e.target.closest('.custom-select-option');
                if (option) {
                    const value = option.getAttribute('data-value');
                    this.SelectCustomOption(selectId, value, onChange);
                }
            });
            // 点击外部关闭
            document.addEventListener('click', (e) => {
                if (!btn.contains(e.target) && !dropdown.contains(e.target)) {
                    this.CloseCustomSelect(selectId);
                }
            });
        }
        // 切换自定义下拉组件
        ToggleCustomSelect(selectId) {
            const btn = document.querySelector(`#${selectId}-btn`);
            const dropdown = document.querySelector(`#${selectId}-dropdown`);
            if (!btn || !dropdown) return;
            const isOpen = dropdown.classList.contains('show');
            // 关闭所有其他下拉组件
            this.CloseAllCustomSelects();
            if (!isOpen) {
                dropdown.classList.add('show');
                btn.classList.add('active');
            }
        }
        // 选择自定义选项
        SelectCustomOption(selectId, value, onChange) {
            const btn = document.querySelector(`#${selectId}-btn`);
            const dropdown = document.querySelector(`#${selectId}-dropdown`);
            const options = dropdown.querySelectorAll('.custom-select-option');
            if (!btn || !dropdown) return;
            // 更新选中状态
            options.forEach(option => {
                option.classList.remove('selected');
                if (option.getAttribute('data-value') === value) {
                    option.classList.add('selected');
                }
            });
            // 更新按钮显示
            const selectedOption = dropdown.querySelector(`[data-value="${value}"]`);
            if (selectedOption) {
                const iconElement = selectedOption.querySelector('i');
                const icon = iconElement ? iconElement.outerHTML : '';
                const titlecn = selectedOption.getAttribute('title-cn');
                const titleen = selectedOption.getAttribute('title-en');
                // 检查按钮是否带文字（用于下拉菜单）
                const hasText = btn.querySelector('.option-text');
                if (hasText) {
                    // 带文字的按钮：更新图标和文字
                    const optionText = selectedOption.querySelector('.option-text');
                    btn.innerHTML = icon + (optionText ? optionText.outerHTML : '');
                } else {
                    // 仅图标按钮：只更新图标
                    btn.innerHTML = icon;
                }
                btn.setAttribute('title-cn', titlecn);
                btn.setAttribute('title-en', titleen);
            }
            // 关闭下拉菜单
            this.CloseCustomSelect(selectId);
            // 触发回调
            if (onChange) {
                onChange(value);
            }
        }
        // 关闭自定义下拉组件
        CloseCustomSelect(selectId) {
            const btn = document.querySelector(`#${selectId}-btn`);
            const dropdown = document.querySelector(`#${selectId}-dropdown`);
            if (btn) btn.classList.remove('active');
            if (dropdown) dropdown.classList.remove('show');
        }
        // 关闭所有自定义下拉组件
        CloseAllCustomSelects() {
            const allDropdowns = document.querySelectorAll('.custom-select-dropdown');
            const allBtns = document.querySelectorAll('.custom-select-btn');
            allDropdowns.forEach(dropdown => dropdown.classList.remove('show'));
            allBtns.forEach(btn => btn.classList.remove('active'));
        }
        // 为按钮添加tooltip
        AddButtonTooltips() {
            const buttons = [
                this.elements.summaryBtn,
                this.elements.teamRankBtn,
                this.elements.schoolRankBtn,
                this.elements.fullscreenBtn
            ];
            // 不再需要，全局tooltip系统会自动处理所有元素的tooltip
            // 点击模态框背景关闭
            this.elements.summaryModal.addEventListener('click', (e) => {
                if (e.target === this.elements.summaryModal) this.HideModal('summary');
            });
            // 键盘事件
            document.addEventListener('keydown', (e) => this.HandleKeydown(e));
        }
        // #########################################
        //  数据加载和处理模块
        // 用途：通用逻辑（适用于所有功能：队伍榜、学校榜、统计、滚榜）
        // #########################################
        
        OriInit(raw_data) {
            this.data = raw_data;
            // 将 list 格式转换为 dict 格式
            this.ConvertListToDict();
            this.ProcessData();
            this.UpdateAwardInfo();
            if (!this.externalMode) {
                this.UpdatePageTitle();
                // 数据加载完成后，重新创建表头以包含题目列
                this.RecreateHeaderRow();
                // 初始化时间进度条（如果启用）
                if (this.config.flg_show_time_progress !== false) {
                    this.InitializeTimeProgress();
                }
                // 处理 Rank 模式：打星显示模式、队排/滚榜/校排，会执行RenderRank
                this.UpdateRank();
            }
            this.HideLoading();
            this.isInitialLoad = false; // 标记初始加载完成
        }
        async LoadData() {
            try {
                this.ShowLoading();
                const cacheKey = `${this.key}_data_v2`;
                // 如果启用缓存，尝试从缓存加载数据（30秒过期）
                if (this.config.flg_rank_cache) {
                    const cachedData = await this.cache.get(cacheKey);
                    if (cachedData) {
                        this.OriInit(cachedData);
                        return;
                    }
                }
                
                const apiUrl = this.config.api_url;
                
                // 判断 api_url 是数据对象还是 URL 字符串
                // 如果是对象（离线模式，数据已从 data.js 加载），直接使用
                if (typeof apiUrl === 'object' && apiUrl !== null && !Array.isArray(apiUrl)) {
                    // 检查是否包含比赛数据的字段（contest, team, problem, solution）
                    if (apiUrl.contest || apiUrl.team || apiUrl.problem || apiUrl.solution) {
                        // 这是数据对象，直接使用
                        this.data = apiUrl;
                        // 如果启用缓存，使用缓存管理器保存数据，30秒过期
                        if (this.config.flg_rank_cache) {
                            await this.cache.set(cacheKey, this.data, 30 * 1000);
                        }
                        this.OriInit(this.data);
                        this.HideLoading();
                        this.isInitialLoad = false;
                        return;
                    }
                }
                
                // 如果是字符串（URL），使用 fetch 请求
                if (typeof apiUrl === 'string') {
                    const params = {};
                    if (this.config.cid_list) {
                        params.cid = this.config.cid_list; // 假设是字符串或数字
                    }
                    
                    // 处理 info_need 数组（直接定义为数组）
                    params['info_need[]'] = [ // 键名带 []，配合函数内的处理生成正确格式
                        'solution',
                        'team',
                        'problem',
                        'contest'
                    ];
                      
                    const result = await this.GetRequest(apiUrl, params);
                    if (result.code === 1) {
                        this.data = result.data;
                        // 如果启用缓存，使用缓存管理器保存数据，30秒过期
                        if (this.config.flg_rank_cache) {
                            await this.cache.set(cacheKey, this.data, 30 * 1000);
                        }
                        this.OriInit(result.data);
                        this.HideLoading();
                        this.isInitialLoad = false; // 标记初始加载完成
                    } else {
                        this.ShowError(result.msg || '数据加载失败');
                    }
                } else {
                    this.ShowError('无效的 api_url 配置');
                }
            } catch (error) {
                console.error('数据加载错误:', error);
                this.ShowError('网络错误，请检查连接');
            }
        }
        // 将 list 格式转换为 dict 格式
        ConvertListToDict() {
            if (!this.data) return;
            // 使用公共函数进行转换
            RankToolConvertListToDict(this.data);
        }
        
        // ********** 通用逻辑 - 数据处理 **********
        // 用途：处理原始数据，构建基础数据结构
        // 涉及功能：队伍榜、学校榜、统计、滚榜
        // 功能：处理题目映射、提交映射、队伍映射、一血计算、封榜标记（滚榜模式）
        ProcessData(flg_real_rank=false) {
            if (!this.data) return;
            // 预处理数据：统一处理带"#"的ID格式
            this.PreprocessData();
            // 处理题目数据
            this.problemMap = {};
            this.data.problem.forEach(problem => {
                this.problemMap[problem.problem_id] = problem;
            });
            // 处理提交数据
            this.solutionMap = {};
            // 重置一血记录
            this.map_fb = { global: {}, regular: {} };
            
            if (this.data.solution) {
                // 从根源上过滤掉无效的提交结果：
                // 0~3：等待评测或正在评测
                // >=11：无效状态
                // 只保留 result === 4 (AC) 或其他有效的结果状态 (4-10)
                this.data.solution = this.data.solution.filter(solution => {
                    const result = solution.result;
                    // 忽略 0~3 和 >=11 的结果，保留封榜结果
                    return result >= 4 && result < 11 || result < 0;
                });
                
                // 先按时间排序，确保一血计算的准确性
                this.data.solution.sort((a, b) => {
                    const cmp = a.in_date.localeCompare(b.in_date);
                    if (cmp !== 0) return cmp;
                    return (a.solution_id || 0) - (b.solution_id || 0); // 用 solution_id 排序，处理一血同时间多队的情况
                });
                
                this.data.solution.forEach(solution => {
                    const team_id = solution.team_id; // 已经预处理过，直接使用
                    
                    // 如果处于回放模式，只处理回放时间之前的提交
                    if (this.timeReplayMode && this.replayTime) {
                        const submitTime = new Date(solution.in_date);
                        if (submitTime > this.replayTime) {
                            return; // 跳过回放时间之后的提交
                        }
                    }
                    
                    if (!this.solutionMap[team_id]) {
                        this.solutionMap[team_id] = {
                            ac: {},
                            frozen: {},
                            problems: {}
                        };
                    }
                    const problemId = solution.problem_id;
                    const team_solutions = this.solutionMap[team_id];
                    
                    // 如果该题已有AC且AC已揭晓，忽略后续提交，已考虑是否frozen
                    if (team_solutions.ac[problemId]) {                        
                        return;     // 忽略 AC 后的提交
                    }
                    
                    // 初始化problems数组，按时间顺序记录一个队在特定题目的所有提交
                    if (!team_solutions.problems[problemId]) {
                        team_solutions.problems[problemId] = [];
                    }
                    team_solutions.problems[problemId].push(solution);
                    
                    // 判断这次提交是否是frozen
                    if (!flg_real_rank && this.IsFrozen(solution)) {
                        team_solutions.frozen[problemId] = true;
                        // frozen状态的提交，后续可能还有提交，不处理AC逻辑（因为还未揭晓）
                        return;
                    }
                    // 处理AC提交和一血计算
                    // 关键修正：只保留第一次AC的时间（最早的AC）
                    if (solution.result === 4) {
                        team_solutions.ac[problemId] = solution.in_date;
                        // 计算一血（First Blood）- 只用第一次AC计算一血
                        this.UpdateFirstBlood(team_id, problemId, solution.in_date);
                    }
                });
            }
            // 计算排名数据
            this.CalculateRank(flg_real_rank);
        }
        
        // 更新一血记录（在AC提交时调用）
        UpdateFirstBlood(team_id, problemId, in_date) {
            const team = this.teamMap[team_id];
            const isStarTeam = team && team.tkind === 2;
            
            // Global 一血：所有队伍中第一个AC的
            if (!this.map_fb.global[problemId]) {
                this.map_fb.global[problemId] = {
                    team_id: team_id,
                    in_date: in_date,
                    isStarTeam: isStarTeam
                };
            }
            
            // Regular 一血：非打星队伍中第一个AC的
            if (!isStarTeam && !this.map_fb.regular[problemId]) {
                this.map_fb.regular[problemId] = {
                    team_id: team_id,
                    in_date: in_date,
                    isStarTeam: false
                };
            }
        }
        // 预处理数据：统一处理带"#"的ID格式
        PreprocessData() {
            // 处理队伍数据中的team_id
            this.teamMap = {}; //处理队伍数据
            if (this.data.team) {
                this.data.team.forEach(team => {
                    if (team.team_id && team.team_id.startsWith('#')) {
                        // 提取真正的team_id (格式: #cpc1001_A11 -> A11)
                        if (team.team_id.startsWith('#cpc')) {
                            team.team_id = team.team_id.split('_')[1];
                        } else {
                            // 其他格式，直接去掉"#"
                            console.error("team 用户名格式不正确", team)
                        }
                    }
                    // 维护 team_id 到 team 信息的映射
                    this.teamMap[team.team_id] = team;
                });
            }
            // 处理提交数据中的team_id
            if (this.data.solution) {
                this.data.solution.forEach(solution => {
                    if (solution.team_id && solution.team_id.startsWith('#')) {
                        // 提取真正的team_id (格式: #cpc1001_A11 -> A11)
                        if (solution.team_id.startsWith('#cpc')) {
                            solution.team_id = solution.team_id.split('_')[1];
                        } else {
                            console.error("solution 用户名格式不正确", solution)
                        }
                    }
                });
            }
        }
        // ********** 队伍榜 - 排名计算 **********
        // 用途：计算队伍排名，生成 rankList （队伍榜单的基础数据）
        // 涉及功能：队伍榜（主功能）、学校榜（依赖此数据）、统计（依赖此数据）、滚榜（依赖此数据）
        // 功能：计算每队每题状态、解决数、罚时，处理封榜状态（滚榜模式），排序
        CalculateRank(flg_real_rank=false) {
            // 忠于 ProcessData 处理的数据，计算榜单并排序，不理会是否封榜，不处理打星信息
            this.rankList = [];
            for (const team_id in this.teamMap) {
                const team = this.teamMap[team_id];
                const team_solutions = this.solutionMap[team_id] || { ac: {}, frozen: {}, problems: {} };
                let solved = 0;
                let penalty = 0; // seconds
                const problemStats = {};
                // 计算每个题目的状态
                for (const problemId in this.problemMap) {
                    const problem = this.problemMap[problemId];
                    let team_problem_solutions = team_solutions.problems[problemId] || [];
                    let status = 'none';
                    let submitCount = 0;
                    let lastSubmitTime = '';
                    // 检查AC状态（基于过滤后的提交）
                    const validAcTime = team_solutions.ac[problemId];
                    
                    if (validAcTime) {
                        // ac
                        status = 'ac';
                        solved ++;
                        
                        submitCount = team_problem_solutions.length;
                        
                        const acTime = new Date(validAcTime).getTime();
                        const startTime = new Date(this.data.contest.start_time).getTime();
                        const deltaSeconds = Math.floor((acTime - startTime) / 1000);
                        // 基础用时（秒） + 每次错误罚时20分钟（转秒）
                        penalty += deltaSeconds;
                        penalty += (submitCount - 1) * 20 * 60;
                        lastSubmitTime = RankjsFormatSecondsToHMS(deltaSeconds);
                    } else if (team_problem_solutions.length > 0) {
                        // wa 或 pending
                        status = problemId in team_solutions.frozen ? 'pending' : 'wa';
                        submitCount = team_problem_solutions.length;
                        const lastSolution = team_problem_solutions[team_problem_solutions.length - 1];
                        const lastTime = new Date(lastSolution.in_date).getTime();
                        const startTime = new Date(this.data.contest.start_time).getTime();
                        const deltaSeconds = Math.floor((lastTime - startTime) / 1000);
                        lastSubmitTime = RankjsFormatSecondsToHMS(deltaSeconds);
                    } else {
                        // 没有提交
                        submitCount = 0;
                    }
                    problemStats[problemId] = {
                        status,
                        submitCount,
                        lastSubmitTime,
                        problemAlphabetIdx: RankToolGetProblemAlphabetIdx(problem.num)
                    };
                }
                this.rankList.push({
                    item_key: team_id,  // 无论学校排名还是队伍排名，都用队伍做key，学校排名的队伍key就是该校排第一的队伍
                    team_id,
                    team,
                    solved,
                    penalty, // 精确到秒
                    problemStats
                });
            }
            // 排序
            this.rankList.sort((a, b) => this.CompareTeamsForRanking(a, b));
        }
        
        /**
         * 队伍排序比较函数（通用逻辑，供子类复用）
         * 排序规则：solved 降序，penalty 升序，team_id 升序
         * @param {Object} a - 队伍A
         * @param {Object} b - 队伍B
         * @returns {number} 比较结果
         */
        CompareTeamsForRanking(a, b) {
            if (a.solved !== b.solved) return b.solved - a.solved;
            if (a.penalty !== b.penalty) return a.penalty - b.penalty;  // 此处 penalty 精确到秒
            return a.team_id.localeCompare(b.team_id);
        }
        // ********** 通用逻辑 - 榜单更新和渲染 **********
        // 用途：根据当前模式更新榜单显示
        // 涉及功能：队伍榜、学校榜
        // 功能：根据模式选择数据源（rankList/schoolRank），应用打星过滤，触发渲染
        UpdateRank(flg_render=true, starMode=null) {
            // 基于新一轮计算的 rankList 数据，更新 rank 的 渲染
            if (!this.rankList.length) {
                return [];
            }
            let displayList;
            // 普通模式使用原始数据
            const filteredList = this.FilterByStarMode(this.rankList, starMode);
            // ********** 学校榜 - 学校排名计算 **********
            displayList = this.currentMode === 'school' ? 
                this.CalculateSchoolRank(filteredList) : filteredList;
            if(flg_render) {
                // 如果已有行，尝试增量更新；否则全量渲染
                if (this.elements.rankGrid && this.elements.rankGrid.children.length > 0) {
                    this.IncrementalUpdate(displayList);
                } else {
                    this.RenderRank(displayList);
                }
            }
            return displayList;
        }
        // #########################################
        //  排名计算和渲染模块
        // #########################################
        // 行级增量更新：基于ID匹配的增量更新
        async IncrementalUpdate(list) {
            // 🔥 模拟模式：跳过DOM操作和动画
            if (this.isSimulating) {
                return;
            }
            
            // 真实模式：执行DOM更新和动画
            const grid = this.elements.rankGrid;
            const rankedList = this.CalculateRankInfo(list);
            // 获取当前页面中所有存在的队伍ID
            const existingItemKeys = new Set();
            const existingRows = grid.querySelectorAll('.rank-row');
            existingRows.forEach(row => {
                const item_key = row.getAttribute('data-row-id');
                if (item_key) existingItemKeys.add(item_key);
            });
            // 获取新列表中的队伍ID
            const newItemKeys = new Set(rankedList.map(item => String(item.item_key)));
            
            // 1. 更新已存在的队伍
            let updatedCount = 0;
            let createdCount = 0;
            const rankUpdates = []; // 存储需要延迟更新的排名信息（仅在滚榜排序时使用）
            // 检查是否在滚榜排序中（由子类 RankRollSystem 提供 currentRollStep）
            const isRollSorting = this.currentRollStep === 'sort';
            
            if (isRollSorting) {
                // 滚榜排序：立即更新排名和背景色（包括排名），然后执行位置动画
                // 关键修复：先更新所有排名和背景色，再执行动画，确保观众立即看到排名变化
                for (let i = 0; i < rankedList.length; i++) {
                    const item = rankedList[i];
                    const item_key = String(item.item_key);
                    if (existingItemKeys.has(item_key)) {
                        // 队伍存在，立即更新所有内容（包括排名和背景色）
                        await this.UpdateRankRow(item, item.displayRank, i);
                        updatedCount++;
                    } else {
                        // 队伍不存在，创建新行
                        const newRow = await this.CreateRankRow(item, item.displayRank, i);
                        grid.appendChild(newRow);
                        createdCount++;
                    }
                }
            } else {
                // 普通更新：正常更新所有内容（包括排名）
                for (let i = 0; i < rankedList.length; i++) {
                    const item = rankedList[i];
                    const item_key = String(item.item_key);
                    if (existingItemKeys.has(item_key)) {
                        // 队伍存在，更新内容
                        await this.UpdateRankRow(item, item.displayRank, i);
                        updatedCount++;
                    } else {
                        // 队伍不存在，创建新行
                        const newRow = await this.CreateRankRow(item, item.displayRank, i);
                        grid.appendChild(newRow);
                        createdCount++;
                    }
                }
            }
            
            // 2. 删除不存在的队伍
            let removedCount = 0;
            existingItemKeys.forEach(item_key => {
                if (!newItemKeys.has(item_key)) {
                    const rowToRemove = document.getElementById(`rank-grid-${item_key}`);
                    if (rowToRemove) {
                        rowToRemove.remove();
                        removedCount++;
                    }
                }
            });
            
            // 3. 执行排序动画
            // 关键修复：滚榜排序时排名已在上面更新，这里只需要执行位置动画
            if (isRollSorting) {
                // 滚榜排序：排名已更新，只执行位置动画
                await this.AnimateRankSort(rankedList, () => {
                    // 动画完成回调：恢复正在揭晓队伍的z-index
                    if (this.judgingTeamId) {
                        const judgingRow = document.getElementById(`rank-grid-${this.judgingTeamId}`);
                        if (judgingRow && judgingRow.style.zIndex === '99') {
                            judgingRow.style.zIndex = '';
                        }
                    }
                });
            } else {
                // 普通更新：正常执行动画（可能没有位置变化）
                await this.AnimateRankSort(rankedList);
            }
        }
        // 更新排名行（不更新排名数字）
        async UpdateRankRowWithoutRank(item, index) {
            const team_id = item.team.team_id;
            const row = document.getElementById(`rank-grid-${team_id}`);
            if (!row) {
                return;
            }
            // 更新行样式（但不更新排名相关的样式）
            row.className = `rank-row ${index % 2 === 0 ? 'even' : 'odd'}`;
            // 更新解题数
            const solveCell = row.querySelector('.solve-item .problem-label');
            if (solveCell) {
                solveCell.textContent = item.solved;
            }
            // 更新解题数tooltip
            const solveItem = row.querySelector('.solve-item');
            if (solveItem) {
                solveItem.setAttribute('title-cn', `解题数：${item.solved}`);
                solveItem.setAttribute('title-en', `Solved: ${item.solved}`);
            }
            // 更新罚时
            const penaltyBrief = row.querySelector('.penalty-time-brief');
            const penaltyFull = row.querySelector('.penalty-time-full');
            if (penaltyBrief) penaltyBrief.textContent = RankjsFormatSecondsToMinutes(item.penalty);
            if (penaltyFull) penaltyFull.textContent = RankjsFormatSecondsToHMS(item.penalty);
            // 更新罚时tooltip
            const penaltyItem = row.querySelector('.penalty-item');
            if (penaltyItem) {
                penaltyItem.setAttribute('title-cn', `罚时：${RankjsFormatSecondsToMinutes(item.penalty)} 分钟（${RankjsFormatSecondsToHMS(item.penalty)}）`);
                penaltyItem.setAttribute('title-en', `Penalty: ${RankjsFormatSecondsToMinutes(item.penalty)} min (${RankjsFormatSecondsToHMS(item.penalty)})`);
            }
            // 更新题目组
            const problemGroup = row.querySelector('.problem-group');
            if (problemGroup) {
                problemGroup.innerHTML = this.CreateProblemGroup(item.problemStats, item);
            }
        }
        // 只更新排名数字
        UpdateRankNumber(item, rank, index) {
            const team_id = item.team.team_id;
            const row = document.getElementById(`rank-grid-${team_id}`);
            if (!row) {
                return;
            }
            // 检查排名变化（使用order属性判断）
            const rankCell = row.querySelector('.rank-item');
            if (!rankCell) {
                return;
            }
            const rankNumberElement = rankCell.querySelector('.rank-number');
            const oldOrder = parseInt(rankNumberElement?.getAttribute('order') || '0');
            const newOrder = item?.displayOrder || 0;
            const rankChanged = oldOrder !== newOrder && oldOrder > 0; // oldOrder > 0 确保不是初次创建
            
            const rankDisplay = item.isStar ? '*' : rank;
            const displayOrder = item?.displayOrder;
            const rankClass = this.GetRankClass(rank);
            
            // // 更新排名显示
            // if (rankChanged) {
            //     // 排名变化，添加特殊动画
            //     row.classList.add('rank-changed');
            //     if (newOrder < oldOrder) {
            //         row.classList.add('rank-improved');
            //     } else if (newOrder > oldOrder) {
            //         row.classList.add('rank-declined');
            //     }
            //     // 排名数字变化动画
            //     const rankNumber = rankCell.querySelector('.rank-number');
            //     if (rankNumber) {
            //         rankNumber.style.transform = 'scale(1.2)';
            //         rankNumber.style.color = newOrder < oldOrder ? '#10b981' : '#f59e0b';
            //     }
            // }
            
            rankCell.innerHTML = `${this.GetRankEmoji(rankClass)}<span class="rank-number" order="${displayOrder}">${rankDisplay}</span>`;
            rankCell.className = `rank-item ${rankClass}`;
            
            // // 清理动画类（0.5秒后清理）
            // if (rankChanged) {
            //     setTimeout(() => {
            //         row.classList.remove('rank-changed', 'rank-improved', 'rank-declined');
            //         const rankNumber = rankCell.querySelector('.rank-number');
            //         if (rankNumber) {
            //             rankNumber.style.transform = '';
            //             rankNumber.style.color = '';
            //         }
            //     }, 500);
            // }
        }
        // 全新的动画系统：流畅的排名变化动画
        async AnimateRankSort(rankedList, onComplete = null) {
            const grid = this.elements.rankGrid;
            // 强制重排，确保DOM状态稳定
            grid.offsetHeight;
            // 重新获取最新的DOM状态（IncrementalUpdate后）
            const currentRows = Array.from(grid.querySelectorAll('.rank-row'));
            if (currentRows.length === 0) {
                if (onComplete) onComplete();
                return;
            }
            // 1. 记录当前所有行的位置和状态（基于最新DOM）
            const currentPositions = new Map();
            const rowHeight = currentRows[0]?.getBoundingClientRect().height || 0;
            currentRows.forEach((row, index) => {
                const item_key = row.getAttribute('data-row-id');
                const rect = row.getBoundingClientRect();
                currentPositions.set(item_key, {
                    element: row,
                    currentIndex: index,
                    top: rect.top,
                    height: rect.height
                });
            });
            // 2. 计算目标位置映射
            const targetPositions = new Map();
            rankedList.forEach((item, index) => {
                const item_key = String(item.item_key);
                const currentPos = currentPositions.get(item_key);
                if (currentPos) {
                    targetPositions.set(item_key, {
                        newIndex: index,
                        targetTop: index * rowHeight,
                        element: currentPos.element
                    });
                }
            });
            // 3. 执行一次性动画
            await this.ExecuteOneTimeAnimation(currentPositions, targetPositions, rankedList, onComplete);
        }
        async ExecuteOneTimeAnimation(currentPositions, targetPositions, rankedList, onComplete = null) {
            const grid = this.elements.rankGrid;
            // 1. 创建目标DOM结构（按rankedList顺序）
            const sortedRows = [];
            const matchedCount = { count: 0 };
            rankedList.forEach((item, index) => {
                const item_key = String(item.item_key);
                const currentPos = currentPositions.get(item_key);
                if (currentPos) {
                    sortedRows.push(currentPos.element);
                    matchedCount.count++;
                } else {
                }
            });
            // 2. 直接使用CSG动画库，让它处理FLIP逻辑
            await this.ExecuteBulkAnimation([], sortedRows, grid, onComplete);
        }
        // 使用CSG动画库的智能排序动画（标准榜单模式，不需要处理上升队伍）
        async ExecuteBulkAnimation(movements, sortedRows, grid, onComplete = null) {
            // 计算动画持续时间（rollSpeedMultiplier 由子类 RankRollSystem 提供，默认为1.0）
            const speedMultiplier = this.rollSpeedMultiplier || 1.0;
            const animationDuration = RankToolCalculateAnimationDuration(this.baseAnimationDuration, speedMultiplier, this.minAnimationDuration, this.maxAnimationDuration);
            // 1. 提取排序后的itemKey顺序
            const order = sortedRows.map(row => row.getAttribute('data-row-id')).filter(Boolean);
            // 2. 使用CSG动画库的智能排序动画（标准榜单：固定duration + 队列管理，不处理上升队伍）
            await window.CSGAnim.sortAnimate(grid, order, {
                duration: animationDuration,
                speedMultiplier: speedMultiplier,
                easing: window.CSGAnim.getEasing('smooth'),
                useFlip: true,
                queue: true,
                cancelPrevious: true,
                // 标准榜单：不启用上升队伍相关功能
                useSpeedBasedDuration: false,
                mergeAnimations: false,
                risingTeamIds: [],
                onStart: () => {
                },
                onComplete: () => {
                    this.FinalizeBulkAnimation([], sortedRows, grid);
                    if (onComplete) {
                        onComplete();
                    }
                }
            });
        }
        // 完成批量动画
        FinalizeBulkAnimation(animationData, sortedRows, grid) {
            // 清理动画状态
            animationData.forEach(data => {
                // 注释掉上升下降动效的class移除，提高滚榜流畅性
                // data.element.classList.remove('rank-animating', 'rank-moving-up', 'rank-moving-down');
                data.element.style.transform = '';
                data.element.style.transition = '';
            });
        }
        FilterByStarMode(list, starMode=null) {
            // 根据打星模式过滤队伍或调整队伍标记
            const tmp_star_mode = starMode === null ? this.starMode : starMode;
            return list.filter(item => {
                const team = item.team;
                if (team.tkind === 2) { // 处理打星队
                    if (tmp_star_mode === 0) item.isStar = true;    // 打星不排名
                    if (tmp_star_mode === 1) return false;          // 不含打星，过滤掉
                    if (tmp_star_mode === 2) item.isStar = false;   // 打星参与排名
                }
                return true;
            });
        }
        // 计算具体名次（考虑并列、打星） - 公共逻辑
        CalculateRankInfo(list) {
            let currentOrder = 0;
            let currentRank = 0;
            let schoolCntSet = new Set();
            let currentSchoolCntOrder = 0; // 不考虑并列，队伍前面不重复学校个数
            let currentSchoolCntNow = 0;   // 考虑并列，队伍前面不重复学校个数
            let lastSolved = -1;
            let lastPenalty = -1;
            return list.map((item, index) => {
                if (!item.isStar) {
                    currentOrder++;
                    const school = item?.team?.school ?? "";
                    if(!schoolCntSet.has(school)) {
                        schoolCntSet.add(school);
                        currentSchoolCntOrder++;
                    }
                    const penalty_for_rank = Math.floor(item.penalty / 60 + 0.00000001); // 按分钟取整，以此排名
                    if (item.solved !== lastSolved || penalty_for_rank !== lastPenalty) {
                        currentRank = currentOrder;
                        currentSchoolCntNow = currentSchoolCntOrder;
                    }
                    lastSolved = item.solved;
                    lastPenalty = penalty_for_rank;
                }
                item.displayRank = item.isStar ? '*' : currentRank;
                item.displayOrder = currentOrder; // 如果打星，就会出现重复的order，但这刚好可以作为打星队的近似排名
                item.displayIdx = index + 1;
                item.displaySchoolCntNow = item.isStar ? '*' : currentSchoolCntNow;
                item.displaySchoolCntOrder = currentSchoolCntOrder;
                return item;
            });
        }
        // ********** 学校榜 - 学校排名计算 **********
        // 用途：将队伍排名聚合为学校排名
        // 涉及功能：学校榜（主功能）
        // 功能：按学校分组、选择每校前N支队伍、合并题目状态、计算学校总解决数和罚时、排序
        CalculateSchoolRank(teamList) {
            // 从已经 filter 并根据模式标记好 isStar 的排名中计算学校排名
            const schoolMap = {};
            // 按 学校 分组
            teamList.forEach(item => {
                const school = item.team.school;
                if (!schoolMap[school]) {
                    schoolMap[school] = [];
                }
                schoolMap[school].push(item);
            });
            // 计算 学校 排名
            const schoolList = [];
            for (const school in schoolMap) {
                const teams = schoolMap[school];
                teams.sort((a, b) => {
                    if (a.solved !== b.solved) return b.solved - a.solved;
                    return a.penalty - b.penalty;
                });
                // 根据打星模式处理队伍选择
                let selectedTeams = [];
                // 打星不排名或不含打星：优先选择regular队 （此处以 isStar 区分，已涵盖 starMode 的定义）
                const regularTeams = teams.filter(team => team.team?.isStar);
                const starTeams = teams.filter(team => !team.team?.isStar);
                if (regularTeams.length > 0) {
                    // 有regular队，只取regular队
                    selectedTeams = regularTeams.slice(0, this.GetTopTeamCount());
                } else {
                    // 全是打星队，则使用所有打星队
                    selectedTeams = starTeams.slice(0, this.GetTopTeamCount());
                }
                let totalSolved = 0;
                let totalPenalty = 0;
                const mergedProblems = {};
                selectedTeams.forEach(team => {
                    totalSolved += team.solved;
                    totalPenalty += team.penalty;
                    // 合并题目状态
                    for (const problemId in team.problemStats) {
                        const stats = team.problemStats[problemId];
                        if (!mergedProblems[problemId]) {
                            mergedProblems[problemId] = {
                                status: 'none',
                                submitCount: 0,
                                lastSubmitTime: '',
                                problemAlphabetIdx: stats.problemAlphabetIdx
                            };
                        }
                        const merged = mergedProblems[problemId];
                        merged.submitCount += stats.submitCount;
                        // school rank 的每个题目格子显示该校多个队伍这道题最晚的一次最接近ac的提交
                        // 优先级：ac > pending > wa > none
                        if (stats.status === 'ac' || 
                            (stats.status === 'pending' && merged.status !== 'ac') ||
                            (stats.status === 'wa' && merged.status === 'none')) {
                            if(stats.status != merged.status || stats.lastSubmitTime > merged.lastSubmitTime) {
                                merged.status = stats.status;
                                merged.lastSubmitTime = stats.lastSubmitTime;
                            }
                        }
                    }
                });
                const top_item = selectedTeams[0];
                schoolList.push({
                    item_key: top_item.team.team_id, // 用top team 的 team_id 作为这个 school row 的key
                    school,
                    solved: totalSolved,
                    penalty: totalPenalty,
                    problemStats: mergedProblems,
                    top_team: top_item,
                    team: top_item?.team,
                    isStar: top_item?.isStar ?? false,
                    teamCount: selectedTeams.length
                });
            }
            // 按成绩排序
            schoolList.sort((a, b) => {
                if (a.solved !== b.solved) return b.solved - a.solved;
                return a.penalty - b.penalty;
            });
            return schoolList;
        }
        GetTopTeamCount() {
            // 直接使用 data.contest.topteam 字段
            return this.data?.contest?.topteam || 1;
        }
        async RenderRank(list) {
            const grid = this.elements.rankGrid;
            grid.innerHTML = '';
            // 使用公共逻辑计算排名信息
            const rankedList = this.CalculateRankInfo(list);
            // 分帧渲染，避免卡顿
            const batchSize = 200;
            let i = 0;
            const total = rankedList.length;
            const renderBatch = async () => {
                const frag = document.createDocumentFragment();
                let count = 0;
                while (i < total && count < batchSize) {
                    const item = rankedList[i];
                    try {
                        const row = await this.CreateRankRow(item, item.displayRank, i);
                        if (row && row.nodeType === Node.ELEMENT_NODE) {
                            frag.appendChild(row);
                        } else {
                            console.error('CreateRankRow returned invalid node:', row);
                        }
                    } catch (error) {
                        console.error('Error creating rank row:', error);
                    }
                    i++; count++;
                }
                grid.appendChild(frag);
                if (i < total) {
                    requestAnimationFrame(renderBatch);
                } else {
                    const lastRow = grid.querySelector('.rank-row:last-child');
                    if (lastRow) lastRow.style.borderRadius = '0 0 8px 8px';
                    // 渲染完成后，重新观察旗帜图标和校徽图标
                    this.ReobserveFlags();
                    this.ReobserveLogos();
                }
            };
            await renderBatch();
        }
        // 绑定图标tooltip
        BindIconTooltips(row) {
            const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
            const setupIconTooltip = (icon) => {
                if (!icon) return;
                const tooltipKey = icon.getAttribute('tooltip-key');
                const titleCn = icon.getAttribute('title-cn');
                const titleEn = icon.getAttribute('title-en');
                const nameElement = icon.nextElementSibling;
                const name = nameElement ? nameElement.textContent.trim() : '';
                if (!tooltipKey || !name) return;
                const template = this.tooltipTemplates[tooltipKey];
                if (!template) return;
                const cnText = template.cn.replace('{name}', name);
                const enText = template.en.replace('{name}', name);
                if (isMobile) {
                    icon.addEventListener('click', (e) => {
                        e.stopPropagation();
                        this.ShowTooltipForElement(icon, cnText, enText);
                    });
                } else {
                    icon.addEventListener('mouseenter', () => {
                        this.ShowTooltipForElement(icon, cnText, enText);
                    });
                    icon.addEventListener('mouseleave', () => {
                        this.HideGlobalTooltip();
                    });
                }
            };
            // 绑定教练和选手图标
            const coachIcon = row.querySelector('.coach-icon');
            const playerIcon = row.querySelector('.player-icon');
            setupIconTooltip(coachIcon);
            setupIconTooltip(playerIcon);
        }
        // BindNamePopovers方法已移除，现在统一使用title-cn和title-en属性
        CreateHeaderRow() {
            const headerRow = document.createElement('div');
            headerRow.className = 'rank-header-row';
            // 设置表头z-index为100000，确保始终在最上层
            headerRow.style.zIndex = '100000';
            // 基础列 - 使用与CreateRankContainer相同的格式
            let headerHtml = `
                <div class="rank-col rank-col-rank"><div class="header-cell">${this.CreateBilingualText('排名', 'Rank')}</div></div>
                <div class="rank-col rank-col-solve"><div class="header-cell">${this.CreateBilingualText('题数', 'Solved')}</div></div>
                <div class="rank-col rank-col-penalty"><div class="header-cell">${this.CreateBilingualText('罚时', 'Penalty')}</div></div>
            `;
            headerHtml += '<div class="pro-header-group">\n';
            // 添加题目列
            if (this.data && this.data.problem) {
                // ********** 统计 - 题目统计 **********
                const problemStats = this.CalculateProblemStats();
                this.data.problem.forEach(problem => {
                    const problemAlphabetIdx = RankToolGetProblemAlphabetIdx(problem.num);
                    const stats = problemStats[problem.problem_id] || { 
                        ac: 0, 
                        total: 0, 
                        acTeams: 0, 
                        totalTeams: 0 
                    };
                    const color = RankToolParseColor(problem.color);
                    
                    // 生成tooltip文本：显示两套统计数据
                    const tooltipCn = `AC队伍数：${stats.acTeams} / 总提交队伍数：${stats.totalTeams}\nAC提交数：${stats.ac} / 总提交数：${stats.total}`;
                    const tooltipEn = `AC Teams: ${stats.acTeams} / Total Tried Teams: ${stats.totalTeams}\nAC Submissions: ${stats.ac} / Total Submissions: ${stats.total}`;
                    
                    headerHtml += `
                        <div class="rank-col rank-col-problem" style="--rank-problem-color: ${color}">
                            <div class="problem-header-color-bg">
                                <i class="bi bi-balloon-fill" title-cn="${color}"></i>
                            </div>
                            <div class="problem-header-content" ${RankToolGenerateBilingualAttributes(tooltipCn, tooltipEn)}>
                                <div class="problem-header-title">${problemAlphabetIdx}</div>
                                <div class="problem-header-stats" >
                                    ${stats.acTeams}/${stats.totalTeams}
                                </div>
                            </div>
                        </div>
                    `;
                });
            }
            headerHtml += '</div>\n';
            headerRow.innerHTML = headerHtml;
            return headerRow;
        }
        async CreateRankRow(item, rank, index) {
            const row = document.createElement('div');
            row.className = `rank-row ${index % 2 === 0 ? 'even' : 'odd'}`;
            row.setAttribute('data-row-id', item.item_key);
            row.id = `rank-grid-${item.item_key}`;
            const rankDisplay = item.isStar ? '*' : rank;
            const displayOrder = item?.displayOrder;
            const rankClass = this.GetRankClass(rank);
            // 顶部跨列信息： 学校/组织 名与国家副标题
            const schoolName = RankToolEscapeHtml(item.team?.school || item.school || '');
            const region = RankToolEscapeHtml(item.team?.region || item.region || '');
            const flagBase = this.config.region_flag_url || '/static/image/region_flag';
            // 在生成HTML时判断旗帜是否存在
            let flagDisplay = 'none';
            let regionTextDisplay = 'none';
            if (region) {
                try {
                    const mapping = await this.LoadFlagMapping();
                    if (mapping.has(region.trim())) {
                        // 旗帜存在，显示旗帜
                        flagDisplay = 'inline-block';
                    } else {
                        // 旗帜不存在，显示地区名
                        regionTextDisplay = 'flex';
                    }
                } catch (error) {
                    console.error('Error checking flag mapping:', error);
                    // 出错时显示地区名
                    regionTextDisplay = 'flex';
                }
            }
            // 队伍信息（已移除，使用新的HTML结构）
                row.innerHTML = `
                    <div class="rank-main-content">
                        <!-- 前三列背景区域，横跨三列纵跨两行 -->
                        <div class="school-logo" data-school="${schoolName}"></div>
                        <div class="top-section">
                            ${this.CreateCoachPlayerSection(item.team, item)}
                            <div class="team-info-section">
                                <div class="team-info">
                                    <div class="team-type-icon">${this.GetTeamTypeIcon(item.team?.tkind || 0)}</div>
                                    ${region ? `<img class="flag-icon" data-flag="${region}" alt="${region}" title="${region}" style="display: ${flagDisplay}; opacity: 0;" onload="this.style.opacity='1'" onerror="this.style.opacity='0'">` : ''}
                                    ${this.CreateSchoolName(schoolName, item.team)}
                                    <div class="team-names" ${(() => {
                                        const tooltip = this.CreateTeamNamesTooltip(item.team);
                                        return RankToolGenerateBilingualAttributes(tooltip.titleCn, tooltip.titleEn);
                                    })()}>
                                        ${this.CreateTeamNameCn(item.team)}
                                        ${this.CreateTeamNameEn(item.team)}
                                        ${this.currentMode === 'school' ? `
                                            <div class="team-watermark">
                                                <div class="watermark-text-cn">第一队</div>
                                                <div class="watermark-text-en">Top Team</div>
                                            </div>
                                        ` : ''}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="stats-section">
                            <div class="rank-col rank-col-rank">
                                <div class="rank-item ${rankClass}">
                                    ${this.GetRankEmoji(rankClass)}
                                    <span class="rank-number" order="${displayOrder}">${rankDisplay}</span>
                                </div>
                            </div>
                            <div class="rank-col rank-col-solve">
                                <div class="solve-item" ${RankToolGenerateBilingualAttributes(`解题数：${item.solved}`, `Solved: ${item.solved}`)}>
                                    <div class="problem-content">
                                        <span class="problem-label">${item.solved}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="rank-col rank-col-penalty">
                                <div class="penalty-item" ${RankToolGenerateBilingualAttributes(`罚时：${RankjsFormatSecondsToMinutes(item.penalty)} 分钟（${RankjsFormatSecondsToHMS(item.penalty)}）`, `Penalty: ${RankjsFormatSecondsToMinutes(item.penalty)} min (${RankjsFormatSecondsToHMS(item.penalty)})`)}>
                                    <div class="penalty-content">
                                        <span class="penalty-time-brief">${RankjsFormatSecondsToMinutes(item.penalty)}</span>
                                        <span class="penalty-time-full">${RankjsFormatSecondsToHMS(item.penalty)}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="problem-group">
                                ${this.CreateProblemGroup(item.problemStats, item)}
                            </div>
                        </div>
                    </div>
                `;
            // 懒加载校徽背景到前三列背景区域
            const firstThreeColsBg = row.querySelector('.school-logo');
            if (firstThreeColsBg) {
                const school = schoolName;
                if (school) {
                    this.LoadSchoolLogoBackground(firstThreeColsBg, school);
                }
            }
            // 懒加载旗帜（与校徽类似）
            const flagImg = row.querySelector('img.flag-icon');
            if (flagImg && !flagImg.getAttribute('src')) {
                const code = flagImg.getAttribute('data-flag');
                this.LazyLoadFlag(flagImg, flagBase, code);
            }
            // BindNamePopovers已移除，现在统一使用title-cn和title-en属性
            // 绑定图标tooltip
            this.BindIconTooltips(row);
            return row;
        }
        // 获取排名行的className（保留滚榜相关类）
        GetRowClassName(index, row = null) {
            // 基础类名
            let className = `rank-row ${index % 2 === 0 ? 'even' : 'odd'}`;
            
            // 如果提供了row元素，检查并保留滚榜相关的类
            if (row) {
                // 保留roll-judging类（正在揭晓的队伍）
                if (row.classList.contains('roll-judging')) {
                    className += ' roll-judging';
                }
            }
            
            return className;
        }
        // 更新排名行内容（与CreateRankRow逻辑对应）
        async UpdateRankRow(item, rank, index) {
            const team_id = item.team.team_id;
            const row = document.getElementById(`rank-grid-${team_id}`);
            if (!row) {
                return;
            }
            // // 添加更新动画类
            // row.classList.add('rank-updating');
            // 检查排名变化（使用order属性判断）
            const rankNumberElement = row.querySelector('.rank-number');
            const oldOrder = parseInt(rankNumberElement?.getAttribute('order') || '0');
            const newOrder = item?.displayOrder || 0;
            // const rankChanged = oldOrder !== newOrder && oldOrder > 0; // oldOrder > 0 确保不是初次创建
            // if (rankChanged) {
            //     // 排名变化，添加特殊动画
            //     row.classList.add('rank-changed');
            //     if (newOrder < oldOrder) {
            //         row.classList.add('rank-improved');
            //     } else if (newOrder > oldOrder) {
            //         row.classList.add('rank-declined');
            //     }
            // }
            // 更新行样式（使用GetRowClassName保留滚榜相关类）
            row.className = this.GetRowClassName(index, row);
            const rankDisplay = item.isStar ? '*' : rank;
            const displayOrder = item?.displayOrder;
            const rankClass = this.GetRankClass(rank);
            // 更新排名显示
            const rankCell = row.querySelector('.rank-item');
            if (rankCell) {
                // // 排名数字变化动画
                // if (rankChanged) {
                //     const rankNumber = rankCell.querySelector('.rank-number');
                //     if (rankNumber) {
                //         rankNumber.style.transform = 'scale(1.2)';
                //         rankNumber.style.color = newOrder < oldOrder ? '#10b981' : '#f59e0b';
                //     }
                // }
                rankCell.innerHTML = `${this.GetRankEmoji(rankClass)}<span class="rank-number" order="${displayOrder}">${rankDisplay}</span>`;
                rankCell.className = `rank-item ${rankClass}`;
            }
            // 更新解题数
            const solveCell = row.querySelector('.solve-item .problem-label');
            if (solveCell) {
                solveCell.textContent = item.solved;
            }
            // 更新解题数tooltip
            const solveItem = row.querySelector('.solve-item');
            if (solveItem) {
                solveItem.setAttribute('title-cn', `解题数：${item.solved}`);
                solveItem.setAttribute('title-en', `Solved: ${item.solved}`);
            }
            // 更新罚时
            const penaltyBrief = row.querySelector('.penalty-time-brief');
            const penaltyFull = row.querySelector('.penalty-time-full');
            if (penaltyBrief) penaltyBrief.textContent = RankjsFormatSecondsToMinutes(item.penalty);
            if (penaltyFull) penaltyFull.textContent = RankjsFormatSecondsToHMS(item.penalty);
            // 更新罚时tooltip
            const penaltyItem = row.querySelector('.penalty-item');
            if (penaltyItem) {
                penaltyItem.setAttribute('title-cn', `罚时：${RankjsFormatSecondsToMinutes(item.penalty)} 分钟（${RankjsFormatSecondsToHMS(item.penalty)}）`);
                penaltyItem.setAttribute('title-en', `Penalty: ${RankjsFormatSecondsToMinutes(item.penalty)} min (${RankjsFormatSecondsToHMS(item.penalty)})`);
            }
            // 更新题目组
            const problemGroup = row.querySelector('.problem-group');
            if (problemGroup) {
                problemGroup.innerHTML = this.CreateProblemGroup(item.problemStats, item);
            }
            // // 清理动画类（0.5秒后清理）
            // setTimeout(() => {
            //     row.classList.remove('rank-updating', 'rank-changed', 'rank-improved', 'rank-declined');
            //     const rankNumber = row.querySelector('.rank-number');
            //     if (rankNumber) {
            //         rankNumber.style.transform = '';
            //         rankNumber.style.color = '';
            //     }
            // }, 500);
        }
        // 通用动态加载方法
        LoadImageElement(element, identifier, attributeName, observerProperty, initMethod) {
            if (!identifier) return;
            // 设置标识符
            element.setAttribute(attributeName, identifier);
            // 确保观察器已初始化
            if (!this[observerProperty]) {
                this[initMethod]();
            }
            // 观察元素（避免重复观察）
            if (!element.dataset.observed) {
                this[observerProperty].observe(element);
                element.dataset.observed = 'true';
            }
        }
        // 旗帜懒加载（使用通用方案）
        LazyLoadFlag(img, base, code) {
            this.LoadImageElement(img, code, 'data-flag', '_flagObserver', 'InitFlagLoader');
        }
        // 显示旗帜（当旗帜加载成功时）
        ShowFlag(img) {
            // 确保旗帜显示
            img.style.display = 'inline-block';
            // 确保地区名被隐藏
            const regionInfo = img.closest('.region-info');
            if (regionInfo) {
                const regionText = regionInfo.querySelector('.region-text');
                if (regionText) {
                    regionText.style.display = 'none';
                }
            }
        }
        // 显示地区名（当旗帜加载失败时）
        ShowRegionText(img, code) {
            // 确保旗帜隐藏
            img.style.display = 'none';
            // 确保地区名显示
            const regionInfo = img.closest('.region-info');
            if (regionInfo) {
                const regionText = regionInfo.querySelector('.region-text');
                if (regionText) {
                    regionText.style.display = 'flex';
                }
            }
        }
        // 检查字符串是否为空（已迁移到 rank_tool.js）
        IsEmptyString(str) {
            return RankToolIsEmptyString(str);
        }
        // 生成校名HTML
        CreateSchoolName(schoolName, team = null) {
            // 检查是否有team_id需要显示
            const hasTeamId = this.config.flg_show_team_id && team && team.team_id;
            const teamId = hasTeamId ? RankToolEscapeHtml(team.team_id) : '';
            const isEmpty = RankToolIsEmptyString(schoolName);
            
            // 构建基础title（根据校名是否为空）
            let titleCn = isEmpty ? "校名：缺失" : `学校/组织：${schoolName}`;
            let titleEn = isEmpty ? "School Name: Missing" : `School/Organization: ${schoolName}`;
            
            // 如果有team_id，在title前加上ID信息
            if (hasTeamId) {
                titleCn = `ID: ${teamId}\n${titleCn}`;
                titleEn = `ID: ${teamId}\n${titleEn}`;
            }
            
            // 构建显示内容：如果有校名且有team_id，在校名前添加team_id
            let schoolNameContent = isEmpty ? '' : schoolName;
            if (!isEmpty && hasTeamId) {
                schoolNameContent = `<span class="team-id-display">${teamId}</span><span class="team-id-separator"> | </span>${schoolName}`;
            }
            
            // 返回HTML（校名为空时添加placeholder类）
            const placeholderClass = isEmpty ? ' tinfo-placeholder' : '';
            return `<div class="school-name${placeholderClass}" title-cn="${titleCn}" title-en="${titleEn}">${schoolNameContent}</div>`;
        }
        // 生成主队名HTML
        CreateTeamNameCn(team) {
            const nameCn = RankToolEscapeHtml(team.name || '');
            const nameEn = RankToolEscapeHtml(team.name_en || '');
            const isEmptyCn = RankToolIsEmptyString(nameCn);
            const isEmptyEn = RankToolIsEmptyString(nameEn);
            if (isEmptyCn && isEmptyEn) {
                return ``;
            }
            if (isEmptyCn) {
                return `<div class="team-name-cn tinfo-placeholder"></div>`;
            }
            return `<div class="team-name-cn">${nameCn}</div>`;
        }
        // 生成副队名HTML
        CreateTeamNameEn(team) {
            const nameCn = RankToolEscapeHtml(team.name || '');
            const nameEn = RankToolEscapeHtml(team.name_en || '');
            const isEmptyCn = RankToolIsEmptyString(nameCn);
            const isEmptyEn = RankToolIsEmptyString(nameEn);
            if (isEmptyCn && isEmptyEn) {
                return ``;
            }
            if (isEmptyEn) {
                return `<div class="team-name-en tinfo-placeholder"></div>`;
            }
            return `<div class="team-name-en">${nameEn}</div>`;
        }
        // 生成队伍名称区域的tooltip内容
        CreateTeamNamesTooltip(team) {
            const nameCn = RankToolEscapeHtml(team.name || '');
            const nameEn = RankToolEscapeHtml(team.name_en || '');
            const isEmptyCn = RankToolIsEmptyString(team.name);
            const isEmptyEn = RankToolIsEmptyString(team.name_en);
            let titleCn = '';
            let titleEn = '';
            if (isEmptyCn && isEmptyEn) {
                titleCn = '队名缺失';
                titleEn = 'Secondary Language Name Missing';
            } else if (isEmptyCn) {
                titleCn = '队名缺失';
                titleEn = `Secondary Language Name：${nameEn}`;
            } else if (isEmptyEn) {
                titleCn = `队名：${nameCn}`;
                titleEn = 'Secondary Language Name Missing';
            } else {
                titleCn = `队名：${nameCn}`;
                titleEn = `Secondary Language Name：${nameEn}`;
            }
            return { titleCn, titleEn };
        }
        // 生成教练信息HTML
        CreateCoachInfo(team) {
            const coachName = RankToolEscapeHtml(team.coach || '');
            const isEmpty = RankToolIsEmptyString(team.coach);
            if (isEmpty) {
                return `<div class="coach-info tinfo-placeholder">
                    <span class="coach-icon" tooltip-key="coach" title-cn="教练" title-en="Coach">${RankToolGenerateIcon('coach')}</span>
                    <span class="coach-name tinfo-placeholder" title-cn="教练：缺失" title-en="Coach: Missing"></span>
                </div>`;
            }
            return `<div class="coach-info">
                <span class="coach-icon" tooltip-key="coach" title-cn="教练" title-en="Coach">${RankToolGenerateIcon('coach')}</span>
                <span class="coach-name" title-cn="教练：${coachName}" title-en="Coach: ${coachName}">${coachName}</span>
            </div>`;
        }
        // 生成选手信息HTML
        CreatePlayerInfo(team) {
            const playerName = RankToolEscapeHtml(team.tmember || '');
            const isEmpty = RankToolIsEmptyString(team.tmember);
            if (isEmpty) {
                return `<div class="player-info tinfo-placeholder">
                    <span class="player-icon" tooltip-key="player" title-cn="选手" title-en="Player">${RankToolGenerateIcon('player')}</span>
                    <span class="player-name tinfo-placeholder" title-cn="选手：缺失" title-en="Player: Missing"></span>
                </div>`;
            }
            return `<div class="player-info">
                <span class="player-icon" tooltip-key="player" title-cn="选手" title-en="Player">${RankToolGenerateIcon('player')}</span>
                <span class="player-name" title-cn="选手：${playerName}" title-en="Player: ${playerName}">${playerName}</span>
            </div>`;
        }
        // 生成教练选手区域HTML
        CreateCoachPlayerSection(team, item = null) {
            // 学校排名模式下显示队伍信息合计
            if (this.currentMode === 'school' && item) {
                const topTeamCount = this.GetTopTeamCount();
                const actualTeamCount = item.teamCount || 0;
                let summaryTextCn, summaryTextEn;
                if (topTeamCount === 1) {
                    // 如果只需要1队，显示"第1队"
                    summaryTextCn = '计入第 1 队';
                    summaryTextEn = '1st Team Counted';
                } else if (actualTeamCount >= topTeamCount) {
                    // 有足够队伍，显示"前X队合计"
                    summaryTextCn = `前 ${topTeamCount} 队合计`;
                    summaryTextEn = `Top ${topTeamCount} Teams Counted`;
                } else {
                    // 队伍不足，显示"全部X队合计"
                    summaryTextCn = `全部 ${actualTeamCount} 队合计`;
                    summaryTextEn = `All ${actualTeamCount} Teams Counted`;
                }
                return `<div class="coach-player-section school-summary">
                    <div class="team-summary-info">
                        <div class="summary-text-cn">${summaryTextCn}</div>
                        <div class="summary-text-en">${summaryTextEn}</div>
                    </div>
                </div>`;
            }
            // 队伍排名模式下显示教练和队员信息
            const hasCoach = !RankToolIsEmptyString(team.coach);
            const hasPlayer = !RankToolIsEmptyString(team.tmember);
            return `<div class="coach-player-section">
                ${this.CreateCoachInfo(team)}
                ${this.CreatePlayerInfo(team)}
            </div>`;
        }
        CreateSchoolInfo(item) {
            // 检查数据是否存在
            if (!item || !item.problemStats) {
                return `
                    <div class="team-name-cn">${RankToolEscapeHtml(item?.school || '')}</div>
                `;
            }
            return `
                <div class="team-name-cn">${RankToolEscapeHtml(item.school)}</div>
            `;
        }
        CreateProblemGroup(problemStats, item = null) {
            let html = '';
            // 检查problemMap是否存在且不为空
            if (!this.problemMap || Object.keys(this.problemMap).length === 0) {
                return '<div class="problem-group"><!-- 题目数据加载中 --></div>';
            }
            const problemIds = Object.keys(this.problemMap).sort((a, b) => 
                this.problemMap[a].num - this.problemMap[b].num
            );
            problemIds.forEach(problemId => {
                const stats = problemStats[problemId] || {
                    status: 'none',
                    submitCount: 0,
                    lastSubmitTime: '',
                    problemAlphabetIdx: RankToolGetProblemAlphabetIdx(this.problemMap[problemId].num)
                };
                // 计算总分钟数：将 HH:MM:SS 转换为总分钟数
                let briefMinute = '';
                if (stats.lastSubmitTime && /^(\d{1,2}:)?\d{1,2}:\d{2}$/.test(stats.lastSubmitTime)) {
                    const timeParts = stats.lastSubmitTime.split(':');
                    let totalMinutes = 0;
                    if (timeParts.length === 3) {
                        // HH:MM:SS 格式
                        const hours = parseInt(timeParts[0]) || 0;
                        const minutes = parseInt(timeParts[1]) || 0;
                        totalMinutes = hours * 60 + minutes;
                    } else if (timeParts.length === 2) {
                        // MM:SS 格式
                        totalMinutes = parseInt(timeParts[0]) || 0;
                    }
                    briefMinute = totalMinutes + "'";
                }
                // 生成简化的tooltip内容
                const statusText = stats.status === 'ac' ? '已通过' : 
                                stats.status === 'wa' ? '未通过' : '未知';
                const statusTextEn = stats.status === 'ac' ? 'Accepted' : 
                                    stats.status === 'wa' ? 'Wrong Answer' : 'Unknown';
                // 智能显示分隔符：只要有提交次数>0且有最后提交时间就显示分隔符（与榜单逻辑保持一致）
                const shouldShowSeparator = (stats.submitCount > 0) && (stats.lastSubmitTime && stats.lastSubmitTime.trim() !== '');
                const separatorHtml = shouldShowSeparator ? '<span class="problem-separator">|</span>' : '';
     
                // 检查一血状态
                let isGlobalFirstBlood = false;
                let isRegularFirstBlood = false;
                
                if (this.currentMode === 'school') {
                    // 学校排名模式：检查一血队伍的学校是否与当前item的学校相同
                    const globalFirstBloodTeam = this.map_fb?.global?.[problemId];
                    const regularFirstBloodTeam = this.map_fb?.regular?.[problemId];
                    
                    if (globalFirstBloodTeam) {
                        const firstBloodTeam = this.teamMap[globalFirstBloodTeam.team_id];
                        isGlobalFirstBlood = firstBloodTeam?.school === item?.school;
                    }
                    
                    if (regularFirstBloodTeam) {
                        const firstBloodTeam = this.teamMap[regularFirstBloodTeam.team_id];
                        isRegularFirstBlood = firstBloodTeam?.school === item?.school;
                    }
                } else {
                    // 队伍排名模式：直接比较team_id
                    isGlobalFirstBlood = this.map_fb?.global?.[problemId]?.team_id === item?.team_id;
                    isRegularFirstBlood = this.map_fb?.regular?.[problemId]?.team_id === item?.team_id;
                }
                
                // 构建一血相关的CSS类
                let firstBloodClasses = '';
                if (isRegularFirstBlood) {
                    firstBloodClasses += ' pro-first-blood-regular';
                }
                if (isGlobalFirstBlood) {
                    firstBloodClasses += ' pro-first-blood-global';
                }
                
                html += `
                    <div class="rank-col rank-col-problem">
                        <div class="${this.GetProblemStatusClass(stats)}${firstBloodClasses}" 
                            d-pro-idx="${stats.problemAlphabetIdx}"
                            d-sub-cnt="${stats.submitCount || 0}"
                            d-last-sub="${this.GetLastSubmitTimeDisplay(stats)}">
                            <div class="problem-content">
                                <span class="pro-submit-cnt">${this.GetSubmitCountDisplay(stats)}</span>
                                ${separatorHtml}
                                <span class="time-brief">${briefMinute}</span>
                                <span class="time-full">${this.GetLastSubmitTimeDisplay(stats)}</span>
                            </div>
                        </div>
                    </div>
                `;
            });
            return html;
        }
        GetTeamTypeIcon(tkind, flg_render=true) {
            const iconKeyMap = {
                0: 'team-regular',    // 常规队
                1: 'team-girl',         // 女队
                2: 'team-star'          // 打星队
            };
            const iconKey = iconKeyMap[tkind] || 'team-regular';
            const labels = {
                0: { cn: '常规队', en: 'Regular' },
                1: { cn: '女队', en: 'Girl' },
                2: { cn: '打星队', en: 'Star' }
            };
            const label = labels[tkind] || labels[0];
            const iconHtml = RankToolGenerateIcon(iconKey, label.cn, label.en);
            if(flg_render) {
                return `<span class="team-type-icon ${iconKey.replace('team-', '')}">${iconHtml}</span>`;
            } else {
                return {
                    iconKeyMap,
                    iconKey,
                    labels,
                    label,
                    iconHtml
                }
            }
        }
        GetRankClass(rank) {
            if (rank === '*') return 'star';
            if (rank <= this.rankGold) return 'gold';
            if (rank <= this.rankSilver) return 'silver';
            if (rank <= this.rankBronze) return 'bronze';
            return '';
        }
        // 获取排名对应的奖牌emoji
        GetRankEmoji(rankClass) {
            switch (rankClass) {
                case 'gold':
                    return '<span class="rank-emoji">🥇</span>';
                case 'silver':
                    return '<span class="rank-emoji">🥈</span>';
                case 'bronze':
                    return '<span class="rank-emoji">🥉</span>';
                case 'star':
                    return '<span class="rank-emoji">⭐</span>';
                default:
                    return '';
            }
        }
        // 解析奖牌比例数据（已迁移到 rank_tool.js）
        ParseAwardRatio(awardRatio) {
            return RankToolParseAwardRatio(awardRatio);
        }
        // 验证奖牌比例（已迁移到 rank_tool.js）
        ValidateAwardRatio(ratio) {
            return RankToolValidateAwardRatio(ratio);
        }
        // ********** 通用逻辑 - 获奖信息计算 **********
        // 用途：计算金、银、铜牌名次线
        // 涉及功能：队伍榜、学校榜、统计（用于显示获奖信息）
        // 功能：解析获奖比例、计算有效队伍数、计算各奖项名次线
        GetAwardRanks(options = {}) {
            const {
                flg_ac_team_base = true,     // 是否以总数为基数
                customBaseCount = null,    // 自定义基数（优先级最高）
                starMode = null
            } = options;
            if (!this.data || !this.data.contest) {
                console.error("数据未初始化");
                return [0, 0, 0];
            }
            const awardRatio = this.data.contest.award_ratio;
            const ratios = RankToolParseAwardRatio(awardRatio);
            const tmp_star_mode = starMode ? starMode : this.starMode;
            
            // 先调用 FilterByStarMode 设置 isStar 属性，然后再计算有效队伍数
            // FilterByStarMode 会根据 starMode 设置 isStar 属性或过滤掉打星队
            const filteredList = this.FilterByStarMode(this.rankList, tmp_star_mode);
            
            // 获取有效队伍数（排除打星队和0题队伍）
            // 注意：starMode === 1 时，打星队已被 FilterByStarMode 过滤掉，所以这里只需要检查 isStar
            // starMode === 0 时，打星队 isStar=true，会被排除
            // starMode === 2 时，打星队 isStar=false，会被计入
            const validTeamNum = customBaseCount ? customBaseCount : 
                (flg_ac_team_base ? 
                    filteredList.filter(item => item.solved > 0 && !item.isStar):
                    filteredList.filter(item => !item.isStar)
                ).length;
            return RankToolGetAwardRank(validTeamNum, ratios.gold, ratios.silver, ratios.bronze);
        }
        UpdateAwardInfo() {
            const awardRank = this.GetAwardRanks();
            this.rankGold = awardRank.rankGold;
            this.rankSilver = awardRank.rankSilver;
            this.rankBronze = awardRank.rankBronze;
        }
        // 将题目编号转换为字母标识（已迁移到 rank_tool.js）
        GetProblemAlphabetIdx(problemNum) {
            return RankToolGetProblemAlphabetIdx(problemNum);
        }
        // ==========================================
        // 外部调用接口 (Outer API)
        // ==========================================
        // 获取比赛信息
        OuterGetContest() {
            return this.data?.contest || null;
        }
        // 获取队伍列表
        OuterGetTeams() {
            return this.data?.team || [];
        }
        // 获取题目列表
        OuterGetProblems() {
            return this.data?.problem || [];
        }
        // 获取提交记录
        OuterGetSolutions() {
            return this.data?.solution || [];
        }
        // 获取排名列表
        OuterGetRankList(starMode=null) {
            // 针对 starMode 进行 filter，不提供则基于 this.starMode
            const ret_rank_list = this.UpdateRank(false, starMode); // false 表示不执行 render，用于外部调用
            // 针对 starMode 计算实际显示的排名
            return this.CalculateRankInfo(ret_rank_list);
        }
        // 获取获奖比例
        OuterGetAwardRatio() {
            if (!this.data?.contest?.award_ratio) return null;
            return RankToolParseAwardRatio(this.data.contest.award_ratio);
        }
        // 检查数据是否已加载
        OuterIsDataLoaded() {
            return this.data !== null;
        }
        // 获取提交次数显示文本
        GetSubmitCountDisplay(stats) {
            // 如果没有尝试过（submitCount为0），显示题号
            if (!stats.submitCount || stats.submitCount === 0) {
                return stats.problemAlphabetIdx || '?';
            }
            return stats.submitCount;
        }
        // 获取最后提交时间显示文本
        GetLastSubmitTimeDisplay(stats) {
            return stats.lastSubmitTime || '';
        }
        // 获取题目状态类名
        GetProblemStatusClass(stats) {
            return `problem-item pro-${stats.status}`;
        }
        // #########################################
        //  时间回放功能模块
        // #########################################
        // 绑定时间进度条事件
        BindTimeProgressEvents() {
            // header 现在在容器外部
            const header = this.GetHeaderElement();
            const slider = header ? header.querySelector('#time-progress-slider') : null;
            const resetBtn = header ? header.querySelector('#time-reset-btn') : null;
            if (!slider || !resetBtn) return;
            
            // 滑块拖动事件（实时更新显示）
            slider.addEventListener('input', (e) => {
                const progress = parseFloat(e.target.value);
                this.SetReplayTime(progress);
                this.UpdateTimeDisplay();
                this.UpdateTimeProgressTrack();
            });
            
            // 滑块拖动开始事件（进入回放模式，停止自动更新）
            slider.addEventListener('mousedown', () => {
                this.StopTimeProgressAutoUpdate();
            });
            // 触摸设备支持
            slider.addEventListener('touchstart', () => {
                this.StopTimeProgressAutoUpdate();
            });
            
            // 处理滑块拖动结束逻辑
            const handleSliderEnd = (progress) => {
                this.SetReplayTime(progress);
                
                // 检查是否拖动到比当前时间晚的位置
                const totalDuration = this.contestEndTime - this.contestStartTime;
                const progressTime = new Date(this.contestStartTime.getTime() + (progress / 100) * totalDuration);
                const actualCurrentTime = this.GetActualCurrentTime();
                
                if (progressTime >= actualCurrentTime || progress >= 100) {
                    // 如果拖动到当前时间或之后，重置到最新时间
                    this.ResetTimeReplay();
                } else {
                    // 否则进入回放模式
                    // 先设置回放模式标志，这样 UpdateTimeDisplay 才能正确显示回放时间
                    this.timeReplayMode = true;
                    // 直接调用 ApplyTimeReplay，它会先快速更新UI，然后异步处理数据
                    this.ApplyTimeReplay();
                }
            };
            
            // 滑块拖动结束事件（鼠标）
            slider.addEventListener('change', (e) => {
                const progress = parseFloat(e.target.value);
                handleSliderEnd(progress);
            });
            
            // 触摸设备拖动结束事件
            slider.addEventListener('touchend', (e) => {
                const progress = parseFloat(e.target.value);
                handleSliderEnd(progress);
            });
            
            // 重置按钮事件
            resetBtn.addEventListener('click', () => {
                this.ResetTimeReplay();
            });
        }
        // 获取实际当前时间（前端时间 + 后端时间差）
        GetActualCurrentTime() {
            return new Date(new Date().getTime() + this.backendTimeDiff);
        }
        
        // 初始化时间进度条
        InitializeTimeProgress() {
            if (!this.data || !this.data.contest) return;
            this.contestStartTime = new Date(this.data.contest.start_time);
            this.contestEndTime = new Date(this.data.contest.end_time);
            const totalDuration = this.contestEndTime - this.contestStartTime;
            
            // 更新总时间显示（header 现在在容器外部）
            const header = this.GetHeaderElement();
            const totalTimeSpan = header ? header.querySelector('#time-progress-total') : null;
            if (totalTimeSpan) {
                totalTimeSpan.textContent = RankToolFormatDuration(totalDuration);
            }
            
            // 更新进度条位置和时间显示
            this.UpdateTimeProgress();
            
            // 启动自动更新定时器（每秒更新）
            this.StartTimeProgressAutoUpdate();
        }
        
        // 更新进度条位置和时间显示
        UpdateTimeProgress() {
            if (!this.contestStartTime || !this.contestEndTime) return;
            
            // 如果处于回放模式，不自动更新
            if (this.timeReplayMode) return;
            
            const totalDuration = this.contestEndTime - this.contestStartTime;
            const actualCurrentTime = this.GetActualCurrentTime();
            
            // 计算已过时间：早于开始时间为0，晚于结束时间为总时长
            let elapsedTime = 0;
            if (actualCurrentTime < this.contestStartTime) {
                elapsedTime = 0;
            } else if (actualCurrentTime > this.contestEndTime) {
                elapsedTime = totalDuration;
            } else {
                elapsedTime = actualCurrentTime - this.contestStartTime;
            }
            
            // 更新滑块位置（header 现在在容器外部）
            const header = this.GetHeaderElement();
            const slider = header ? header.querySelector('#time-progress-slider') : null;
            if (slider) {
                const progress = Math.min(100, Math.max(0, (elapsedTime / totalDuration) * 100));
                slider.value = progress;
            }
            
            // 更新时间显示
            this.UpdateTimeDisplay();
            
            // 更新进度条背景色
            this.UpdateTimeProgressTrack();
        }
        
        // 更新进度条背景色（基于实际当前时间，而不是滑块位置）
        UpdateTimeProgressTrack() {
            // header 现在在容器外部
            const header = this.GetHeaderElement();
            const track = header ? header.querySelector('.time-progress-track') : null;
            const slider = header ? header.querySelector('#time-progress-slider') : null;
            if (!track || !slider) return;
            
            if (!this.contestStartTime || !this.contestEndTime) return;
            
            const actualCurrentTime = this.GetActualCurrentTime();
            const totalDuration = this.contestEndTime - this.contestStartTime;
            
            // 计算实际当前时间对应的进度百分比
            let currentProgress = 0;
            if (actualCurrentTime < this.contestStartTime) {
                currentProgress = 0;
            } else if (actualCurrentTime > this.contestEndTime) {
                currentProgress = 100;
            } else {
                const elapsedTime = actualCurrentTime - this.contestStartTime;
                currentProgress = Math.min(100, Math.max(0, (elapsedTime / totalDuration) * 100));
            }
            
            // 设置背景色的宽度为当前时间对应的进度
            track.style.width = `${currentProgress}%`;
            track.style.opacity = '1';
            
            // 轮廓始终覆盖整个进度条，无需调整位置
        }
        
        // 启动时间进度条自动更新
        StartTimeProgressAutoUpdate() {
            // 清除已有定时器
            this.StopTimeProgressAutoUpdate();
            // 每秒更新一次
            this.timeProgressInterval = setInterval(() => {
                if (!this.timeReplayMode) {
                    this.UpdateTimeProgress();
                }
            }, 1000);
        }
        
        // 停止时间进度条自动更新
        StopTimeProgressAutoUpdate() {
            if (this.timeProgressInterval) {
                clearInterval(this.timeProgressInterval);
                this.timeProgressInterval = null;
            }
        }
        // 设置回放时间
        SetReplayTime(progress) {
            if (!this.contestStartTime || !this.contestEndTime) return;
            const totalDuration = this.contestEndTime - this.contestStartTime;
            const replayDuration = (progress / 100) * totalDuration;
            this.replayTime = new Date(this.contestStartTime.getTime() + replayDuration);
        }
        // 更新时间显示
        UpdateTimeDisplay() {
            // header 现在在容器外部
            const header = this.GetHeaderElement();
            const currentTimeSpan = header ? header.querySelector('#time-progress-current') : null;
            if (!currentTimeSpan) return;
            
            // 根据回放模式添加/移除样式类，用于视觉区分
            if (this.timeReplayMode && this.replayTime) {
                // 回放模式：显示回放时间，添加回放样式类
                const elapsedTime = this.replayTime - this.contestStartTime;
                currentTimeSpan.textContent = RankToolFormatDuration(elapsedTime);
                currentTimeSpan.classList.add('time-replay-mode');
            } else {
                // 正常模式：显示实际当前时间，移除回放样式类
                const actualCurrentTime = this.GetActualCurrentTime();
                const totalDuration = this.contestEndTime - this.contestStartTime;
                let elapsedTime = 0;
                if (actualCurrentTime < this.contestStartTime) {
                    elapsedTime = 0;
                } else if (actualCurrentTime > this.contestEndTime) {
                    elapsedTime = totalDuration;
                } else {
                    elapsedTime = actualCurrentTime - this.contestStartTime;
                }
                currentTimeSpan.textContent = RankToolFormatDuration(elapsedTime);
                currentTimeSpan.classList.remove('time-replay-mode');
            }
        }
        // 应用时间回放
        ApplyTimeReplay() {
            // timeReplayMode 已在调用前设置，这里只确保状态正确
            if (!this.timeReplayMode) {
                this.timeReplayMode = true;
            }
            // 确保停止自动更新
            this.StopTimeProgressAutoUpdate();
            
            // 先快速更新UI（时间显示和进度条），让用户立即看到反馈
            this.UpdateTimeDisplay();
            this.UpdateTimeProgressTrack();
            
            // 然后进行耗时的数据处理和榜单更新（异步执行，不阻塞UI）
            // 使用 requestAnimationFrame 或 setTimeout 让浏览器先渲染UI更新
            requestAnimationFrame(() => {
                // 重新处理数据（包括重新计算一血），只处理回放时间之前的提交
                this.ProcessData();
                // 更新header统计信息（根据回放时间更新题目统计）
                this.RecreateHeaderRow();
                this.UpdateRank();
            });
        }
        // 重置时间回放
        ResetTimeReplay() {
            const start = new Date().getTime()
            this.timeReplayMode = false;
            this.replayTime = null;
            
            // 先快速更新UI（进度条和时间显示），让用户立即看到反馈
            this.UpdateTimeProgress();
            
            // 重新启动自动更新
            this.StartTimeProgressAutoUpdate();
            
            // 然后进行耗时的数据处理和榜单更新（异步执行，不阻塞UI）
            requestAnimationFrame(() => {
                // 重新处理数据（包括重新计算一血），处理所有提交
                this.ProcessData();
                // 更新header统计信息（恢复为所有提交的统计）
                this.RecreateHeaderRow();
                this.UpdateRank();
            });
        }
        // 格式化持续时间（已迁移到 rank_tool.js）
        FormatDuration(milliseconds) {
            return RankToolFormatDuration(milliseconds);
        }
        // 计算动画持续时间（已迁移到 rank_tool.js）
        CalculateAnimationDuration(baseDuration = null) {
            const base = baseDuration !== null ? baseDuration : this.baseAnimationDuration;
            const speedMultiplier = this.rollSpeedMultiplier || 1.0; // rollSpeedMultiplier 由子类 RankRollSystem 提供
            return RankToolCalculateAnimationDuration(base, speedMultiplier, this.minAnimationDuration, this.maxAnimationDuration);
        }
        // 平滑滚动到底部（带加减速动画，有仪式感）
        // 用途：启动滚榜时，以优雅的方式滚动到榜单底部
        // #########################################
        //  工具方法和辅助功能模块
        // #########################################
        // ********** 统计 - 题目统计计算 **********
        // 用途：计算每个题目的提交统计（AC、WA、TLE等）
        // 涉及功能：统计（主功能）、队伍榜（表头显示统计）
        // 功能：统计每题的总提交数、各结果类型的提交数、参与提交的队伍数
        CalculateProblemStats() {
            const problemStats = {};
            if (!this.data || !this.data.problem || !this.data.solution) {
                return problemStats;
            }
            // 初始化所有题目的统计
            this.data.problem.forEach(problem => {
                problemStats[problem.problem_id] = {
                    ac: 0,              // AC提交数
                    total: 0,           // 总提交数
                    acTeams: 0,         // AC队伍数
                    totalTeams: 0       // 总提交队伍数
                };
            });
            
            // 用于记录每个题目的队伍集合（用于统计队伍数）
            const teamSets = {};
            const acTeamSets = {};
            this.data.problem.forEach(problem => {
                teamSets[problem.problem_id] = new Set();
                acTeamSets[problem.problem_id] = new Set();
            });
            
            // 统计每个题目的提交情况
            this.data.solution.forEach(solution => {
                const problemId = solution.problem_id;
                if (!problemStats[problemId]) return;
                
                // 如果处于回放模式，只统计回放时间之前的提交
                if (this.timeReplayMode && this.replayTime) {
                    const submitTime = new Date(solution.in_date);
                    if (submitTime > this.replayTime) {
                        return; // 跳过回放时间之后的提交
                    }
                }
                
                const teamId = solution.team_id;
                
                // 统计提交数（原来的逻辑）
                problemStats[problemId].total++;
                if (solution.result === 4) { // AC
                    problemStats[problemId].ac++;
                    // 记录AC的队伍
                    acTeamSets[problemId].add(teamId);
                }
                
                // 记录尝试过该题目的队伍（无论是否AC）
                teamSets[problemId].add(teamId);
            });
            
            // 计算队伍数
            this.data.problem.forEach(problem => {
                const problemId = problem.problem_id;
                problemStats[problemId].acTeams = acTeamSets[problemId].size;
                problemStats[problemId].totalTeams = teamSets[problemId].size;
            });
            
            return problemStats;
        }
        // 解析颜色（已迁移到 rank_tool.js）
        ParseColor(colorString) {
            return RankToolParseColor(colorString);
        }
        // 重新创建表头（数据加载完成后调用）
        RecreateHeaderRow() {
            const existingHeaderRow = this.container.querySelector('.rank-header-row');
            if (existingHeaderRow) {
                const newHeaderRow = this.CreateHeaderRow();
                existingHeaderRow.parentNode.replaceChild(newHeaderRow, existingHeaderRow);
            }
        }
        // 创建美观的鼠标悬停提示信息
        CreateTooltipTitle(titlecn, titleen) {
            if (!titlecn && !titleen) return '';
            let tooltip = '';
            if (titlecn && titleen) {
                tooltip = `${titlecn}\n${titleen}`;
            } else if (titlecn) {
                tooltip = titlecn;
            } else if (titleen) {
                tooltip = titleen;
            }
            return tooltip;
        }
        // 生成双语title属性（已迁移到 rank_tool.js）
        GenerateBilingualTitle(titlecn, titleen) {
            return RankToolGenerateBilingualTitle(titlecn, titleen);
        }
        // 生成双语HTML属性（已迁移到 rank_tool.js）
        GenerateBilingualAttributes(titlecn, titleen) {
            return RankToolGenerateBilingualAttributes(titlecn, titleen);
        }
        // 专用tooltip处理函数：problem-item
        GenerateProblemItemTooltip(element) {
            // 从class中提取状态信息
            const classList = element.classList;
            let status = 'none';
            let statusText = '未知';
            let statusTextEn = 'Unknown';
            if (classList.contains('pro-ac')) {
                status = 'ac';
                statusText = '已通过';
                statusTextEn = 'Accepted';
            } else if (classList.contains('pro-wa')) {
                status = 'wa';
                statusText = '未通过';
                statusTextEn = 'Wrong Answer';
            } else if (classList.contains('pro-pending')) {
                status = 'pending';
                statusText = '等待或封榜';
                statusTextEn = 'Pending or Frozen';
            }
            // 从data属性中提取信息
            const problemAlphabetIdx = element.getAttribute('d-pro-idx') || '?';
            const submitCount = element.getAttribute('d-sub-cnt') || '0';
            const lastSubmitTime = element.getAttribute('d-last-sub') || '';
            // 生成tooltip内容
            const titlecn = `题目${problemAlphabetIdx}: ${statusText}，${submitCount}次提交, 最后提交: ${lastSubmitTime || '无'}`;
            const titleen = `Problem ${problemAlphabetIdx}: ${statusTextEn}, ${submitCount} submission(s), Last submit: ${lastSubmitTime || 'None'}`;
            return { titlecn, titleen };
        }
        // 检查元素是否有专用tooltip处理函数
        HasSpecialTooltipHandler(element) {
            // 检查元素是否有注册的专用处理函数
            for (const className in this.specialTooltipHandlers) {
                if (element.classList.contains(className)) {
                    return className;
                }
            }
            return null;
        }
        // 获取专用tooltip内容
        GetSpecialTooltipContent(element, handlerKey) {
            const handler = this.specialTooltipHandlers[handlerKey];
            if (handler) {
                return handler(element);
            }
            return null;
        }
        // 确保tooltip可用（智能重建）
        EnsureTooltipReady() {
            // 如果tooltip不存在，直接创建
            if (!this.globalTooltip) {
                this.CreateTooltip();
                return;
            }
            
            // 检查tooltip是否正常（通过getBoundingClientRect判断）
            this.globalTooltip.style.display = 'block';
            this.globalTooltip.style.visibility = 'visible';
            this.globalTooltip.style.opacity = '1';
            this.globalTooltip.style.left = '0px';
            this.globalTooltip.style.top = '0px';
            
            // 强制重新计算布局
            this.globalTooltip.offsetHeight;
            this.globalTooltip.offsetWidth;
            
            const rect = this.globalTooltip.getBoundingClientRect();
            
            // 如果尺寸为0，说明tooltip状态异常，需要重建
            if (rect.width === 0 && rect.height === 0) {
                this.DestroyTooltip();
                this.CreateTooltip();
            }
        }
        
        // 创建tooltip
        CreateTooltip() {
            this.globalTooltip = document.createElement('div');
            this.globalTooltip.className = 'tooltip tooltip-top';
            this.globalTooltip.style.position = 'fixed';
            this.globalTooltip.style.zIndex = '100009';
            this.globalTooltip.style.display = 'none';
            
            // 创建tooltip内容结构
            this.globalTooltipContent = document.createElement('div');
            this.globalTooltipContent.className = 'tooltip-content';
            this.globalTooltip.appendChild(this.globalTooltipContent);
            
            // 添加到container
            this.container.appendChild(this.globalTooltip);
        }
        
        // 销毁tooltip
        DestroyTooltip() {
            if (this.globalTooltip && this.globalTooltip.parentNode) {
                this.globalTooltip.parentNode.removeChild(this.globalTooltip);
            }
            this.globalTooltip = null;
            this.globalTooltipContent = null;
        }
        // 显示tooltip到指定元素
        ShowTooltipForElement(element, titlecn, titleen, event = null) {
            if (!titlecn && !titleen) return;
            
            // 确保tooltip可用（智能重建）
            this.EnsureTooltipReady();
            
            // 更新内容
            this.globalTooltipContent.innerHTML = '';
            if (titlecn) {
                const cnDiv = document.createElement('div');
                cnDiv.className = 'tooltip-cn';
                cnDiv.textContent = titlecn;
                this.globalTooltipContent.appendChild(cnDiv);
            }
            if (titleen) {
                const enDiv = document.createElement('div');
                enDiv.className = 'tooltip-en';
                enDiv.textContent = titleen;
                this.globalTooltipContent.appendChild(enDiv);
            }
            
            // 显示tooltip
            this.globalTooltip.style.display = 'block';
            this.globalTooltip.style.opacity = '1';
            this.globalTooltip.style.visibility = 'visible';
            this.globalTooltip.classList.add('show');
            
            // 计算并设置位置
            this.UpdateTooltipPosition(this.globalTooltip, element, event);
        }
        // 隐藏tooltip
        HideGlobalTooltip() {
            if (this.globalTooltip) {
                this.globalTooltip.style.display = 'none';
                this.globalTooltip.classList.remove('show');
            }
        }
        // 检测是否为可点击的功能对象
        IsClickableElement(element) {
            // 检查是否在controls-toolbar内
            const toolbar = element.closest('.controls-toolbar');
            if (toolbar) return true;
            // 检查是否有点击事件监听器
            const hasClickHandler = element.onclick || 
                element.getAttribute('onclick') || 
                element.classList.contains('control-btn') ||
                element.classList.contains('custom-select-btn') ||
                element.classList.contains('toolbar-item');
            return hasClickHandler;
        }
        // 显示tooltip (保留兼容性)
        ShowTooltip(tooltip, element) {
            if (this.globalTooltip) {
                this.globalTooltip.classList.add('show');
                this.UpdateTooltipPosition(this.globalTooltip, element);
            }
        }
        // 隐藏tooltip (保留兼容性)
        HideTooltip(tooltip) {
            if (this.globalTooltip) {
                this.globalTooltip.classList.remove('show');
            }
        }
        // 更新tooltip位置
        UpdateTooltipPosition(tooltip, element, event = null) {
            if (!tooltip) return;
            
            // 获取tooltip尺寸
            const tooltipRect = tooltip.getBoundingClientRect();
            const viewport = { width: window.innerWidth, height: window.innerHeight };
            
            // 计算位置
            this.CalculateTooltipPosition(tooltip, element, event, tooltipRect, viewport);
        }
        // 计算tooltip位置的核心逻辑
        CalculateTooltipPosition(tooltip, element, event, tooltipRect, viewport) {
            let position = 'top';
            let left;
            let top;
            // 优先使用鼠标位置，如果没有则使用元素位置
            if (event && event.clientX !== undefined && event.clientY !== undefined) {
                // 默认：tooltip在鼠标上方，箭头指向鼠标
                // 箭头在tooltip底部中央，所以tooltip的left应该是鼠标X - tooltip宽度的一半
                left = event.clientX - tooltipRect.width / 2;
                top = event.clientY - tooltipRect.height - 12; // 鼠标上方12px
            } else {
                // 回退到元素位置
                const rect = element.getBoundingClientRect();
                left = rect.left + rect.width / 2 - tooltipRect.width / 2;
                top = rect.top - tooltipRect.height - 12;
            }
            // 边界检测和调整
            // 检查上边界
            if (top < 10) {
                position = 'bottom';
                if (event && event.clientY !== undefined) {
                    // tooltip在鼠标下方，箭头在tooltip顶部中央指向鼠标
                    top = event.clientY + 12; // 鼠标下方12px
                    left = event.clientX - tooltipRect.width / 2; // 箭头指向鼠标
                } else {
                    const rect = element.getBoundingClientRect();
                    top = rect.bottom + 12;
                }
            }
            // 检查左边界
            if (left < 10) {
                left = 10;
            }
            // 检查右边界
            if (left + tooltipRect.width > viewport.width - 10) {
                if (event && event.clientX !== undefined) {
                    // tooltip在鼠标左侧，箭头在tooltip右侧中央指向鼠标
                    left = event.clientX - tooltipRect.width - 12; // 鼠标左侧12px
                    top = event.clientY - tooltipRect.height / 2; // 箭头指向鼠标
                } else {
                    left = viewport.width - tooltipRect.width - 10;
                }
            }
            // 检查下边界
            if (top + tooltipRect.height > viewport.height - 10) {
                position = 'top';
                if (event && event.clientY !== undefined) {
                    // tooltip在鼠标上方，箭头在tooltip底部中央指向鼠标
                    top = event.clientY - tooltipRect.height - 12; // 鼠标上方12px
                    left = event.clientX - tooltipRect.width / 2; // 箭头指向鼠标
                } else {
                    const rect = element.getBoundingClientRect();
                    top = rect.top - tooltipRect.height - 12;
                }
            }
            // 最终边界检查
            left = Math.max(10, Math.min(left, viewport.width - tooltipRect.width - 10));
            top = Math.max(10, Math.min(top, viewport.height - tooltipRect.height - 10));
            // 应用位置（使用fixed定位，不需要滚动偏移）
            tooltip.className = `tooltip tooltip-${position} show`;
            tooltip.style.left = `${left}px`;
            tooltip.style.top = `${top}px`;
        }
        // 简单的GET请求封装
        async GetRequest(url, params = {}) {
            try {
                const urlParams = new URLSearchParams();
    
                // 遍历参数，自动处理数组
                Object.entries(params).forEach(([key, value]) => {
                  if (Array.isArray(value)) {
                    // 数组参数：逐个 append，生成 key[]=v1&key[]=v2
                    value.forEach(v => urlParams.append(key, v));
                  } else {
                    // 普通参数：直接添加
                    urlParams.append(key, value);
                  }
                });
                const fullUrl = urlParams.toString() ? `${url}?${urlParams}` : url;
                const response = await fetch(fullUrl, {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Content-Type': 'application/json'
                    }
                });
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return await response.json();
            } catch (error) {
                console.error('GET请求失败:', error);
                throw error;
            }
        }
        SwitchMode(mode) {
            // 设置新模式
            this.currentMode = mode;
            // 更新URL anchor参数
            this.UpdateAnchor(mode);
            // 重置初始加载标志，确保切换模式时显示白色蒙版
            this.isInitialLoad = true;
            // 重新初始化整个系统（推倒重来）
            this.Init();
        }
        // 更新header中的表头
        UpdateHeaderTableHeader() {
            const tableHeaderRow = this.container.querySelector('.rank-header-row');
            if (!tableHeaderRow) return;
            const teamLabel = this.currentMode === 'school' ? 
                this.CreateBilingualText(' 学校/组织 ', 'School') : 
                this.CreateBilingualText('队伍', 'Team');
            const teamCol = tableHeaderRow.querySelector('.rank-col-team');
            if (teamCol) {
                teamCol.innerHTML = teamLabel;
            }
        }
        UpdatePageTitle() {
            if (!this.data) return;
            // 根据当前模式决定是否显示标题
            const shouldShowTitle = this.isFullscreen 
                ? this.config.flg_show_fullscreen_contest_title 
                : this.config.flg_show_page_contest_title;
            if (!shouldShowTitle) return;
            const title = this.data.contest.title;
            const modeText = {
                team: '队伍排名',
                school: ' 学校/组织 排名',
                roll: '滚榜'
            };
            if (this.elements.pageTitle) {
                // 如果currentMode不是有效模式，使用默认的team模式
                const displayMode = modeText[this.currentMode] || modeText['team'];
                this.elements.pageTitle.textContent = `${title} - ${displayMode}`;
            }
        }
        // ********** 统计 - 显示统计信息 **********
        // 用途：显示题目提交统计表格
        // 涉及功能：统计（主功能）
        // 功能：生成统计表格HTML、显示各题目各结果类型的统计、显示合计
        ShowSummary() {
            if (!this.data) return;
            const summaryHtml = this.GenerateSummaryHtml();
            this.elements.summaryContent.innerHTML = summaryHtml;
            this.ShowModal('summary');
        }
        GenerateSummaryHtml() {
            const problems = this.data.problem;
            const solutions = this.data.solution || [];
            // 统计每个题目的提交情况
            const problemStats = {};
            problems.forEach(problem => {
                problemStats[problem.problem_id] = {
                    total: 0,
                    ac: 0,
                    wa: 0,
                    tle: 0,
                    mle: 0,
                    re: 0,
                    ce: 0,
                    pe: 0
                };
            });
            solutions.forEach(solution => {
                const problemId = solution.problem_id;
                if (problemStats[problemId]) {
                    problemStats[problemId].total++;
                    switch (solution.result) {
                        case 4: problemStats[problemId].ac++; break;
                        case 5: problemStats[problemId].pe++; break;
                        case 6: problemStats[problemId].wa++; break;
                        case 7: problemStats[problemId].tle++; break;
                        case 8: problemStats[problemId].mle++; break;
                        case 9: problemStats[problemId].re++; break;
                        case 10: problemStats[problemId].re++; break;
                        case 11: problemStats[problemId].ce++; break;
                    }
                }
            });
            let html = '<table class="summary-table"><thead><tr><th><div class="bilingual-header"><span class="header-cn">结果</span><span class="header-en">Result</span></div></th>';
            problems.forEach(problem => {
                html += `<th>${RankToolGetProblemAlphabetIdx(problem.num)}</th>`;
            });
            html += '<th><div class="bilingual-header"><span class="header-cn">合计</span><span class="header-en">Total</span></div></th></tr></thead><tbody>';
            const resultTypes = [
                { key: 'ac', name: 'AC', class: 'success' },
                { key: 'wa', name: 'WA', class: 'danger' },
                { key: 'tle', name: 'TLE', class: 'warning' },
                { key: 'mle', name: 'MLE', class: 'warning' },
                { key: 're', name: 'RE', class: 'warning' },
                { key: 'ce', name: 'CE', class: 'info' },
                { key: 'pe', name: 'PE', class: 'danger' }
            ];
            resultTypes.forEach(result => {
                html += `<tr><td>${result.name}</td>`;
                let total = 0;
                problems.forEach(problem => {
                    const count = problemStats[problem.problem_id][result.key];
                    total += count;
                    html += `<td>${count}</td>`;
                });
                html += `<td>${total}</td></tr>`;
            });
            html += '<tr><td><div class="bilingual-header"><span class="header-cn">合计</span><br/><span class="header-en">Total</span></div></td>';
            let grandTotal = 0;
            problems.forEach(problem => {
                const total = problemStats[problem.problem_id].total;
                grandTotal += total;
                html += `<td>${total}</td>`;
            });
            html += `<td>${grandTotal}</td></tr></tbody></table>`;
            return html;
        }
        ToggleFullscreen() {
            if (!document.fullscreenElement) {
                // 进入全屏
                try {
                    if (this.container.requestFullscreen) {
                        this.container.requestFullscreen();
                    } else if (this.container.webkitRequestFullscreen) { /* Safari */
                        this.container.webkitRequestFullscreen();
                    } else if (this.container.msRequestFullscreen) { /* IE11 */
                        this.container.msRequestFullscreen();
                    }
                    this.isFullscreen = true;
                    this.container.classList.add('fullscreen');
                } catch(e) {
                    console.error('Error attempting to enable full-screen mode:', e);
                }
            } else {
                // 退出全屏
                try {
                    if (document.exitFullscreen) {
                        document.exitFullscreen();
                    } else if (document.webkitExitFullscreen) { /* Safari */
                        document.webkitExitFullscreen();
                    } else if (document.msExitFullscreen) { /* IE11 */
                        document.msExitFullscreen();
                    }
                    this.isFullscreen = false;
                    this.container.classList.remove('fullscreen');
                } catch(e) {
                    console.error('Error attempting to exit full-screen mode:', e);
                }
            }
        }
        // 监听全屏状态变化
        BindFullscreenEvents() {
            // 监听全屏状态变化事件
            document.addEventListener('fullscreenchange', () => {
                this.HandleFullscreenChange();
            });
            document.addEventListener('webkitfullscreenchange', () => {
                this.HandleFullscreenChange();
            });
            document.addEventListener('mozfullscreenchange', () => {
                this.HandleFullscreenChange();
            });
            document.addEventListener('MSFullscreenChange', () => {
                this.HandleFullscreenChange();
            });
        }
        // 处理全屏状态变化
        HandleFullscreenChange() {
            const isCurrentlyFullscreen = !!document.fullscreenElement || 
                                        !!document.webkitFullscreenElement || 
                                        !!document.mozFullScreenElement || 
                                        !!document.msFullscreenElement;
            if (isCurrentlyFullscreen !== this.isFullscreen) {
                this.isFullscreen = isCurrentlyFullscreen;
            if (this.isFullscreen) {
                if (this.currentMode === 'roll') {
                    const rankContainer = this.container;
                    if (rankContainer) {
                        rankContainer.classList.add('fullscreen');
                    }
                } else {
                    this.container.classList.add('fullscreen');
                }
            } else {
                // 退出全屏时，关闭时间遮罩层
                this.StopTimeOverlay();
                // 滚榜模式：移除全屏类
                if (this.currentMode === 'roll') {
                    const rankContainer = this.container;
                    if (rankContainer) {
                        rankContainer.classList.remove('fullscreen');
                    }
                }
                this.container.classList.remove('fullscreen');
            }
                // 重新创建header以应用新的标题显示配置
                this.RecreateHeader();
            }
        }
        // 重新创建header（用于全屏切换时更新标题显示）
        RecreateHeader() {
            // 移除旧的header（从容器外部查找）
            const oldHeader = this.GetHeaderElement();
            if (oldHeader) {
                oldHeader.remove();
            }
            // 重新创建header（会自动插入到容器外部）
            this.CreateHeader();
            // UpdateHeaderVisibility 已经在 CreateHeader 中调用了
            
            // 重新初始化elements，确保按钮引用正确
            this.InitElements();
            // 重新绑定事件
            this.BindHeaderEvents();
            // 更新页面标题
            this.UpdatePageTitle();
        }
        ToggleAutoRefresh() {
            this.autoRefresh = !this.autoRefresh;
            if (this.autoRefresh) {
                // 开启前先清除可能存在的旧定时器
                if (this.refreshInterval) {
                    clearInterval(this.refreshInterval);
                }
                this.refreshInterval = setInterval(() => {
                    // 自动刷新时不是初始加载
                    this.isInitialLoad = false;
                    this.LoadData();
                }, 60000);
                this.ShowMessage('开启自动刷新');
            } else {
                // 关闭时清除定时器并重置引用
                if (this.refreshInterval) {
                    clearInterval(this.refreshInterval);
                    this.refreshInterval = null;
                }
                this.ShowMessage('关闭自动刷新');
            }
        }
        // 切换倒计时（仅在全屏模式下支持，使用全屏时间遮罩层）
        ToggleCountdown() {
            if (!this.isFullscreen) {
                return; // 非全屏模式不支持倒计时
            }
            // 使用全屏时间遮罩层作为倒计时显示
            this.showTimeOverlay = !this.showTimeOverlay;
            if (this.showTimeOverlay) {
                this.StartTimeOverlay();
            } else {
                this.StopTimeOverlay();
            }
        }
        HandleKeydown(e) {
            if (e.key === 'F5' && !e.ctrlKey) {
                e.preventDefault();
                this.RefreshData();
                return;
            }
            
            
            // 非滚榜模式的快捷键
            if (e.key === 'a' || e.key === 'A') {
                this.ToggleAutoRefresh();
            } else if (e.key === 'b' || e.key === 'B') {
                this.ToggleAutoScroll();
            } else if (e.key === 't' || e.key === 'T') {
                // 全屏模式下显示/隐藏倒计时（使用全屏时间遮罩层）
                this.ToggleCountdown();
            } else if (e.key === 'h' || e.key === 'H') {
                // 显示帮助
                this.ShowHelp();
            }
        }
        ToggleAutoScroll() {
            this.autoScroll = !this.autoScroll;
            if (this.autoScroll) {
                // 开启前先清除可能存在的旧定时器
                if (this.autoScrollInterval) {
                    clearInterval(this.autoScrollInterval);
                }
                this.StartAutoScroll();
                this.ShowMessage('开启自动滚动');
            } else {
                this.StopAutoScroll();
                this.ShowMessage('关闭自动滚动');
            }
        }
        StartAutoScroll() {
            const scrollStep = 2;
            const scrollDelay = 50;
            this.autoScrollInterval = setInterval(() => {
                window.scrollBy(0, scrollStep);
                if (window.innerHeight + window.scrollY >= document.body.scrollHeight) {
                    window.scrollTo(0, 0);
                }
            }, scrollDelay);
        }
        StopAutoScroll() {
            if (this.autoScrollInterval) {
                clearInterval(this.autoScrollInterval);
                this.autoScrollInterval = null;
            }
        }
        ShowModal(type) {
            if (type === 'help') {
                const modal = this.elements.helpModal;
                if (modal) {
                    modal.style.display = 'flex';
                }
            } else {
                const modal = this.elements[`${type}Modal`];
                if (modal) {
                    modal.style.display = 'flex';
                }
            }
        }
        HideModal(type) {
            if (type === 'help') {
                const modal = this.elements.helpModal;
                if (modal) {
                    modal.style.display = 'none';
                }
            } else {
                const modal = this.elements[`${type}Modal`];
                if (modal) {
                    modal.style.display = 'none';
                }
            }
        }
        ShowLoading() {
            if(this.externalMode) {
                return; // 外部调用模式，不需要dom调整
            }
            if (this.isInitialLoad) {
                // 初始加载时显示白色蒙版
                if (this.externalMode || !this.elements.loading) return;
                this.elements.loading.style.display = 'flex';
            } else {
                // 刷新时只显示按钮动效
                this.ShowRefreshButtonLoading();
            }
        }
        HideLoading() {
            if(this.externalMode) {
                return; // 外部调用模式，不需要dom调整
            }
            if (this.isInitialLoad) {
                // 初始加载时隐藏白色蒙版
                if (this.externalMode || !this.elements.loading) return;
                this.elements.loading.style.display = 'none';
            } else {
                // 刷新时隐藏按钮动效
                this.HideRefreshButtonLoading();
            }
        }
        
        // 显示刷新按钮加载状态
        ShowRefreshButtonLoading() {
            if (this.externalMode) {
                return; // 外部调用模式，不需要DOM调整
            }
            if (!this.container) {
                return; // 容器不存在，直接返回
            }
            const refreshButtons = this.container.querySelectorAll('#refresh-btn');
            refreshButtons.forEach(btn => {
                if (btn) {
                    btn.classList.add('loading');
                }
            });
        }
        
        // 隐藏刷新按钮加载状态
        HideRefreshButtonLoading() {
            if (this.externalMode) {
                return; // 外部调用模式，不需要DOM调整
            }
            if (!this.container) {
                return; // 容器不存在，直接返回
            }
            const refreshButtons = this.container.querySelectorAll('#refresh-btn');
            refreshButtons.forEach(btn => {
                if (btn) {
                    btn.classList.remove('loading');
                }
            });
        }
        
        // 刷新数据方法
        async RefreshData() {
            try {
                // 刷新时不是初始加载
                this.isInitialLoad = false;
                // 显示刷新按钮的加载状态
                this.ShowRefreshButtonLoading();
                this.ShowLoading();
                await this.LoadData();
            } catch (error) {
                console.error('刷新数据失败:', error);
                this.ShowError('刷新失败，请重试');
            } finally {
                this.HideLoading();
                this.HideRefreshButtonLoading();
            }
        }
        ShowError(message) {
            this.HideLoading();
            // 输出详细错误信息到控制台
            console.error('RankSystem Error:', message);
            // 使用浮动提示显示用户友好的消息
            this.ShowMessage('数据尚未准备好');
        }
        ShowMessage(message) {
            // 简单的消息提示 - 限制在容器内
            const toast = document.createElement('div');
            toast.style.cssText = `
                position: absolute;
                top: 20px;
                right: 20px;
                background: #333;
                color: white;
                padding: 10px 20px;
                border-radius: 4px;
                z-index: 10000;
                font-size: 14px;
            `;
            toast.textContent = message;
            this.container.appendChild(toast);
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 3000);
        }
        ShowKeyHint(msg, key) {
            // TODO
            return this.ShowMessage(`${msg} (${key})`);
        }
        // 显示帮助模态框
        ShowHelp() {
            this.ShowModal('help');
        }
        // 切换全屏时间遮罩层
        ToggleTimeOverlay() {
            if (!this.isFullscreen) {
                return; // 非全屏模式不显示
            }
            this.showTimeOverlay = !this.showTimeOverlay;
            if (this.showTimeOverlay) {
                this.StartTimeOverlay();
            } else {
                this.StopTimeOverlay();
            }
        }
        // 启动时间遮罩层
        StartTimeOverlay() {
            if (!this.elements.timeOverlay || !this.elements.timeOverlayText) {
                return;
            }
            this.elements.timeOverlay.style.display = 'flex';
            this.UpdateTimeOverlay();
            // 每秒更新一次
            this.timeOverlayInterval = setInterval(() => {
                this.UpdateTimeOverlay();
            }, 1000);
        }
        // 停止时间遮罩层
        StopTimeOverlay() {
            if (this.timeOverlayInterval) {
                clearInterval(this.timeOverlayInterval);
                this.timeOverlayInterval = null;
            }
            if (this.elements.timeOverlay) {
                this.elements.timeOverlay.style.display = 'none';
            }
            this.showTimeOverlay = false;
        }
        // 更新时间遮罩层显示
        UpdateTimeOverlay() {
            if (!this.elements.timeOverlayText || !this.data || !this.data.contest) {
                return;
            }
            
            const actualCurrentTime = this.GetActualCurrentTime();
            const startTime = new Date(this.data.contest.start_time);
            const endTime = new Date(this.data.contest.end_time);
            
            let timeText = '000:00:00';
            
            if (actualCurrentTime < startTime) {
                // 比赛开始前：显示倒计时（距离开始时间）
                const diff = Math.floor((startTime - actualCurrentTime) / 1000);
                const hours = Math.floor(diff / 3600);
                const minutes = Math.floor((diff % 3600) / 60);
                const seconds = diff % 60;
                // 支持三位数小时（使用 padStart(3, '0')）
                timeText = `${hours.toString().padStart(3, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            } else if (actualCurrentTime < endTime) {
                // 比赛进行中：显示倒计时（距离结束时间）
                const diff = Math.floor((endTime - actualCurrentTime) / 1000);
                const hours = Math.floor(diff / 3600);
                const minutes = Math.floor((diff % 3600) / 60);
                const seconds = diff % 60;
                // 支持三位数小时（使用 padStart(3, '0')）
                timeText = `${hours.toString().padStart(3, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            } else {
                // 比赛已结束：显示 000:00:00
                timeText = '000:00:00';
            }
            
            this.elements.timeOverlayText.textContent = timeText;
        }
        IsFrozen(solution) {
            // 是否显示为封榜状态
            if (!this.data) return false;
            if(solution.result < 0) {
                return true;    // 后端没给结果，属于封榜状态
            }
            return false;
            // *** 由后端数据控制，有数据就显示，以下注释掉 ***
            // // 判断提交是否在封榜期间
            // const inDate = solution.in_date;
            // const submitTime = new Date(inDate).getTime();
            // const endTime = new Date(this.data.contest.end_time).getTime();
            // const frozenMinutes = this.data.contest.frozen_minute || 0;
            // const frozenAfter = this.data.contest.frozen_after || 0;
            // const frozenStartTime = endTime - frozenMinutes * 60 * 1000;
            // const frozenEndTime = endTime + frozenAfter * 60 * 1000;
            // // 不在封榜时间内的提交
            // if (submitTime <= frozenStartTime) {
            //     return false;
            // }
            // // 在封榜期间，且当前时间仍在封榜或揭晓期间内
            // const now = this.GetActualCurrentTime().getTime();
            // return frozenStartTime <= now && now <= frozenEndTime;
        }
        // HTML转义（已迁移到 rank_tool.js）
        EscapeHtml(text) {
            return RankToolEscapeHtml(text);
        }
        // 旗帜映射缓存
        // 加载旗帜映射数据
        async LoadFlagMapping() {
            if (this._flagMapping) return this._flagMapping;
            if (this._flagMappingPromise) return this._flagMappingPromise;
            this._flagMappingPromise = this._LoadFlagMappingInternal();
            this._flagMapping = await this._flagMappingPromise;
            return this._flagMapping;
        }
        async _LoadFlagMappingInternal() {
            const flagBaseUrl = this.config.region_flag_url || '/static/image/region_flag';
            const mappingUrl = `${flagBaseUrl}/region_mapping.json`;
            try {
                const response = await fetch(mappingUrl);
                if (!response.ok) throw new Error('Failed to load mapping');
                const data = await response.json();
                // 构建映射表：中英文名称和缩写都映射到文件名
                const mapping = new Map();
                data.forEach(region => {
                    // 中文名映射
                    if (region['中文名']) mapping.set(region['中文名'], region['文件名']);
                    if (region['中文简称']) mapping.set(region['中文简称'], region['文件名']);
                    // 英文名映射
                    if (region['英文名']) mapping.set(region['英文名'], region['文件名']);
                    if (region['英文简称']) mapping.set(region['英文简称'], region['文件名']);
                    // 英文缩写映射
                    if (region['英文缩写']) mapping.set(region['英文缩写'], region['文件名']);
                });
                return mapping;
            } catch (error) {
                console.warn('Failed to load flag mapping:', error);
                return new Map(); // 返回空映射
            }
        }
        // 计算旗帜加载地址
        async CalculateFlagUrl(region) {
            if (!region || typeof region !== 'string') return null;
            const trimmedRegion = region.trim();
            if (!trimmedRegion) return null;
            const flagBaseUrl = this.config.region_flag_url || '/static/image/region_flag';
            const mapping = await this.LoadFlagMapping();
            // 先尝试从映射表查找
            if (mapping.has(trimmedRegion)) {
                const fileName = mapping.get(trimmedRegion);
                return `${flagBaseUrl}/${fileName}`;
            }
            // 映射表没找到，直接尝试 region.png
            return `${flagBaseUrl}/${encodeURIComponent(trimmedRegion)}.png`;
        }
        // #########################################
        //  通用图片懒加载模块
        // #########################################
        // 通用图片懒加载方案
        // 缓存策略：
        // - 成功图片：缓存1小时
        // - 失败图片：缓存10分钟，标记失败状态，避免重复请求
        CreateImageLazyLoader(config) {
            const {
                type,           // 'logo' 或 'flag'
                getFn,          // 缓存获取函数
                setFn,          // 缓存设置函数
                baseUrl,        // 基础URL
                fetchFn,        // 获取图片数据的函数
                calculateFn,    // 计算图片URL的函数（可选）
                onSuccess,      // 成功回调
                onError,        // 错误回调
                rootMargin = '50px'
            } = config;
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(async (entry) => {
                    if (!entry.isIntersecting) return;
                    const element = entry.target;
                    observer.unobserve(element);
                    // 获取图片标识符
                    const identifier = this.GetImageIdentifier(element, type);
                    if (!identifier) {
                        this.HandleImageError(element, type);
                        return;
                    }
                    // 计算图片URL
                    let imageUrl;
                    if (calculateFn) {
                        imageUrl = await calculateFn(identifier);
                        if (!imageUrl) {
                            this.HandleImageError(element, type);
                            return;
                        }
                    } else {
                        imageUrl = `${baseUrl}/${encodeURIComponent(identifier)}`;
                    }
                    // 尝试从缓存加载
                    const cacheKey = `${type}_${baseUrl}_${encodeURIComponent(identifier)}`;
                    const cached = await this.LoadFromCache(cacheKey, getFn);
                    if (cached) {
                        // 检查是否是失败状态
                        if (cached.flg_success === false) {
                            // 缓存中标记为失败，直接处理错误
                            this.HandleImageError(element, type, onError);
                            return;
                        }
                        // 成功状态，应用图片
                        this.ApplyCachedImage(element, cached, type, onSuccess);
                        return;
                    }
                    // 从网络加载
                    try {
                        const dataUrl = await fetchFn(imageUrl);
                        const payload = { 
                            dataUrl, 
                            ts: Date.now(), 
                            flg_success: true 
                        };
                        // 成功图片缓存1小时
                        setFn(cacheKey, payload, 60 * 60 * 1000);
                        this.ApplyImageToElement(element, dataUrl, type, onSuccess);
                    } catch (error) {
                        // 加载失败，缓存失败状态10分钟
                        const failurePayload = { 
                            dataUrl: null, 
                            ts: Date.now(), 
                            flg_success: false,
                            error: error.message || 'Load failed'
                        };
                        setFn(cacheKey, failurePayload, 10 * 60 * 1000);
                        this.HandleImageError(element, type, onError);
                    }
                });
            }, { rootMargin });
            return observer;
        }
        // 获取图片标识符
        GetImageIdentifier(element, type) {
            switch (type) {
                case 'logo':
                    return element.getAttribute('data-school') || '';
                case 'flag':
                    return element.getAttribute('data-flag') || '';
                default:
                    return '';
            }
        }
        // 从缓存加载图片
        async LoadFromCache(cacheKey, getFn) {
            try {
                const cached = await getFn(cacheKey);
                if (!cached) return null;
                // IndexedDBCache已自动处理JSON解析，直接返回
                return cached;
            } catch (e) {
                // 缓存读取失败
                console.warn('Cache read failed:', cacheKey, e.message);
            }
            return null;
        }
        // 应用缓存的图片
        ApplyCachedImage(element, cached, type, onSuccess) {
            if (cached.dataUrl) {
                // 成功缓存，应用图片
                this.ApplyImageToElement(element, cached.dataUrl, type, onSuccess);
            } else {
                // 失败缓存，不应用图片（保持默认状态）
                // 这里不需要做任何操作，因为失败时应该保持元素的默认状态
            }
        }
        // 应用图片到元素
        ApplyImageToElement(element, dataUrl, type, onSuccess) {
            switch (type) {
                case 'logo':
                    this.SetBackgroundImage(element, dataUrl);
                    break;
                case 'flag':
                    element.src = dataUrl;
                    break;
            }
            if (onSuccess) {
                onSuccess(element, dataUrl);
            }
        }
        // 处理图片加载错误
        HandleImageError(element, type, onError) {
            switch (type) {
                case 'logo':
                    // logo 加载失败时保持透明
                    break;
                case 'flag':
                    element.style.display = 'none';
                    break;
            }
            if (onError) {
                onError(element);
            }
        }
        // 重新观察旗帜图标（在数据渲染完成后调用）
        ReobserveFlags() {
            if (!this._flagObserver) {
                this.InitFlagLoader();
            }
            // 观察新添加的旗帜图标
            this.container.querySelectorAll('img.flag-icon').forEach(img => {
                if (!img.dataset.observed) {
                    this._flagObserver.observe(img);
                    img.dataset.observed = 'true';
                }
            });
        }
        // 重新观察校徽图标（在数据渲染完成后调用）
        ReobserveLogos() {
            if (!this._logoObserver) {
                this.InitSchoolLogoLoader();
            }
            // 观察新添加的校徽图标
            this.container.querySelectorAll('.school-logo').forEach(element => {
                if (!element.dataset.observed) {
                    this._logoObserver.observe(element);
                    element.dataset.observed = 'true';
                }
            });
        }
        // 获取旗帜数据URL
        async FetchFlagDataUrl(url) {
            // 检查离线图片数据（优先使用，避免CORS问题）
            if (window.OFFLINE_IMAGES?.region_flag) {
                // 从URL中提取文件名
                const fileName = url.substring(url.lastIndexOf('/') + 1);
                const base64Data = window.OFFLINE_IMAGES.region_flag[fileName];
                if (base64Data) {
                    return base64Data; // 直接返回base64 data URL
                }
            }
            
            // 离线模式没有找到，尝试在线加载
            const response = await fetch(url);
            if (!response.ok) throw new Error('Failed to load flag');
            const blob = await response.blob();
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = () => resolve(reader.result);
                reader.onerror = reject;
                reader.readAsDataURL(blob);
            });
        }
        // 加载校徽背景（动态添加元素时调用）
        LoadSchoolLogoBackground(element, school) {
            this.LoadImageElement(element, school, 'data-school', '_logoObserver', 'InitSchoolLogoLoader');
        }
        // 设置背景图片
        SetBackgroundImage(element, dataUrl) {
            element.style.setProperty('--rank-school-logo-bg', `url(${dataUrl})`);
            element.classList.add('has-background');
        }
        
        // 复制到剪贴板 - 优化版本
        async CopyToClipboard(text) {
            if (!text || text.trim() === '') {
                return false;
            }
            
            // 方法1: 现代 Clipboard API (需要 HTTPS 或 localhost)
            if (navigator.clipboard && window.isSecureContext) {
                try {
                    await navigator.clipboard.writeText(text);
                    return true;
                } catch (error) {
                    // 如果 Clipboard API 失败，继续尝试传统方法
                }
            }
            
            // 方法2: 传统 document.execCommand 方法
            try {
                const textArea = document.createElement('textarea');
                textArea.value = text;
                
                // 设置样式使其不可见但可选择
                Object.assign(textArea.style, {
                    position: 'fixed',
                    top: '0',
                    left: '0',
                    width: '2em',
                    height: '2em',
                    padding: '0',
                    border: 'none',
                    outline: 'none',
                    boxShadow: 'none',
                    background: 'transparent',
                    opacity: '0',
                    zIndex: '-1'
                });
                
                // 添加到 DOM
                document.body.appendChild(textArea);
                
                // 选择文本
                textArea.focus();
                textArea.select();
                textArea.setSelectionRange(0, 99999); // 移动端兼容
                
                // 执行复制
                const successful = document.execCommand('copy');
                
                // 清理
                document.body.removeChild(textArea);
                
                return successful;
            } catch (error) {
                console.error('Copy to clipboard failed:', error);
                return false;
            }
        }
        
        // 显示复制成功气泡
        ShowCopySuccessBubble(event) {
            // 创建气泡元素
            const bubble = document.createElement('div');
            bubble.className = 'copy-success-bubble';
            bubble.innerHTML = '<i class="bi bi-check-circle-fill"></i>';
            
            // 设置样式
            Object.assign(bubble.style, {
                position: 'fixed',
                left: `${event.clientX}px`,
                top: `${event.clientY - 30}px`,
                zIndex: '10000',
                background: '#28a745',
                color: 'white',
                borderRadius: '50%',
                width: '24px',
                height: '24px',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                fontSize: '12px',
                pointerEvents: 'none',
                transform: 'scale(0)',
                transition: 'all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55)',
                boxShadow: '0 2px 8px rgba(40, 167, 69, 0.3)'
            });
            
            // 添加到页面
            document.body.appendChild(bubble);
            
            // 触发动画
            requestAnimationFrame(() => {
                bubble.style.transform = 'scale(1)';
            });
            
            // 自动移除
            setTimeout(() => {
                bubble.style.transform = 'scale(0)';
                bubble.style.opacity = '0';
                setTimeout(() => {
                    if (bubble.parentNode) {
                        bubble.parentNode.removeChild(bubble);
                    }
                }, 300);
            }, 1500);
        }
    }
    window.RankSystem = RankSystem;
}
// #########################################
//  全局调用接口
// #########################################
// 使用示例：
// 1. 使用全局配置：RankSystemInit('my-container')
// 2. 使用自定义配置：RankSystemInit('my-container', { cid_list: '123,456' })
// 3. 混合配置：RankSystemInit('my-container', { api_url: '/custom/api' })
function RankSystemInit(containerId, config = {}) {
    // 如果没有传入配置，尝试从全局获取
    if (!config || Object.keys(config).length === 0) {
        config = window.RANK_CONFIG || {};
    }
    return new RankSystem(containerId, config);
};

function RankjsFormatSecondsToHMS(seconds) {
    if (seconds == null || isNaN(seconds)) return '';
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const remainingSeconds = seconds % 60;
    return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${remainingSeconds.toString().padStart(2, '0')}`;
}
function RankjsFormatSecondsToMinutes(seconds) {
    if (seconds == null || isNaN(seconds)) return '0';
    return Math.floor(seconds / 60).toString();
}

// #########################################
//  OutrankRankSystem 外榜专用子类
// #########################################
if(typeof OutrankRankSystem == 'undefined') {
    class OutrankRankSystem extends RankSystem {
        constructor(containerId, config = {}) {
            // 设置默认配置
            const defaultConfig = {
                cache_duration: 60 * 1000, // 60秒缓存
                request_t_param: true, // 启用 t 参数
            };
            const mergedConfig = RankToolMergeConfig(defaultConfig, config);
            super(containerId, mergedConfig);
        }
        
        /**
         * 重写 LoadData 方法，处理外榜的特殊需求：
         * 1. 使用 60 秒缓存（而不是 30 秒）
         * 2. 添加 t 参数（60秒更新一次）
         * 3. 处理 JSON 文件请求（直接返回 JSON，不需要 code 字段）
         */
        async LoadData() {
            try {
                this.ShowLoading();
                const cacheKey = `${this.key}_data_v2`;
                const cacheDuration = this.config.cache_duration || 60 * 1000; // 默认 60 秒
                
                // 如果启用缓存，尝试从缓存加载数据
                if (this.config.flg_rank_cache) {
                    const cachedData = await this.cache.get(cacheKey);
                    if (cachedData) {
                        this.OriInit(cachedData);
                        return;
                    }
                }
                
                const apiUrl = this.config.api_url;
                
                // 判断 api_url 是数据对象还是 URL 字符串
                if (typeof apiUrl === 'object' && apiUrl !== null && !Array.isArray(apiUrl)) {
                    // 检查是否包含比赛数据的字段（contest, team, problem, solution）
                    if (apiUrl.contest || apiUrl.team || apiUrl.problem || apiUrl.solution) {
                        // 这是数据对象，直接使用
                        this.data = apiUrl;
                        // 如果启用缓存，使用缓存管理器保存数据
                        if (this.config.flg_rank_cache) {
                            await this.cache.set(cacheKey, this.data, cacheDuration);
                        }
                        this.OriInit(this.data);
                        this.HideLoading();
                        this.isInitialLoad = false;
                        return;
                    }
                }
                
                // 如果是字符串（URL），使用 fetch 请求
                if (typeof apiUrl === 'string') {
                    // 构建请求 URL
                    let fullUrl = apiUrl;
                    
                    // 如果需要添加 t 参数（60秒更新一次）
                    if (this.config.request_t_param) {
                        // 计算当前时间戳，向下取整到 60 秒
                        const now = Date.now();
                        const t = Math.floor(now / (60 * 1000)) * (60 * 1000); // 每60秒更新一次
                        const urlParams = new URLSearchParams();
                        urlParams.append('t', t);
                        fullUrl = `${apiUrl}?${urlParams.toString()}`;
                    }
                    
                    // 请求 JSON 文件
                    const response = await fetch(fullUrl, {
                        method: 'GET',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Content-Type': 'application/json'
                        },
                        cache: 'no-cache' // 禁用浏览器缓存，使用 t 参数控制
                    });
                    
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    
                    const result = await response.json();
                    
                    // 外榜 JSON 文件可能有两种格式：
                    // 1. 直接是数据对象 { contest, team, problem, solution }
                    // 2. 包装格式 { code: 1, data: { contest, team, problem, solution } }
                    let data = null;
                    if (result.code === 1 && result.data) {
                        // 包装格式
                        data = result.data;
                    } else if (result.contest || result.team || result.problem || result.solution) {
                        // 直接数据格式
                        data = result;
                    } else {
                        throw new Error('Invalid data format');
                    }
                    
                    this.data = data;
                    
                    // 如果启用缓存，使用缓存管理器保存数据
                    if (this.config.flg_rank_cache) {
                        await this.cache.set(cacheKey, this.data, cacheDuration);
                    }
                    
                    this.OriInit(this.data);
                    this.HideLoading();
                    this.isInitialLoad = false; // 标记初始加载完成
                } else {
                    this.ShowError('无效的 api_url 配置');
                }
            } catch (error) {
                console.error('数据加载错误:', error);
                this.ShowError('网络错误，请检查连接');
            }
        }
    }
    window.OutrankRankSystem = OutrankRankSystem;
}

// 外榜初始化函数
function OutrankRankSystemInit(containerId, config = {}) {
    // 如果没有传入配置，尝试从全局获取
    if (!config || Object.keys(config).length === 0) {
        config = window.RANK_CONFIG || {};
    }
    return new OutrankRankSystem(containerId, config);
}