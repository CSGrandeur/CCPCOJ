/**
 * 滚榜模块 (Rank Roll Module)
 * 继承自 RankSystem 的滚榜功能模块
 * 
 * 使用示例：
 * const rollSystem = new RankRollSystem('roll-container', {
 *     key: 'contest_123',
 *     cid_list: '123,456',
 *     api_url: '/csgoj/contest/contest_data_ajax',
 *     // ... 其他配置
 * });
 * // 无需手动调用 init()，继承的 Init() 会自动处理
 */
class RankRollSystem extends RankSystem {
    constructor(containerId, config = {}) {
        // 调用父类构造函数
        super(containerId, config);
        
        // 滚榜相关状态
        this.rollStack = [];
        this.rollDataMap = null;
        this.rollData = null;
        this.currentJudgingIndex = -1;
        this.judgingTeamId = null;
        this.judgingProblemId = null;
        this.judgingTeamIdLast = null;
        this.animatingRisingTeamId = null; // 用于动画的上升队伍ID（在执行IncrementalUpdate时设置）
        this.autoSpeed = 1000;
        this.isAutoRolling = false;
        this.isRolling = false;

        this.realRankMap = null;    // 终榜的 team_id 到 team item 映射, this.rankList 将被处理为终榜
        this.realRankGold = 0;
        this.realRankSilver = 0;
        this.realRankBronze = 0;
        this.rollSolutionMap = null;    // 滚榜对应的 solution map
        this.flgAwardRankReady = false; // 标记是否最末有 ac 队已揭晓，此时不必再更新获奖线
        
        // 获奖区域跟踪
        this.isInAwardArea = false;
        this.currentAwardLevel = 0;
        this.startAwardLevel = 0;
        this.awardShownTeams = new Set(); // 记录已经显示过获奖的队伍ID
        this.settledTeams = new Set(); // 记录已经确认过"尘埃落定"的队伍ID
        
        // 滚榜步骤控制
        this.currentRollStep = null; // 'confirm', 'do', 'sort', 'award', null
        this.rollSpeedMultiplier = 1.0;
        this.DEFAULT_ROLL_SPEED = 1000;
        this.MIN_ROLL_SPEED = 100;
        this.MAX_ROLL_SPEED = 5000;
        this.pendingAutoRoll = false;
        
        // 模拟计算相关
        this.isSimulating = false;
        this.simulatedRollDataBackup = null;
        this.simulatedSolutionMapBackup = null;
        this.simulatedRollDataMapBackup = null;
        
        // 滚动管理
        this.scrollTimeout = null; // 滚动定时器，用于取消旧的滚动
        this.pendingScrollTeamId = null; // 待滚动的队伍ID
        this.scrollAnimationRunning = false; // 滚动动画是否正在运行（用于中断f键的滚动）
        this.scrollAnimationId = null; // 滚动动画的 requestAnimationFrame ID
        this.lastScrollTeamId = null; // 上一次滚动的队伍ID
        this.lastScrollY = null; // 上一次滚动到的Y位置（用于判断是否需要重新滚动）
        this.fastSkipScrollTimeout = null; // f键滚动等待定时器ID（用于清理）
        this.fastSkipJudgeConfirmTimeout = null; // f键滚动后的JudgeConfirm定时器ID（用于清理）
        
        // 全屏事件监听器引用（用于移除）
        this._fullscreenHandler = null;
        
        this.maxAnimationDuration = 20000; // 最大动画持续时间
    }
    
    /**
     * 重写 OriInit：在数据初始化完成后，初始化滚榜状态并创建UI
     */
    OriInit(raw_data) {
        // 调用父类的 OriInit 方法（处理数据、计算排名等）
        super.OriInit(raw_data);    // 这里会执行第一次 ProcessData，处理数据、计算排名等
        
        // 初始化滚榜状态（基于 RankSystem 的数据创建滚榜专用数据）
        this.InitRollState();
        
        // 如果提供了容器，创建滚榜UI
        if (this.container && !this.externalMode) {
            this.createUI();
            this.bindEvents();
            
            // // 初始渲染榜单（使用封榜状态的数据）
            // if (this.rollData && this.rollData.length > 0) {
            //     const displayList = this.FilterByStarMode(this.rollData, this.starMode);
            //     this.RenderRank(displayList);
            // }
        }
    }
    DoUpdateAwardInfo() {
        // 暂时直接用真实获奖线，不实时计算
        return;
        // if(!this.flgAwardRankReady) {
        //     this.UpdateAwardInfo();
        // }
    }
    
    /**
     * 初始化滚榜状态的内部实现（通用逻辑）
     * 基于 RankSystem 的数据创建滚榜专用数据结构
     * @param {boolean} forceReset - 是否强制重置（忽略已初始化检查）
     */
    _InitRollDataInternal(forceReset = false) {
        if (!this.rankList || this.rankList.length === 0) {
            console.warn('RankRollSystem: rankList not available');
            return;
        }
        
        if (!this.data || !this.data.contest) {
            console.warn('RankRollSystem: data or contest not available');
            return;
        }
        
        // 如果已经初始化过且不是强制重置，不再重复初始化
        if (!forceReset && this.rollData && this.rollData.length > 0) {
            return;
        }
        // 1. 初始化 this.rankList 为封榜状态
        this.ProcessData();
                
        // 2. 重新创建滚榜用的数据副本（基于 rankList，但 frozen 题目改为 pending）
        this.rollData = this.rankList.map(item => {
            const newItem = {
                ...item,
                problemStats: {}
            };
            const solutions = this.solutionMap[item.team_id];
            const frozenProblems = solutions && solutions.frozen ? solutions.frozen : {};
            
            // 深拷贝 problemStats，但 frozen 题目的状态改为 pending
            for (const problemId in item.problemStats) {
                const isFrozen = frozenProblems[problemId];
                if (isFrozen) {
                    // frozen 题目：改为 pending
                    newItem.problemStats[problemId] = {
                        ...item.problemStats[problemId],
                        status: 'pending'
                    };
                } else {
                    // 非 frozen 题目：保持原状（ac、wa 或 none）
                    newItem.problemStats[problemId] = { ...item.problemStats[problemId] };
                }
            }
            return newItem;
        });
        // 3. 重新创建 rollDataMap
        this.RollSort();
        if(this.rollDataMap) {
            this.rollDataMap.clear();
        } else {
            this.rollDataMap = new Map();
        }
        this.rollData.forEach(item => {
            this.rollDataMap.set(item.team_id, item);
        });

        this.flgAwardRankReady = false;
        this.DoUpdateAwardInfo();

        // 4. 深拷贝 solutionMap
        this.rollSolutionMap = JSON.parse(JSON.stringify(this.solutionMap));
        
        // 5. 初始化真实榜单，this.rankList会变为真实榜单 this.solutionMap 也会对应真实榜单
        this.CalculateRealRankMap();

        // 6. 计算真实 获奖线
        const awardRatio = this.data.contest.award_ratio;
        const ratios = RankToolParseAwardRatio(awardRatio);
        const filteredList = this.FilterByStarMode(this.rankList, this.starMode);
        const validTeamNum = filteredList.filter(item => item.solved > 0 && !item.isStar).length
        const realAwardRank = RankToolGetAwardRank(validTeamNum, ratios.gold, ratios.silver, ratios.bronze);
        this.realRankGold = realAwardRank.rankGold;
        this.realRankSilver = realAwardRank.rankSilver;
        this.realRankBronze = realAwardRank.rankBronze;
    }
    
    /**
     * 初始化滚榜状态（首次初始化，有保护检查）
     * 基于 RankSystem 的数据创建滚榜专用数据结构
     */
    InitRollState() {
        this._InitRollDataInternal(false); // 不强制重置
    }
    
    /**
     * 重写 IsFrozen：滚榜模式下只看提交时间，不考虑当前时间
     * 继承自 RankSystem，但在滚榜模式下应该只看提交是否在封榜期间，不考虑是否已揭晓
     * 
     * 按照原 IsFrozenSolution 的逻辑实现：只判断提交时间是否在封榜期间内
     */
    IsFrozen(solution) {
        // 是否是封榜期间的提交（只看提交时间）
        if (!this.data) return false;
        
        // 检查 result < 0 的情况（后端没给结果）
        if (solution.result < 0) {
            return true;    // 后端没给结果，属于封榜状态
        }
        
        // 判断提交时间是否在封榜期间内
        const inDate = solution.in_date;
        const submitTime = new Date(inDate).getTime();
        const endTime = new Date(this.data.contest.end_time).getTime();
        const frozenMinutes = this.data.contest.frozen_minute || 0;
        const frozenStartTime = endTime - frozenMinutes * 60 * 1000;
        return submitTime > frozenStartTime;
    }
    
    /**
     * 创建滚榜UI
     */
    createUI() {
        // 检查是否已经创建过UI，避免重复创建
        if (this.container && !this.externalMode) {
            // 检查滚榜控制按钮是否已存在
            const existingControls = document.querySelector('.roll-controls-section');
            if (!existingControls) {
        // 创建滚榜控制按钮
        const rollControls = this.createRollControlButtons();
                if (rollControls) {
            // 在容器之前插入控制按钮
            this.container.insertAdjacentHTML('beforebegin', rollControls);
                }
        }
        
            // 检查获奖模态框是否已存在
            const existingAwardModal = this.container.querySelector('#award-modal');
            if (!existingAwardModal) {
        this.createAwardModal();
            }
        
            // 检查滚榜帮助模态框是否已存在
            const existingHelpModal = this.container.querySelector('#roll-help-modal');
            if (!existingHelpModal) {
        this.createRollHelpModal();
            }
        }
    }
    
    /**
     * 生成滚榜控制按钮HTML
     */
    createRollControlButtons() {
        // 根据配置决定是否显示导出按钮
        const showExportButton = this.config.flg_show_export_offline_roll !== false; // 默认 true
        
        // 生成按钮的双语文本（上下结构）
        const createRollButtonText = (label, label_en) => {
            if (!label_en) return label;
            return `<span class="roll-button-bilingual-stacked">
                <span class="roll-button-text-cn">${label}</span>
                <span class="roll-button-text-en">${label_en}</span>
            </span>`;
        };
        
        let exportButtonHtml = '';
        if (showExportButton) {
            exportButtonHtml = `
                    <button id="export-offline-roll-btn" class="roll-btn roll-btn-outline-secondary">
                        <i class="bi bi-download"></i>
                        ${createRollButtonText('导出离线滚榜', 'Export Offline Roll')}
                    </button>`;
        }
        
        return `
            <div class="roll-controls-section">
                <div class="roll-controls-toolbar">
                    <button id="start-roll-btn" class="roll-btn roll-btn-primary">
                        <i class="bi bi-play-fill"></i>
                        ${createRollButtonText('启动滚榜', 'Start Roll')}
                    </button>
                    <button id="reset-roll-btn" class="roll-btn roll-btn-secondary">
                        <i class="bi bi-arrow-counterclockwise"></i>
                        ${createRollButtonText('重置滚榜', 'Reset Roll')}
                    </button>
                    <button id="help-roll-btn" class="roll-btn roll-btn-outline-info">
                        <i class="bi bi-question-circle"></i>
                        ${createRollButtonText('帮助', 'Help')}
                    </button>
                    ${exportButtonHtml}
                </div>
            </div>
        `;
    }
    
    /**
     * 创建获奖模态框
     */
    createAwardModal() {
        if (!this.container) return;
        
        const awardModal = document.createElement('div');
        awardModal.id = 'award-modal';
        awardModal.className = 'modal-overlay';
        awardModal.style.display = 'none';
        awardModal.innerHTML = `
            <div class="modal-content award-modal">
                <!-- <button id="close-award" class="award-close-btn">&times;</button> -->
                <div class="award-modal-content">
                    <!-- 顶部获奖等级大标题 -->
                    <div id="award-level" class="award-level-title"></div>
                    
                    <!-- 主体内容区域 -->
                    <div class="award-main-content">
                        <!-- 左侧：队伍照片（3:2比例，参考旧代码） -->
                        <div class="award-photo-section">
                            <div class="award-photo-frame">
                                <div class="award-photo-wrapper">
                                    <img id="award-team-photo" src="" alt="Team Photo" style="display: none;">
                                    <div id="award-photo-placeholder" class="award-photo-placeholder"></div>
                </div>
                                <div class="award-photo-glow"></div>
                    </div>
                    </div>
                        
                        <!-- 右侧：详细信息 -->
                        <div class="award-details-section">
                            <div class="award-details-card">
                                <!-- 文字信息区域（包含学校logo背景） -->
                                <div class="award-info-section">
                                    <!-- 学校logo背景（半透明，放在文字后面） -->
                                    <div id="award-school-logo" class="award-school-logo" data-school=""></div>
                                    
                                    <!-- 学校信息 -->
                                    <div class="award-info-row award-info-primary">
                                        <div class="award-info-label">${this.CreateBilingualText('学校', 'School')}</div>
                                        <div id="award-school" class="award-info-value"></div>
                                    </div>
                                    
                                    <!-- 队名信息 -->
                                    <div class="award-info-row award-info-primary">
                                        <div class="award-info-label">${this.CreateBilingualText('队名', 'Team Name')}</div>
                                        <div id="award-team-name" class="award-info-value"></div>
                                    </div>
                                    
                                    <!-- 成员信息 -->
                                    <div class="award-info-row">
                                        <div class="award-info-label">${this.CreateBilingualText('成员', 'Members')}</div>
                                        <div id="award-members" class="award-info-value"></div>
                                    </div>
                                    
                                    <!-- 教练信息 -->
                                    <div class="award-info-row">
                                        <div class="award-info-label">${this.CreateBilingualText('教练', 'Coach')}</div>
                                        <div id="award-coach" class="award-info-value"></div>
                                    </div>
                                    
                                    <!-- 一血信息（有首答时才显示） -->
                                    <div id="award-first-blood-row" class="award-info-row" style="display: none;">
                                        <div class="award-info-label">${this.CreateBilingualText('首答', 'First Blood')}</div>
                                        <div id="award-first-blood" class="award-info-value"></div>
                                    </div>
                                </div>
                                
                                <!-- 统计数据 -->
                                <div class="award-stats-container">
                                    <div class="award-stat-box">
                                        <div class="award-stat-label">${this.CreateBilingualText('排名', 'Rank')}</div>
                                        <div id="award-rank" class="award-stat-value">-</div>
                                    </div>
                                    <div class="award-stat-box">
                                        <div class="award-stat-label">${this.CreateBilingualText('解题数', 'Solved')}</div>
                                        <div id="award-solved" class="award-stat-value">-</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        this.container.appendChild(awardModal);
    }
    
    /**
     * 创建滚榜帮助模态框
     */
    createRollHelpModal() {
        if (!this.container) return;
        
        const helpModal = document.createElement('div');
        helpModal.id = 'roll-help-modal';
        helpModal.className = 'modal-overlay';
        helpModal.style.display = 'none';
        helpModal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h3>${this.CreateBilingualText('滚榜快捷键', 'Roll Shortcuts')}</h3>
                    <button id="close-roll-help" class="close-btn">&times;</button>
                </div>
                <div class="modal-body">
                    <div id="roll-help-content" class="roll-help-content">
                        <div class="roll-help-section">
                            <div class="roll-help-section-title">${this.CreateBilingualText('基本控制', 'Basic Control')}</div>
                            <div class="roll-help-items">
                                <div class="roll-help-item">
                                    <code>N</code> / <code>n</code> / <code>Space</code>
                                    <span>${this.CreateBilingualText('单步执行', 'Next Step')}</span>
                                </div>
                                <div class="roll-help-item">
                                    <code>A</code> / <code>a</code>
                                    <span>${this.CreateBilingualText('开启/关闭自动滚榜', 'Toggle Auto Roll')}</span>
                                </div>
                                <div class="roll-help-item">
                                    <span style="font-size: 0.9em; color: #666;">${this.CreateBilingualText('（全屏模式下：鼠标左键 = N）', '(Fullscreen: Left Click = N)')}</span>
                                </div>
                            </div>
                        </div>
                        <div class="roll-help-section">
                            <div class="roll-help-section-title">${this.CreateBilingualText('跳转', 'Navigation')}</div>
                            <div class="roll-help-items">
                                <div class="roll-help-item">
                                    <code>F</code> / <code>f</code> / <code>Enter</code>
                                    <span>${this.CreateBilingualText('跳到下个奖区', 'Skip to Next Award Area')}</span>
                                </div>
                                <div class="roll-help-item">
                                    <code>G</code> / <code>g</code>
                                    <span>${this.CreateBilingualText('往前跳 10 个队', 'Jump Forward 10 Teams')}</span>
                                </div>
                                <div class="roll-help-item">
                                    <code>U</code> / <code>u</code> / <code>Backspace</code>
                                    <span>${this.CreateBilingualText('撤回一步', 'Undo Last')}</span>
                                </div>
                                <div class="roll-help-item">
                                    <code>I</code> / <code>i</code>
                                    <span>${this.CreateBilingualText('往回跳 10 个队', 'Jump Back 10 Teams')}</span>
                                </div>
                                <div class="roll-help-item">
                                    <span style="font-size: 0.9em; color: #666;">${this.CreateBilingualText('（全屏模式下：鼠标右键 = F）', '(Fullscreen: Right Click = F)')}</span>
                                </div>
                            </div>
                        </div>
                        <div class="roll-help-section">
                            <div class="roll-help-section-title">${this.CreateBilingualText('速度控制', 'Speed Control')}</div>
                            <div class="roll-help-items">
                                <div class="roll-help-item">
                                    <code>W</code> / <code>w</code>
                                    <span>${this.CreateBilingualText('加速滚榜', 'Speed Up')}</span>
                                </div>
                                <div class="roll-help-item">
                                    <code>S</code> / <code>s</code>
                                    <span>${this.CreateBilingualText('减速滚榜', 'Speed Down')}</span>
                                </div>
                                <div class="roll-help-item">
                                    <code>R</code> / <code>r</code>
                                    <span>${this.CreateBilingualText('重置速度', 'Reset Speed')}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        this.container.appendChild(helpModal);
    }
    
    
    /**
     * 绑定事件
     */
    bindEvents() {
        // 检查是否已经绑定过事件，避免重复绑定
        if (this._eventsBound) {
            return;
        }
        this._eventsBound = true;
        
        // 启动滚榜按钮
        const startRollBtn = document.querySelector('#start-roll-btn');
        if (startRollBtn && !startRollBtn.hasAttribute('data-roll-bound')) {
            startRollBtn.setAttribute('data-roll-bound', 'true');
            startRollBtn.addEventListener('click', () => this.StartRollProcess());
        }
        
        // 重置滚榜按钮
        const resetRollBtn = document.querySelector('#reset-roll-btn');
        if (resetRollBtn && !resetRollBtn.hasAttribute('data-roll-bound')) {
            resetRollBtn.setAttribute('data-roll-bound', 'true');
            resetRollBtn.addEventListener('click', () => this.ResetRoll());
        }
        
        // 帮助按钮
        const helpRollBtn = document.querySelector('#help-roll-btn');
        if (helpRollBtn && !helpRollBtn.hasAttribute('data-roll-bound')) {
            helpRollBtn.setAttribute('data-roll-bound', 'true');
            helpRollBtn.addEventListener('click', () => this.ShowRollHelp());
        }
        
        // 导出离线滚榜按钮（在 roll-controls-section 中）
        const exportOfflineRollBtn = document.querySelector('#export-offline-roll-btn');
        if (exportOfflineRollBtn && !exportOfflineRollBtn.hasAttribute('data-roll-bound')) {
            exportOfflineRollBtn.setAttribute('data-roll-bound', 'true');
            exportOfflineRollBtn.addEventListener('click', () => this.ExportOfflineRoll());
        }
        
        // 模态框关闭
        const closeAward = this.container.querySelector('#close-award');
        if (closeAward && !closeAward.hasAttribute('data-roll-bound')) {
            closeAward.setAttribute('data-roll-bound', 'true');
            closeAward.addEventListener('click', () => this.HideModal('award'));
        }
        
        const closeRollHelp = this.container.querySelector('#close-roll-help');
        if (closeRollHelp && !closeRollHelp.hasAttribute('data-roll-bound')) {
            closeRollHelp.setAttribute('data-roll-bound', 'true');
            closeRollHelp.addEventListener('click', () => this.HideModal('rollHelp'));
        }
        
        // 模态框点击背景关闭
        const awardModal = this.container.querySelector('#award-modal');
        if (awardModal && !awardModal.hasAttribute('data-roll-bound')) {
            awardModal.setAttribute('data-roll-bound', 'true');
            awardModal.addEventListener('click', (e) => {
                if (e.target === awardModal) this.HideModal('award');
            });
        }
        
        const rollHelpModal = this.container.querySelector('#roll-help-modal');
        if (rollHelpModal && !rollHelpModal.hasAttribute('data-roll-bound')) {
            rollHelpModal.setAttribute('data-roll-bound', 'true');
            rollHelpModal.addEventListener('click', (e) => {
                if (e.target === rollHelpModal) this.HideModal('rollHelp');
            });
        }
        
        // 键盘事件（只在第一次绑定，避免重复监听）
        if (!this._keyboardEventBound) {
            this._keyboardEventBound = true;
        document.addEventListener('keydown', (e) => this.HandleRollKeydown(e));
    }
    
        // 鼠标事件（全屏滚榜状态下）
        if (!this._mouseEventBound) {
            this._mouseEventBound = true;
            // 左键点击与N键相同功能（单步执行）
            document.addEventListener('click', (e) => {
                // 检查是否在全屏滚榜状态下
                const isFullscreenRolling = this.isRolling && 
                    (this.isFullscreen || (this.container && this.container.classList.contains('fullscreen')));
                
                if (isFullscreenRolling) {
                    // 检查是否在发奖modal上（允许在发奖modal上触发）
                    const isInAwardModal = e.target.closest('#award-modal');
                    
                    // 排除交互元素（按钮、输入框等），但允许在发奖modal上触发
                    const isInteractiveElement = e.target.closest('button, .control-btn, .custom-select-btn, a, input, select, textarea, .close-btn, .award-close-btn');
                    
                    // 排除其他modal（帮助modal等），但允许发奖modal
                    const isOtherModal = !isInAwardModal && e.target.closest('.modal-overlay, .modal-content');
                    
                    if (!isInteractiveElement && !isOtherModal) {
                        e.preventDefault();
                        this.RollNext();
                    }
                }
            });
            
            // 右键点击与F键相同功能（跳到下个奖区）
            document.addEventListener('contextmenu', (e) => {
                // 检查是否在全屏滚榜状态下
                const isFullscreenRolling = this.isRolling && 
                    (this.isFullscreen || (this.container && this.container.classList.contains('fullscreen')));
                
                if (isFullscreenRolling) {
                    // 检查是否在发奖modal上（允许在发奖modal上触发）
                    const isInAwardModal = e.target.closest('#award-modal');
                    
                    // 排除交互元素（按钮、输入框等），但允许在发奖modal上触发
                    const isInteractiveElement = e.target.closest('button, .control-btn, .custom-select-btn, a, input, select, textarea, .close-btn, .award-close-btn');
                    
                    // 排除其他modal（帮助modal等），但允许发奖modal
                    const isOtherModal = !isInAwardModal && e.target.closest('.modal-overlay, .modal-content');
                    
                    if (!isInteractiveElement && !isOtherModal) {
                        e.preventDefault();
                        this.FastSkipToAwardArea();
                    }
                }
            });
        }
    
        // 全屏退出事件监听（退出全屏时立即停止滚榜）
        if (!this._fullscreenHandler) {
            this._fullscreenHandler = () => this.HandleFullscreenExit();
            document.addEventListener('fullscreenchange', this._fullscreenHandler);
            document.addEventListener('webkitfullscreenchange', this._fullscreenHandler);
            document.addEventListener('mozfullscreenchange', this._fullscreenHandler);
            document.addEventListener('MSFullscreenChange', this._fullscreenHandler);
        }
    }
    
    /**
     * 处理全屏退出事件 - 立即停止滚榜并重置所有状态
     * 只要退出全屏，就退出滚榜状态
     */
    HandleFullscreenExit() {
        const isCurrentlyFullscreen = !!document.fullscreenElement || 
                                    !!document.webkitFullscreenElement || 
                                    !!document.mozFullScreenElement || 
                                    !!document.msFullscreenElement;
        
        // 如果退出全屏，立即停止滚榜并重置所有状态
        if (!isCurrentlyFullscreen) {
            this.StopRollCompletely();
        }
    }
    
    /**
     * 完全停止滚榜并重置所有状态（退出全屏时调用）
     */
    StopRollCompletely() {
        // 停止滚榜
        this.isRolling = false;
        this.isAutoRolling = false;
        this.currentRollStep = null;
        this.autoSpeed = this.DEFAULT_ROLL_SPEED;
        this.rollSpeedMultiplier = 1.0;
        this.pendingAutoRoll = false;
        
        // 清理滚动定时器
        if (this.scrollTimeout) {
            clearTimeout(this.scrollTimeout);
            this.scrollTimeout = null;
        }
        this.pendingScrollTeamId = null;
        
        // 清除高亮
        this.ClearJudgingHighlight();
        
        // 关闭所有打开的modal
        this.HideModal('award');
        this.HideModal('rollHelp');
        
        // 重置获奖区域状态
        this.isInAwardArea = false;
        this.currentAwardLevel = 0;
        this.startAwardLevel = 0;
        this.awardShownTeams.clear();
        
        // 重置判题状态
        this.currentJudgingIndex = -1;
        this.judgingTeamId = null;
        this.judgingProblemId = null;
        this.judgingTeamIdLast = null;
        this.animatingRisingTeamId = null;
        
        // 清空滚榜操作栈
        this.rollStack = [];
        // 清空已确认"尘埃落定"的队伍
        this.settledTeams.clear();
        
        // 恢复启动按钮状态
        const startBtn = document.querySelector('#start-roll-btn');
        if (startBtn) {
            startBtn.classList.remove('disabled');
            startBtn.removeAttribute('disabled');
        }
        
        // 清除所有提示信息
        this.ShowMessage('');
        this.ShowKeyHint('', '');
    }
    
    /**
     * 格式化时间（毫秒转 HH:MM:SS）
     */
    formatDuration(milliseconds) {
        const totalSeconds = Math.floor(milliseconds / 1000);
        const hours = Math.floor(totalSeconds / 3600);
        const minutes = Math.floor((totalSeconds % 3600) / 60);
        const seconds = totalSeconds % 60;
        return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
    }
    // 继承 RankSystem 的方法，无需代理调用
    // ShowMessage, ShowKeyHint, FilterByStarMode, CalculateRankInfo, 
    // GetProblemAlphabetIdx, IncrementalUpdate, RenderRank, UpdateRankRow 
    // 等方法直接继承自 RankSystem，可以直接使用 this.xxx() 调用
    
    CalculateAnimationDuration() {
        // 继承自 RankSystem，直接调用父类方法
        return super.CalculateAnimationDuration(this.baseAnimationDuration, this.rollSpeedMultiplier, this.minAnimationDuration, this.maxAnimationDuration);
    }
    
    /**
     * 重写 ExecuteBulkAnimation：滚榜模式下使用基于速度的动画和区分上升/下降队伍
     */
    async ExecuteBulkAnimation(movements, sortedRows, grid, onComplete = null) {
        // 计算动画持续时间
        const speedMultiplier = this.rollSpeedMultiplier || 1.0;
        const animationDuration = RankToolCalculateAnimationDuration(this.baseAnimationDuration, speedMultiplier, this.minAnimationDuration, this.maxAnimationDuration);
        
        // 1. 提取排序后的itemKey顺序
        const order = sortedRows.map(row => row.getAttribute('data-row-id')).filter(Boolean);
        
        // 2. 判断是否是滚榜排序模式（在 JudgeSort 中）
        // const isRollSorting = this.currentRollStep === 'sort';
        const isRollSorting = true;
        // 关键修复：使用 animatingRisingTeamId（在执行IncrementalUpdate时由JudgeSort设置）
        // 而不是 judgingTeamId（可能已经被更新为下一个队伍）
        const risingTeamIds = isRollSorting && this.animatingRisingTeamId ? [this.animatingRisingTeamId] : [];

        // 3. 使用CSG动画库的智能排序动画（滚榜模式：基于速度 + 区分上升/下降）
        await window.CSGAnim.sortAnimate(grid, order, {
            duration: animationDuration,
            speedMultiplier: speedMultiplier,  // 传递速度倍率
            easing: window.CSGAnim.getEasing('smooth'),
            useFlip: true, // 使用FLIP技术
            queue: true, // 启用队列管理
            cancelPrevious: true, // 取消之前的动画（但mergeAnimations会保护上升队伍）
            // 滚榜专用选项
            useSpeedBasedDuration: isRollSorting, // 滚榜时启用基于速度的动画
            speed: 500, // 移动速度（像素/秒）- 上升队伍的速度，越大越慢
            minDuration: this.minAnimationDuration || 600, // 最小动画时长（提高默认值，让上升动画更慢更优雅）
            maxDuration: this.maxAnimationDuration || 20000, // 最大动画时长（提高默认值，允许更慢的动画）
            risingTeamIds: risingTeamIds, // 上升队伍ID列表
            mergeAnimations: isRollSorting, // 滚榜时启用动画合并（从当前位置继续）
            risingEasing: 'cubic-bezier(0.25, 0.1, 0.25, 1)', // ease-out，优雅减速
            fallingDuration: 400, // 下降队伍固定duration，快速落位
            fallingEasing: 'cubic-bezier(0.4, 0, 1, 1)', // ease-in，快速开始
            onStart: () => {
            },
            onComplete: () => {
                this.FinalizeBulkAnimation([], sortedRows, grid);
                // 执行完成回调
                if (onComplete) {
                    onComplete();
                }
            }
        });
    }
    
    SmoothScrollToBottom(element) {
        // 如果父类有该方法则调用，否则使用简单实现
        if (super.SmoothScrollToBottom) {
            return super.SmoothScrollToBottom(element);
        }
        // 简单实现
        return new Promise((resolve) => {
            const startScrollTop = element.scrollTop;
            const targetScrollTop = element.scrollHeight - element.clientHeight;
            const distance = targetScrollTop - startScrollTop;
            
            if (Math.abs(distance) < 1) {
                resolve();
                return;
            }
            
            const duration = 2000;
            const startTime = performance.now();
            const easeInOutCubic = (t) => {
                return t < 0.5 
                    ? 4 * t * t * t 
                    : 1 - Math.pow(-2 * t + 2, 3) / 2;
            };
            
            const animateScroll = (currentTime) => {
                const elapsed = currentTime - startTime;
                const progress = Math.min(elapsed / duration, 1);
                const easedProgress = easeInOutCubic(progress);
                
                element.scrollTop = startScrollTop + distance * easedProgress;
                
                if (progress < 1) {
                    requestAnimationFrame(animateScroll);
                } else {
                    resolve();
                }
            };
            
            requestAnimationFrame(animateScroll);
        });
    }
    
    // 滚榜排序
    RollSort() {
        // 排名计算必须通过 CalculateRankInfo 来完成，它会正确处理并列、打星等情况
        // 复用父类的排序比较函数，避免重复实现排序逻辑
        this.rollData.sort((a, b) => this.CompareTeamsForRanking(a, b));
        // 调用父类的方法计算排名
        this.CalculateRankInfo(this.rollData);
       
    }
    
    /**
     * 启动滚榜流程
     */
    async StartRollProcess() {
        if (this.isRolling) {
            this.ShowMessage('滚榜已启动');
            return;
        }
        
        // 确保数据已加载
        if (!this.rollData || this.rollData.length === 0) {
            await this.Init();
        }
        
        // 更新启动按钮状态（禁用）
        const startBtn = document.querySelector('#start-roll-btn');
        if (startBtn) {
            startBtn.classList.add('disabled');
            startBtn.setAttribute('disabled', 'true');
        }
        
        // 1. 进入全屏
        if (!document.fullscreenElement) {
            try {
                if (this.container.requestFullscreen) {
                    await this.container.requestFullscreen();
                } else if (this.container.webkitRequestFullscreen) {
                    await this.container.webkitRequestFullscreen();
                } else if (this.container.msRequestFullscreen) {
                    await this.container.msRequestFullscreen();
                }
                this.container.classList.add('fullscreen');
            } catch(e) {
                console.error('Error attempting to enable full-screen mode:', e);
                this.ShowMessage('无法进入全屏模式');
                if (startBtn) {
                    startBtn.classList.remove('disabled');
                    startBtn.removeAttribute('disabled');
                }
                return;
            }
        }
        
        // 2. 等待DOM稳定，然后滚动到底部
        await new Promise(resolve => setTimeout(resolve, 300));
        
        // 滚动到榜单底部
        const rankGrid = this.container.querySelector('.rank-grid');
        if (rankGrid) {
            await this.SmoothScrollToBottom(rankGrid);
        }
        
        // 3. 启动滚榜业务逻辑
        this.StartRollInternal();
    }
    
    /**
     * 启动滚榜（业务逻辑）
     */
    StartRollInternal() {
        this.isRolling = true;
        this.isAutoRolling = false;
        this.autoSpeed = this.DEFAULT_ROLL_SPEED;
        this.rollSpeedMultiplier = 1.0;
        this.currentRollStep = null;
        
        // 清理已经弹出的tooltip
        if (this.HideGlobalTooltip) {
            this.HideGlobalTooltip();
        }
        // 清理tooltip延迟
        if (this.tooltipTimeouts) {
            Object.values(this.tooltipTimeouts).forEach(timeout => clearTimeout(timeout));
            this.tooltipTimeouts = {};
        }
        
        // 初始化滚榜状态变量
        this.rollStack = [];
        this.currentJudgingIndex = this.rollData.length - 1;
        this.judgingTeamId = null;
        this.judgingProblemId = null;
        this.judgingTeamIdLast = null;
        this.animatingRisingTeamId = null;
        
        // 清空已确认"尘埃落定"的队伍
        this.settledTeams.clear();
        
        // 初始化获奖区域状态
        this.isInAwardArea = false;
        this.currentAwardLevel = 0;
        this.startAwardLevel = 0;
        
        // 如果是在自动模式下启动，保持自动状态
        if (this.pendingAutoRoll) {
            this.isAutoRolling = true;
        }
        
        // 开始滚榜
        this.RollNext();
    }
    
    /**
     * 停止滚榜
     */
    StopRoll() {
        // 调用完全停止方法，确保所有状态都重置
        this.StopRollCompletely();
    }
    
    /**
     * 重置滚榜数据的核心逻辑（可复用的内部函数）
     * 清理所有状态变量、定时器、高亮等，并重新初始化滚榜状态
     * @param {boolean} shouldReloadData - 是否重新从服务器获取数据（ResetRoll需要，u/i不需要）
     */
    async _ResetRollDataCore(shouldReloadData = false) {
        // 1. 彻底清空所有滚榜状态变量
        this.rollData = null;
        this.rollDataMap = null;
        this.rollStack = []; // 清空栈，不再使用
        this.currentJudgingIndex = -1;
        this.judgingTeamId = null;
        this.judgingProblemId = null;
        this.judgingTeamIdLast = null;
        this.animatingRisingTeamId = null;
        this.autoSpeed = this.DEFAULT_ROLL_SPEED;
        this.rollSpeedMultiplier = 1.0;
        this.isAutoRolling = false;
        this.currentRollStep = null;
        this.isInAwardArea = false;
        this.settledTeams.clear();
        this.currentAwardLevel = 0;
        this.startAwardLevel = 0;
        this.awardShownTeams.clear();
        this.isSimulating = false;
        this.simulatedRollDataBackup = null;
        this.simulatedSolutionMapBackup = null;
        this.simulatedRollDataMapBackup = null;
        this.pendingAutoRoll = false;
        this.pendingScrollTeamId = null;
        this.scrollAnimationRunning = false;
        this.scrollAnimationId = null;
        this.lastScrollTeamId = null;
        this.lastScrollY = null;
        
        // 2. 清理所有定时器（包括 f 键滚动的定时器）
        if (this.scrollTimeout) {
            clearTimeout(this.scrollTimeout);
            this.scrollTimeout = null;
        }
        if (this.fastSkipScrollTimeout !== null) {
            clearTimeout(this.fastSkipScrollTimeout);
            this.fastSkipScrollTimeout = null;
        }
        if (this.fastSkipJudgeConfirmTimeout !== null) {
            clearTimeout(this.fastSkipJudgeConfirmTimeout);
            this.fastSkipJudgeConfirmTimeout = null;
        }
        
        // 3. 清除高亮
        this.ClearJudgingHighlight();
        
        // 4. 清除所有tooltip
        this.HideGlobalTooltip();
        if (this.tooltipTimeouts) {
            Object.values(this.tooltipTimeouts).forEach(timeout => clearTimeout(timeout));
            this.tooltipTimeouts = {};
        }
        
        // 5. 如果需要，重新获取数据（从服务器重新加载）
        if (shouldReloadData) {
            await this.LoadData();
        }
        
        // 6. 重新初始化滚榜状态
        this.InitRollState();
        
        // 7. 设置 currentJudgingIndex 为最后一个队伍（初始状态）
        this.currentJudgingIndex = this.rollData.length > 0 ? this.rollData.length - 1 : -1;
    }
    
    /**
     * 重置滚榜 - 彻底重置所有变量并重新获取数据
     */
    async ResetRoll() {
        // 1. 停止滚榜
        if (this.isRolling) {
            this.StopRoll();
        }
        
        // 2. 执行核心重置逻辑（包含重新获取数据）
        await this._ResetRollDataCore(true);
        
        // 3. 渲染榜单
        if (this.rollData && this.rollData.length > 0) {
            const displayList = this.FilterByStarMode(this.rollData, this.starMode);
            this.RenderRank(displayList);
        }
        
        // 4. 恢复启动按钮状态
        const startBtn = document.querySelector('#start-roll-btn');
        if (startBtn) {
            startBtn.classList.remove('disabled');
            startBtn.removeAttribute('disabled');
        }
                
        this.ShowMessage('滚榜已重置');
    }
    
    /**
     * 下一个判题
     */
    RollNext() {
        this.DoUpdateAwardInfo();
        if (!this.isRolling) {
            return;
        }
        
        // 强检查：如果滚榜已完成（currentJudgingIndex < 0），坚决停止所有操作
        if (this.currentJudgingIndex < 0) {
            this.ShowMessage('滚榜完成');
            this.currentRollStep = null;
            this.judgingTeamId = null;
            this.judgingProblemId = null;
            // 强制清空状态变量，避免残留导致奇怪行为
            this.awardShownTeams.clear();
            this.settledTeams.clear();
            this.isInAwardArea = false;
            this.currentAwardLevel = 0;
            this.startAwardLevel = 0;
            this.StopRoll();
            return;
        }
        
        // 检查是否有modal打开，如果有则先关闭（确保同步）
        const awardModal = this.container.querySelector('#award-modal');
        if (awardModal && awardModal.style.display === 'flex') {
            // modal已打开，如果有步骤则继续步骤，否则直接关闭并继续
            if (this.currentRollStep === 'award_open') {
                // 正在award_open步骤，进入关闭步骤
                this.RollNextStep();
                return;
            } else if (this.currentRollStep === 'award_close') {
                // 正在award_close步骤，继续执行
                this.RollNextStep();
                return;
            } else {
                // 意外情况：modal打开但没有正确的步骤，强制关闭
                this.HideModal('award');
                this.currentRollStep = null;
                this.ClearJudgingHighlight();
            }
        }
        
        // 如果当前有步骤在执行，执行下一步
        if (this.currentRollStep) {
            this.RollNextStep();
            return;
        }
        // 执行确认步骤（定位到排名最靠后的有未揭晓题目的队伍）
        this.JudgeConfirm();
    }
    
    /**
     * 尝试自动滚榜（封装自动滚榜的延迟逻辑）
     * 根据 currentRollStep 决定延迟时间和执行的操作
     * @param {string} action - 要执行的操作类型: 'RollNextStep', 'JudgeDo', 'JudgeSort', 'JudgeConfirm', 'RollNext', null(根据currentRollStep自动判断)
     * @param {boolean} isSimulating - 是否在模拟模式下（模拟模式下不执行）
     */
    TryAutoRolling(action = null, isSimulating = false) {
        if (!this.isAutoRolling || isSimulating) {
            return;
        }
        
        // 根据 currentRollStep 或 action 决定延迟时间和执行的操作
        let delay = 0;
        let targetAction = action;
        
        // 如果没有指定 action，根据 currentRollStep 自动判断
        if (!targetAction) {
            switch (this.currentRollStep) {
                case 'confirm':
                    targetAction = 'JudgeDo';
                    delay = this.isInAwardArea ? Math.max(300, this.autoSpeed * 0.3) : 50;
                    break;
                case 'award_highlight':
                    targetAction = 'RollNextStep';
                    delay = this.isInAwardArea ? Math.max(300, this.autoSpeed * 0.3) : 200;
                    break;
                case 'do':
                    // do 步骤：如果没有AC，会直接设置 currentRollStep = 'sort'，这里按 sort 处理
                    // 如果AC了，会调用 JudgeSort，但这里我们已经在 JudgeDo 中处理了
                    targetAction = 'RollNextStep';
                    delay = this.isInAwardArea ? Math.max(200, this.autoSpeed * 0.2) : 50;
                    break;
                case 'sort':
                    targetAction = 'RollNextStep'; // 会进入 sort 分支，调用 JudgeAward
                    delay = this.isInAwardArea ? Math.max(200, this.autoSpeed * 0.2) : 50;
                    break;
                case 'award_open':
                    targetAction = 'RollNextStep';
                    delay = Math.max(this.autoSpeed * 2, 2000);
                    break;
                default:
                    // 默认不执行
                    return;
            }
        } else {
            // 如果指定了 action，根据 action 类型和 currentRollStep 决定延迟
            switch (targetAction) {
                case 'RollNextStep':
                    if (this.currentRollStep === 'award_highlight') {
                        delay = this.isInAwardArea ? Math.max(300, this.autoSpeed * 0.3) : 200;
                    } else if (this.currentRollStep === 'award_open') {
                        delay = Math.max(this.autoSpeed * 2, 1000);
                    } else {
                        delay = this.isInAwardArea ? Math.max(200, this.autoSpeed * 0.2) : 50;
                    }
                    break;
                case 'JudgeDo':
                    delay = this.isInAwardArea ? Math.max(300, this.autoSpeed * 0.3) : 50;
                    break;
                case 'JudgeSort':
                    delay = this.isInAwardArea ? Math.max(200, this.autoSpeed * 0.2) : 50;
                    break;
                case 'JudgeConfirm':
                    delay = this.isInAwardArea ? Math.max(this.autoSpeed, 256) : 0;
                    break;
                case 'RollNext':
                    delay = this.isInAwardArea ? Math.max(200, this.autoSpeed * 0.2) : 50;
                    break;
                default:
                    return;
            }
        }
        
        // 如果延迟为0，直接执行；否则延迟执行
        const executeAction = () => {
            switch (targetAction) {
                case 'RollNextStep':
                    this.RollNextStep();
                    break;
                case 'JudgeDo':
                    this.JudgeDo();
                    break;
                case 'JudgeSort':
                    this.JudgeSort();
                    break;
                case 'JudgeConfirm':
                    this.JudgeConfirm();
                    break;
                case 'RollNext':
                    this.RollNext();
                    break;
            }
        };
        
        if (delay === 0) {
            executeAction();
        } else {
            setTimeout(executeAction, delay);
        }
    }
    
    /**
     * 步骤推进
     */
    RollNextStep() {
        // 强检查：如果滚榜已完成（currentJudgingIndex < 0），坚决停止所有操作
        if (this.currentJudgingIndex < 0) {
            this.ShowMessage('滚榜完成');
            this.currentRollStep = null;
            this.judgingTeamId = null;
            this.judgingProblemId = null;
            // 强制清空状态变量，避免残留导致奇怪行为
            this.awardShownTeams.clear();
            this.settledTeams.clear();
            this.isInAwardArea = false;
            this.currentAwardLevel = 0;
            this.startAwardLevel = 0;
            this.StopRoll();
            return;
        }
        
        switch (this.currentRollStep) {
            case 'confirm':
                // 确认步骤后，根据情况进入判题或获奖流程
                const nextJudging = this.FindNextJudging();
                if (nextJudging && nextJudging.needsAward) {
                    // 需要显示获奖，先进入高亮步骤
                    // 关键修复：立即设置 judgingTeamId，确保状态一致性
                    this.judgingTeamId = nextJudging.team_id;
                    this.currentRollStep = 'award_highlight';
                    this.RollNextStep();
                } else if (nextJudging && !nextJudging.needsSkip) {
                    // 需要判题
                    // 关键修复：确保 judgingTeamId 被设置（虽然 JudgeDo 内部会设置，但这里也设置确保一致性）
                    this.judgingTeamId = nextJudging.team_id;
                    this.JudgeDo();
                } else if (nextJudging && nextJudging.needsSkip) {
                    // 需要跳过，但也要设置 judgingTeamId 以保持状态一致性
                    this.judgingTeamId = nextJudging.team_id;
                    this.currentJudgingIndex--;
                    // 强检查：如果索引变为负数，说明滚榜完成，不要继续
                    if (this.currentJudgingIndex >= 0) {
                        this.RollNext();
                    } else {
                        // 滚榜完成，调用 JudgeConfirm 处理结束逻辑（这会再次检查并停止）
                        this.JudgeConfirm();
                    }
                } else {
                    // 没有找到下一个，滚榜完成
                    // 注意：这里 nextJudging 为 null，说明 FindNextJudging 已经设置了 currentJudgingIndex = -1
                    // 调用 JudgeConfirm 处理结束逻辑（这会再次检查并停止）
                    this.JudgeConfirm();
                }
                break;
            case 'award_highlight':
                // 获奖高亮步骤：已高亮，下一步是弹出modal
                this.currentRollStep = 'award_open';
                
                const currentItem = this.rollDataMap.get(this.judgingTeamId);
                
                if (currentItem && currentItem.displayRank !== '*') {
                    const realRankInfo = this.realRankMap.get(this.judgingTeamId);
                    
                    if (realRankInfo && realRankInfo.displayRank !== '*') {
                        let awardType = null;
                        if (realRankInfo.displayRank <= this.rankGold) {
                            awardType = 'gold';
                        } else if (realRankInfo.displayRank <= this.rankSilver) {
                            awardType = 'silver';
                        } else if (realRankInfo.displayRank <= this.rankBronze) {
                            awardType = 'bronze';
                        }
                        
                        if (awardType) {
                            this.ShowAward(this.judgingTeamId, awardType);
                            
                            // 标记该队伍已经显示过获奖，避免重复弹出
                            this.awardShownTeams.add(this.judgingTeamId);
                            
                            // 更新获奖区域状态
                            if (!this.isInAwardArea) {
                                this.isInAwardArea = true;
                                if (this.isAutoRolling) {
                                    this.isAutoRolling = false;
                                    this.autoSpeed = this.DEFAULT_ROLL_SPEED;
                                }
                            }
                            
                            // 自动模式下延迟后关闭modal
                            this.TryAutoRolling('RollNextStep');
                        } else {
                            // 没有获奖，直接继续下一个
                            this.currentRollStep = null;
                            this.currentJudgingIndex--;
                            this.ClearJudgingHighlight();
                            // 强检查：如果索引变为负数，说明滚榜完成，不要继续
                            if (this.currentJudgingIndex >= 0) {
                                this.RollNext();
                            } else {
                                this.JudgeConfirm();
                            }
                        }
                    } else {
                        // 没有获奖，直接继续下一个
                        this.currentRollStep = null;
                        this.currentJudgingIndex--;
                        this.ClearJudgingHighlight();
                        // 强检查：如果索引变为负数，说明滚榜完成，不要继续
                        if (this.currentJudgingIndex >= 0) {
                            this.RollNext();
                        } else {
                            this.JudgeConfirm();
                        }
                    }
                } else {
                    // 没有获奖，直接继续下一个
                    this.currentRollStep = null;
                    this.currentJudgingIndex--;
                    this.ClearJudgingHighlight();
                    // 强检查：如果索引变为负数，说明滚榜完成，不要继续
                    if (this.currentJudgingIndex >= 0) {
                        this.RollNext();
                    } else {
                        this.JudgeConfirm();
                    }
                }
                break;
            case 'do':
                this.JudgeSort();
                break;
            case 'sort':
                this.JudgeAward();
                break;
            case 'award_open':
                // 获奖modal已打开，下一步是关闭modal
                // 先检查modal是否真的打开了
                const awardModalCheck = this.container.querySelector('#award-modal');
                if (awardModalCheck && awardModalCheck.style.display === 'flex') {
                    // modal确实打开了，关闭它
                    this.currentRollStep = 'award_close';
                    this.HideModal('award');
                } else {
                    // modal未打开，直接进入下一步
                    this.currentRollStep = null;
                    this.currentJudgingIndex--;
                    this.ClearJudgingHighlight();
                    // 强检查：如果索引变为负数，说明滚榜完成，不要继续
                    if (this.currentJudgingIndex >= 0) {
                        this.RollNext();
                    } else {
                        this.JudgeConfirm();
                    }
                }
                break;
            case 'award_close':
                // 获奖modal已关闭，继续下一个
                // 确保modal已关闭
                const awardModalClosed = this.container.querySelector('#award-modal');
                if (awardModalClosed && awardModalClosed.style.display === 'flex') {
                    // modal还没关闭，强制关闭
                    this.HideModal('award');
                }
                // 继续下一步
                this.currentJudgingIndex--;
                this.ClearJudgingHighlight();
                // 强检查：如果索引变为负数，说明滚榜完成，不要继续
                if (this.currentJudgingIndex >= 0) {
                    // 自动模式下延迟后继续，非自动模式下立即继续
                    if (this.isAutoRolling) {
                        this.currentRollStep = null;
                        this.TryAutoRolling('RollNext');
                    } else {
                        this.currentRollStep = null;
                        this.RollNext();
                    }
                } else {
                    // 滚榜完成，调用 JudgeConfirm 处理结束逻辑
                    this.currentRollStep = null;
                    this.JudgeConfirm();
                }
                break;
            case 'award':
                // 兼容旧逻辑：如果modal已打开，关闭它；否则继续下一个
                const awardModal = this.container.querySelector('#award-modal');
                if (awardModal && awardModal.style.display === 'flex') {
                    // modal已打开，关闭它
                    this.currentRollStep = 'award_close';
                    this.HideModal('award');
                } else {
                    // modal未打开，继续下一个
                    this.currentRollStep = null;
                    this.currentJudgingIndex--;
                    this.ClearJudgingHighlight();
                    // 强检查：如果索引变为负数，说明滚榜完成，不要继续
                    if (this.currentJudgingIndex >= 0) {
                        this.RollNext();
                    } else {
                        this.JudgeConfirm();
                    }
                }
                break;
            case 'sort_award':
                this.JudgeAward();
                break;
            default:
                this.RollNext();
                break;
        }
    }
    
    /**
     * 查找下一个要处理的队伍
     * 逻辑：基于 currentJudgingIndex 从后往前逐个队伍处理，无论是否有frozen题目都要"看"过
     * 如果当前队伍有未揭晓题目，返回需要判题；如果没有但需要显示获奖，返回需要获奖；否则返回需要跳过
     */
    FindNextJudging() {
        // 强检查：如果滚榜已完成（currentJudgingIndex < 0），直接返回 null，坚决不再继续
        if (this.currentJudgingIndex < 0) {
            return null;
        }
        
        // 初始化：如果 currentJudgingIndex 无效，设置为最后一个队伍
        if (this.currentJudgingIndex < 0 || this.currentJudgingIndex >= this.rollData.length) {
            this.currentJudgingIndex = this.rollData.length - 1;
        }
        
        // 关键修复：总是先检查当前位置的队伍（不管排序后这个位置的队伍是否变化）
        // 这样可以确保当其他队伍降下来占据这个位置时，也能正确处理
        let startIndex = this.currentJudgingIndex;
        
        // 从 currentJudgingIndex 开始，从后往前逐个队伍检查
        for (let i = startIndex; i >= 0; i--) {
            const teamData = this.rollData[i];
            if (!teamData) {
                continue;
            }
            
            const rankedItem = this.rollDataMap.get(teamData.team_id);
            if (!rankedItem) {
                continue;
            }
            
            const solutions = this.rollSolutionMap[teamData.team_id];
            if (!solutions) {
                continue;
            }
            
            // 检查是否有frozen题目
            const hasFrozenProblems = solutions.frozen && Object.keys(solutions.frozen).length > 0;
            
            // 优先处理：如果有frozen题目，返回需要判题
            if (hasFrozenProblems) {
                const frozenProblems = Object.keys(solutions.frozen)
                    .filter(problemId => solutions.frozen[problemId])
                    .map(problemId => {
                        const problem = this.problemMap[problemId];
                        return {
                            problemId: problemId,
                            num: problem ? (problem.num !== undefined ? problem.num : 9999) : 9999
                        };
                    })
                    .sort((a, b) => a.num - b.num);
                
                if (frozenProblems.length > 0) {
                    // 更新 currentJudgingIndex 为当前位置
                    this.currentJudgingIndex = i;
                    return {
                        team_id: teamData.team_id,
                        problemId: frozenProblems[0].problemId, // 序号最小的题目
                        needsAward: false, // 需要判题
                        needsSkip: false // 不需要跳过
                    };
                }
            }
            
            // 如果没有frozen题目，需要判断下一步
            if (!hasFrozenProblems) {
                // 如果这个队伍刚揭晓完且还在同一位置，直接检查获奖，不要再"看"它一次
                if (this.judgingTeamIdLast === teamData.team_id) {
                    // 刚揭晓完的队伍还在同一位置，直接检查是否需要显示获奖（非打星队且未显示过）
                    if(teamData.solved > 0) {
                        this.flgAwardRankReady = true;  // 有尘埃落定的队伍有ac，则获奖线已确定
                    }
                    if (this.ShouldShowAward(teamData, rankedItem, this.realRankMap)) {
                        // 需要显示获奖，直接返回
                        this.currentJudgingIndex = i;
                        return {
                            team_id: teamData.team_id,
                            problemId: null,
                            needsAward: true,
                            needsSkip: false
                        };
                    }
                    // 如果不需要显示获奖（打星队或不在获奖区），且已经确认过尘埃落定，直接跳过
                    if (this.settledTeams.has(teamData.team_id)) {
                        continue;
                    }
                }
                // 检查是否在获奖区且需要显示获奖（非打星队）
                if (this.ShouldShowAward(teamData, rankedItem, this.realRankMap)) {
                    // 在获奖区且没有frozen题目，且未显示过获奖，需要显示获奖
                    this.currentJudgingIndex = i;
                    return {
                        team_id: teamData.team_id,
                        problemId: null,
                        needsAward: true,
                        needsSkip: false
                    };
                }
                
                // 如果队伍还没有被确认过"尘埃落定"，需要确认一次（包括打星队）
                // 打星队也要"看"，也要确认尘埃落定，只是不参与评奖
                if (!this.settledTeams.has(teamData.team_id)) {
                    this.currentJudgingIndex = i;
                    return {
                        team_id: teamData.team_id,
                        problemId: null,
                        needsAward: false,
                        needsSkip: true // 需要跳过（队伍已全部揭晓），但需要确认"尘埃落定"
                    };
                }
                
                // 如果队伍已经确认过"尘埃落定"，直接跳过这个位置，继续检查下一个
            }
            
            // 如果到这里，说明队伍有frozen题目或其他情况，已经在上面处理了
            // 正常情况下不会到达这里
        }
        
        // 如果遍历完所有队伍，说明滚榜完成
        this.currentJudgingIndex = -1;
        return null;
    }
    
    /**
     * 判题确认：定位到当前索引的队伍，先高亮，再判断需要判题、获奖还是跳过
     */
    JudgeConfirm() {
        // 强检查：如果滚榜已完成（currentJudgingIndex < 0），坚决停止所有操作
        if (this.currentJudgingIndex < 0) {
            this.ShowMessage('滚榜完成');
            this.currentRollStep = null;
            this.judgingTeamId = null;
            this.judgingProblemId = null;
            // 强制清空状态变量，避免残留导致奇怪行为
            this.awardShownTeams.clear();
            this.settledTeams.clear();
            this.isInAwardArea = false;
            this.currentAwardLevel = 0;
            this.startAwardLevel = 0;
            this.StopRoll();
            return null;
        }
        this.DoUpdateAwardInfo();
        
        const isSimulating = this.isSimulating;
        
        // 查找下一个要处理的队伍
        const nextJudging = this.FindNextJudging();
        if (!nextJudging) {
            // 没有找到，滚榜完成
            this.ShowMessage('滚榜完成');
            this.currentRollStep = null;
            this.currentJudgingIndex = -1;
            this.judgingTeamId = null;
            this.judgingProblemId = null;
            // 强制清空状态变量，避免残留导致奇怪行为
            this.awardShownTeams.clear();
            this.settledTeams.clear();
            this.isInAwardArea = false;
            this.currentAwardLevel = 0;
            this.startAwardLevel = 0;
            this.StopRoll();
            return null;
        }
        
        // 设置当前判题的队伍和题目
        this.judgingTeamId = nextJudging.team_id;
        this.judgingProblemId = nextJudging.problemId;
        this.judgingTeamIdLast = nextJudging.team_id;
        
        // 先高亮当前队伍（无论是否需要判题或获奖）
        this.currentRollStep = 'confirm';
        if (!isSimulating) {
            // 使用 requestAnimationFrame 确保DOM有时间渲染高亮效果
            requestAnimationFrame(() => {
                // 清除之前的高亮
                this.container.querySelectorAll('.rank-row.roll-judging').forEach(row => {
                    row.classList.remove('roll-judging');
                });
                this.container.querySelectorAll('.problem-item.roll-judging-problem').forEach(item => {
                    item.classList.remove('roll-judging-problem');
                });
                
                // 高亮当前队伍
                const teamRow = document.getElementById(`rank-grid-${nextJudging.team_id}`);
                if (teamRow) {
                    teamRow.classList.add('roll-judging');
                    
                    // 关键修复：如果需要显示获奖（needsAward），不要立即滚动
                    // 因为modal显示时会改变DOM布局，导致滚动位置计算错误，视口会跳到错误位置
                    // 只有在需要判题（needsProblem）或需要跳过（needsSkip）时才滚动
                    // 对于获奖情况，等待modal显示完成后再决定是否需要滚动
                    // if (!nextJudging.needsAward) {
                        // 使用滚动管理，确保窗口跟随当前激活的队伍（这会中断f键的滚动）
                        this.scrollToTeam(nextJudging.team_id);
                    // }
                    // 注意：如果需要显示获奖，跳过滚动，避免modal显示时布局变化导致滚动计算错误
                    
                    // 如果有要揭晓的题目，也高亮题目
                    if (nextJudging.problemId) {
                        const problem = this.problemMap[nextJudging.problemId];
                        if (problem) {
                            const problemAlphabetIdx = this.GetProblemAlphabetIdx(problem.num);
                            const problemItem = teamRow.querySelector(
                                `.problem-item[d-pro-idx="${problemAlphabetIdx}"]`
                            );
                            if (problemItem) {
                                problemItem.classList.add('roll-judging-problem');
                            }
                        }
                    }
                }
            });
        }
                
        // 如果需要跳过（队伍已全部揭晓且不需要获奖），标记为已"尘埃落定"并继续下一个
        if (nextJudging.needsSkip) {
            // 标记该队伍已经确认过"尘埃落定"
            this.settledTeams.add(nextJudging.team_id);
            
            // 这个队伍已经尘埃落定，currentRollStep设为null 直接进入下一次 JudgeConfirm
            this.currentRollStep = null;
            
            // 直接继续下一个队伍（currentJudgingIndex 已经在 FindNextJudging 中更新）
            this.currentJudgingIndex--; // 移动到下一个队伍
            // 强检查：如果索引变为负数，说明滚榜完成，不要继续
            if (this.currentJudgingIndex >= 0) {
                // 自动模式下延迟后继续
                this.RollNext();
            } else {
                // 滚榜完成，调用 JudgeConfirm 处理结束逻辑（这会再次检查并停止）
                this.JudgeConfirm();
            }
            return nextJudging;
        }
        
        // 如果需要显示获奖，进入获奖高亮步骤（下一步再弹出modal）
        if (nextJudging.needsAward) {
            this.currentRollStep = 'award_highlight';
            
            // 自动模式下延迟后进入下一步（弹出modal）
            this.TryAutoRolling('RollNextStep', isSimulating);
            
            return nextJudging;
        }
        
        // 需要判题，设置 confirm 步骤
        this.currentRollStep = 'confirm';
        
        // 自动模式下自动进入下一步
        this.TryAutoRolling('JudgeDo', isSimulating);
        
        return nextJudging;
    }
    
    /**
     * 执行判题
     */
    JudgeDo() {
        this.currentRollStep = 'do';
        
        if (!this.judgingTeamId) {
            this.currentRollStep = null;
            return;
        }
        
        // 如果没有题目，直接跳过排序进入获奖检查
        if (!this.judgingProblemId) {
            this.currentRollStep = 'sort';
            // 自动模式下延迟后进入下一步
            this.TryAutoRolling('RollNextStep');
            return;
        }
        
        const team_solutions = this.rollSolutionMap[this.judgingTeamId];
        const team_problem_solutions = team_solutions.problems[this.judgingProblemId] || [];
        
        // 检查是否AC，并计算submitCount
        let isAC = false;
        let submitCount = 0;
        let acTime = '';
        let firstAcIndex = -1;
        
        // 找到第一次AC的位置
        for (let i = 0; i < team_problem_solutions.length; i++) {
            if (team_problem_solutions[i].result === 4) {
                isAC = true;
                acTime = team_problem_solutions[i].in_date;
                firstAcIndex = i;
                break;
            }
        }
        
        if (isAC && firstAcIndex >= 0) {
            submitCount = firstAcIndex + 1;
        } else {
            submitCount = team_problem_solutions.length;
        }
        
        // 更新队伍数据
        const teamData = this.rollDataMap ? this.rollDataMap.get(this.judgingTeamId) : null;
        let penaltyChange = 0;
        if (teamData) {
            if (isAC) {
                teamData.solved += 1;
                const startTime = new Date(this.data.contest.start_time).getTime();
                const acTimeMs = new Date(acTime).getTime();
                const deltaSeconds = Math.floor((acTimeMs - startTime) / 1000);
                penaltyChange = deltaSeconds + (submitCount - 1) * 20 * 60;
                teamData.penalty += penaltyChange;
                // 更新题目状态
                teamData.problemStats[this.judgingProblemId].status = 'ac';
                teamData.problemStats[this.judgingProblemId].submitCount = submitCount;
                teamData.problemStats[this.judgingProblemId].lastSubmitTime = this.formatDuration(deltaSeconds * 1000);
                // 新的 ac 出现，考虑更新获奖线
                if(teamData.solved == 1) {
                    this.DoUpdateAwardInfo();
                }
            } else {
                teamData.problemStats[this.judgingProblemId].status = 'wa';
                teamData.problemStats[this.judgingProblemId].submitCount = submitCount;
                if (submitCount > 0) {
                    const lastTime = new Date(team_problem_solutions[submitCount - 1].in_date).getTime();
                    const startTime = new Date(this.data.contest.start_time).getTime();
                    const deltaSeconds = Math.floor((lastTime - startTime) / 1000);
                    teamData.problemStats[this.judgingProblemId].lastSubmitTime = this.formatDuration(deltaSeconds * 1000);
                }
            }
        }
                
        // 移除frozen标记
        delete team_solutions.frozen[this.judgingProblemId];
        
        // 如果揭晓的题目是AC，需要重新计算一血状态
        if (isAC && acTime) {
            this.UpdateFirstBloodForProblem(this.judgingProblemId);
        }
        
        // 更新上次判题的队伍ID
        this.judgingTeamIdLast = this.judgingTeamId;
        
        // 模拟模式：跳过DOM操作
        if (this.isSimulating) {
            return;
        }
        
        this.UpdateRankRowForJudging(this.judgingTeamId);
        
        // 如果揭晓的题没有AC，队伍排名不会改变，跳过排序但执行其他逻辑（如检查获奖）
        if (!isAC) {
            // 非AC时，调用 JudgeSort(false) 跳过排序，但保留其他逻辑（如检查获奖）
            // 如果是自动模式，延迟后调用；非自动模式直接执行
            if (this.isAutoRolling) {
                // 自动模式：延迟后调用 JudgeSort(false)
                // 注意：不能使用 TryAutoRolling('JudgeSort')，因为 TryAutoRolling 会调用 JudgeSort()（默认 true）
                // 我们需要传入 false，所以直接在这里处理延迟
                const sortDelay = this.isInAwardArea ? Math.max(200, this.autoSpeed * 0.2) : 50;
                setTimeout(() => {
                    this.JudgeSort(false);
                }, sortDelay);
            } else {
                this.JudgeSort(false);
                return;
                // // 非自动模式：直接执行
                // this.JudgeSort(false);
            }
            return;
        }
        
        // AC了，需要排序（因为排名可能改变）
        // 自动模式下自动进入下一步
        if (this.judgingProblemId !== null) {
            this.TryAutoRolling('JudgeSort');
        }
    }
    
    /**
     * 为特定题目重新计算并更新一血状态
     * 在滚榜揭晓题目时调用，遍历所有队伍的已揭晓AC提交，找出最早的一个
     * @param {string} problemId - 题目ID
     */
    UpdateFirstBloodForProblem(problemId) {
        if (!this.rollSolutionMap || !this.teamMap || !problemId) {
            return;
        }
        
        // 用于记录最早AC的队伍和时间
        let globalFirstBlood = null;  // { team_id, in_date, isStarTeam }
        let regularFirstBlood = null; // { team_id, in_date } (仅非打星队)
        
        // 遍历所有队伍
        for (const teamId in this.rollSolutionMap) {
            const solutions = this.rollSolutionMap[teamId];
            if (!solutions || !solutions.problems || !solutions.problems[problemId]) {
                continue;
            }
            
            // 检查该题目的提交是否已揭晓（非frozen）
            const isFrozen = solutions.frozen && solutions.frozen[problemId];
            if (isFrozen) {
                // 还未揭晓，跳过
                continue;
            }
            
            // 遍历该队伍的所有提交，找出第一次AC
            const problemSolutions = solutions.problems[problemId];
            for (const solution of problemSolutions) {
                if (solution.result === 4) { // AC
                    const team = this.teamMap[teamId];
                    const isStarTeam = team && team.tkind === 2;
                    const inDate = solution.in_date;
                    
                    // 更新全局一血（所有队伍）
                    if (!globalFirstBlood || inDate < globalFirstBlood.in_date) {
                        globalFirstBlood = {
                            team_id: teamId,
                            in_date: inDate,
                            isStarTeam: isStarTeam
                        };
                    }
                    
                    // 更新常规一血（仅非打星队）
                    if (!isStarTeam) {
                        if (!regularFirstBlood || inDate < regularFirstBlood.in_date) {
                            regularFirstBlood = {
                                team_id: teamId,
                                in_date: inDate
                            };
                        }
                    }
                    
                    // 找到第一次AC就停止（因为题目提交是按时间顺序的）
                    break;
                }
            }
        }
        
        // 更新全局一血记录
        if (globalFirstBlood) {
            // 如果之前有记录但新的更早，或者之前没有记录，则更新
            const existingGlobal = this.map_fb?.global?.[problemId];
            if (!existingGlobal || globalFirstBlood.in_date < existingGlobal.in_date) {
                if (!this.map_fb.global) {
                    this.map_fb.global = {};
                }
                this.map_fb.global[problemId] = {
                    team_id: globalFirstBlood.team_id,
                    in_date: globalFirstBlood.in_date,
                    isStarTeam: globalFirstBlood.isStarTeam
                };
            }
        }
        
        // 更新常规一血记录
        if (regularFirstBlood) {
            // 如果之前有记录但新的更早，或者之前没有记录，则更新
            const existingRegular = this.map_fb?.regular?.[problemId];
            if (!existingRegular || regularFirstBlood.in_date < existingRegular.in_date) {
                if (!this.map_fb.regular) {
                    this.map_fb.regular = {};
                }
                this.map_fb.regular[problemId] = {
                    team_id: regularFirstBlood.team_id,
                    in_date: regularFirstBlood.in_date,
                    isStarTeam: false
                };
            }
        }
        
        // 一血状态更新后，需要重新渲染所有相关队伍的题目状态，以便显示一血标记
        // 这里我们会在 UpdateRankRowForJudging 或 IncrementalUpdate 中自动更新
        // 但如果需要立即更新，可以调用 RenderRank()
        // 为了性能考虑，这里不立即渲染，让后续的 UpdateRankRowForJudging 和 IncrementalUpdate 处理
    }
    
    /**
     * 更新单个队伍的排名行
     */
    UpdateRankRowForJudging(teamId) {
        const item = this.rollDataMap.get(teamId);
        if (item && this.container) {
            const row = document.getElementById(`rank-grid-${teamId}`);
            if (row) {
                const index = this.rollDataMap.get(teamId).displayIdx;
                this.UpdateRankRow(item, item.displayRank, index);
            }
        }
    }
    
    /**
     * 判题排序
     * @param {boolean} flg_do_sort - 是否执行排序逻辑，默认 true。如果为 false，则跳过排序（用于非AC情况），但保留其他逻辑（如检查获奖）
     */
    async JudgeSort(flg_do_sort = true) {
        this.currentRollStep = 'sort';
        
        // 关键：在执行动画前，保存要上升的队伍ID（刚揭晓题目的队伍）
        // 使用 judgingTeamIdLast，因为它是刚刚揭晓题目的队伍ID
        // 这样即使 judgingTeamId 被更新，我们也能正确识别上升的队伍
        this.animatingRisingTeamId = this.judgingTeamIdLast || this.judgingTeamId;
        
        // 记录排序前的位置（无论是否排序都需要记录，用于后续判断位置是否变化）
        let teamIndexBefore = null;
        if (this.judgingTeamId) {
            const displayListBefore = this.FilterByStarMode(this.rollData, this.starMode);
            const rankedListBefore = this.CalculateRankInfo(displayListBefore);
            const indexBefore = rankedListBefore.findIndex(item => item.team_id === this.judgingTeamId);
            if (indexBefore >= 0) {
                teamIndexBefore = indexBefore;
            }
        }
        
        // 如果不需要排序（flg_do_sort = false），跳过排序动画，直接执行后续逻辑
        if (!flg_do_sort) {
            // 揭晓没 ac，不需要排序，直接检查位置和frozen状态
            const solutions = this.rollSolutionMap[this.judgingTeamId];
            const hasFrozenProblems = solutions && solutions.frozen && Object.keys(solutions.frozen).length > 0;
            
            // 由于没有排序，位置应该不会改变（teamIndexBefore === indexAfter）
            // 如果位置稳定且没有frozen题目，检查获奖
            if (!hasFrozenProblems) {
                this.currentRollStep = 'sort_award';
                // 修复：根据自动/手动模式决定继续方式
                if (this.isAutoRolling) {
                    // 自动模式：调用 TryAutoRolling 继续流程
                    this.TryAutoRolling('RollNextStep');
                } else {
                    // 手动模式：直接调用 JudgeAward 维持原流程
                    this.JudgeAward();
                }
                return;
            }            
            // 继续确认（检查当前位置是否有需要处理的）
            this.JudgeConfirm();
            return;
        }
        
        // 需要排序：使用增量更新和动画（不阻塞，让动画在后台运行，确保键盘响应不被阻塞）
        this.RollSort();

        const judgingRow = this.judgingTeamId ? document.getElementById(`rank-grid-${this.judgingTeamId}`) : null;
        if (judgingRow && judgingRow.classList.contains('roll-judging')) {
            judgingRow.style.zIndex = '99';
        }
        const rankGrid = this.container.querySelector('.rank-grid');
        if (rankGrid && rankGrid.children.length > 0) {
            // 启动动画，但不等待（后台运行），让键盘事件可以立即响应
            this.IncrementalUpdate(this.rollData).then(() => {
                // 不移除z-index，让上升队伍的z-index自然保留
            });
            
            // 立即检查排序后位置和frozen状态（不等待动画）
            if (this.judgingTeamId && teamIndexBefore !== null) {
                const indexAfter = this.rollDataMap.get(this.judgingTeamId)?.displayIdx;
                const solutions = this.rollSolutionMap[this.judgingTeamId];
                const hasFrozenProblems = solutions && solutions.frozen && Object.keys(solutions.frozen).length > 0;
                
                // 如果位置稳定且没有frozen题目，检查获奖
                if (teamIndexBefore === indexAfter && !hasFrozenProblems) {
                    // 滚动到队伍（在后台执行，不阻塞）
                    this.scrollToTeamAfterAnimation(this.judgingTeamId);
                    this.currentRollStep = 'sort_award';
                    this.JudgeAward();
                    return;
                }
            }
            
            // 位置变化或还有frozen题目，立即继续处理（不等待动画，确保键盘响应不被阻塞）
            // 关键修复：排序后可能有其他队伍降下来占据当前位置，所以不应该直接 currentJudgingIndex--
            // 而是保持 currentJudgingIndex 不变，让 FindNextJudging 检查当前位置的队伍
            // 如果有frozen题目，currentJudgingIndex保持不变（继续处理当前队）
            // 如果没有frozen题目，也需要保持 currentJudgingIndex 不变，检查当前位置（可能有队伍降下来）
            // 只有在 FindNextJudging 确定当前位置不需要处理时，才会更新索引
            // 继续确认（可能继续处理当前队的下一个题目，或检查当前位置是否有新降下来的队伍）
            this.JudgeConfirm();
        } else {
            this.RenderRank(this.rollData);
            // 滚动到当前队伍
            if (this.judgingTeamId) {
                this.scrollToTeamAfterAnimation(this.judgingTeamId);
            }
            // 继续处理（排序后可能有位置变化）
            // 关键修复：排序后可能有其他队伍降下来占据当前位置，所以不应该直接修改 currentJudgingIndex
            // 而是保持 currentJudgingIndex 不变，让 FindNextJudging 检查当前位置的队伍
            // 如果有frozen题目，currentJudgingIndex保持不变（继续处理当前队）
            // 如果没有frozen题目，也需要保持 currentJudgingIndex 不变，检查当前位置（可能有队伍降下来）
            // 只有在 FindNextJudging 确定当前位置不需要处理时，才会更新索引
            // 继续确认（可能继续处理当前队的下一个题目，或检查当前位置是否有新降下来的队伍）
            this.JudgeConfirm();
        }
    }
    
    // /**
    //  * 子类重写获奖排名，获奖排名要随着滚榜更新变化，数据来源应当是不断揭晓的滚榜数据
    //  */
    // GetAwardRanks(options = {}) {
    //     const {
    //         flg_ac_team_base = false,     // 是否以总数为基数
    //         customBaseCount = null,    // 自定义基数（优先级最高）
    //         starMode = null
    //     } = options;
    //     if (!this.data || !this.data.contest) {
    //         console.error("数据未初始化");
    //         return [0, 0, 0];
    //     }
    //     const awardRatio = this.data.contest.award_ratio;
    //     const ratios = RankToolParseAwardRatio(awardRatio);
    //     const tmp_star_mode = starMode ? starMode : this.starMode;
        
    //     // 先调用 FilterByStarMode 设置 isStar 属性，然后再计算有效队伍数
    //     // FilterByStarMode 会根据 starMode 设置 isStar 属性或过滤掉打星队
    //     const filteredList = this.FilterByStarMode(this.rollData ?? this.rankList, tmp_star_mode);
    //     // 获取有效队伍数（排除打星队和0题队伍）
    //     // 注意：starMode === 1 时，打星队已被 FilterByStarMode 过滤掉，所以这里只需要检查 isStar
    //     // starMode === 0 时，打星队 isStar=true，会被排除
    //     // starMode === 2 时，打星队 isStar=false，会被计入
    //     const validTeamNum = customBaseCount ? customBaseCount : 
    //         (flg_ac_team_base ? 
    //             filteredList.filter(item => !item.isStar) : 
    //             filteredList.filter(item => item.solved > 0 && !item.isStar)
    //         ).length;
            
    //     return RankToolGetAwardRank(validTeamNum, ratios.gold, ratios.silver, ratios.bronze);
    // }
    /**
     * 获取获奖级别
     */
    GetAwardLevel(rank) {
        if (rank === '*' || rank <= 0) return 0;
        if (rank <= this.rankGold) return 3;
        if (rank <= this.rankSilver) return 2;
        if (rank <= this.rankBronze) return 1;
        return 0;
    }
    
    /**
     * 判断是否需要显示获奖
     * @param {Object} teamData - 队伍数据
     * @param {Object} rankedItem - 排名项（包含displayRank等）
     * @param {Map} realRankMap - 真实排名映射
     * @returns {boolean} - 是否需要显示获奖
     */
    ShouldShowAward(teamData, rankedItem, realRankMap) {
        // 必须是非打星队
        if (rankedItem.isStar) {
            return false;
        }
        // 获取真实排名信息
        const realRankInfo = realRankMap.get(teamData.team_id);
        if (!realRankInfo || realRankInfo.displayRank === '*') {
            return false;
        }
        // 当前排名不一定与真实排名一致，因为并列时，前面的队伍滚榜结果会影响此队实际 displayRank
        // 所以判断依据是，真实排名在奖区，且滚榜目前排名不大于真实排名即显示modal
        return rankedItem.displayRank <= realRankInfo.displayRank &&
                realRankInfo.displayRank <= this.rankBronze &&
               !this.awardShownTeams.has(teamData.team_id);
    }
    
    /**
     * 计算真实排名（基于所有题目都已揭晓）
     * 关键修复：复用 rank.js 的 CalculateRank() 方法，而不是重写排序算法
     */
    CalculateRealRankMap() {
        if (!this.rollData || this.rollData.length === 0 || !this.rollSolutionMap || !this.teamMap) {
            return ;
        }
        
        this.ProcessData(true);
        const displayList = this.FilterByStarMode(this.rankList, this.starMode);
        this.rankList = this.CalculateRankInfo(displayList);
        this.realRankMap = new Map();
        this.rankList.forEach((item, index) => {
            this.realRankMap.set(item.team_id, item);
        });
        // 更新获奖线为真实获奖线
        this.UpdateAwardInfo();
    }
    
    /**
     * 判题获奖
     */
    JudgeAward() {
        this.currentRollStep = 'award';
        
        if (!this.judgingTeamId) {
            this.JudgeConfirm();
            return;
        }
        
        const currentItem = this.rollDataMap.get(this.judgingTeamId);
        
        if (!currentItem) {
            this.JudgeConfirm();
            return;
        }
        
        const realRankInfo = this.realRankMap.get(this.judgingTeamId);
        
        // 检查该队伍是否已经没有frozen题目
        const solutions = this.rollSolutionMap[this.judgingTeamId];
        const hasFrozenProblems = solutions && solutions.frozen && Object.keys(solutions.frozen).length > 0;
        
        // 检查是否需要显示奖励
        let shouldShowAward = false;
        let awardType = null;
        let newAwardLevel = 0;
        // 使用封装的函数判断是否需要显示获奖
        if (!hasFrozenProblems) {
            // 判断是否需要显示获奖
            shouldShowAward = this.ShouldShowAward({team_id: this.judgingTeamId}, currentItem, this.realRankMap);
            if (shouldShowAward) {
                const realRank = realRankInfo.displayRank;
                if (realRank !== '*' && realRank > 0) {
                    newAwardLevel = this.GetAwardLevel(realRank);
                    if (newAwardLevel > 0) {
                        if (realRank <= this.rankGold) {
                            awardType = 'gold';
                        } else if (realRank <= this.rankSilver) {
                            awardType = 'silver';
                        } else if (realRank <= this.rankBronze) {
                            awardType = 'bronze';
                        }
                    }
                }
            } else {
                // 即使不需要显示获奖，也需要计算获奖级别用于状态跟踪
                const realRank = realRankInfo.displayRank;
                if (realRank !== '*' && realRank > 0) {
                    newAwardLevel = this.GetAwardLevel(realRank);
                }
                // // 没奖，直接下一步
                // this.RollNext();
            }
        }
        
        // 检测是否进入新的获奖级别
        const enteredAwardArea = !this.isInAwardArea && newAwardLevel > 0;
        
        // 更新获奖区域状态
        if (newAwardLevel > 0) {
            this.isInAwardArea = true;
        }
        if (newAwardLevel > this.currentAwardLevel) {
            this.currentAwardLevel = newAwardLevel;
        }
        
        // 从无奖进入获奖区域时，停止自动模式
        if (this.isAutoRolling && enteredAwardArea) {
            this.isAutoRolling = false;
            this.autoSpeed = this.DEFAULT_ROLL_SPEED;
            if (!shouldShowAward) {
                this.currentRollStep = null;
                this.ClearJudgingHighlight();
                return;
            }
        }
        
        // 如果获奖，显示奖励模态框
        if (shouldShowAward && awardType) {
            this.currentRollStep = 'award_open'; // 使用 award_open 步骤
            this.ShowAward(this.judgingTeamId, awardType);
            
            // 标记该队伍已经显示过获奖，避免重复弹出
            this.awardShownTeams.add(this.judgingTeamId);
            
            // 更新获奖区域状态
            if (!this.isInAwardArea) {
                this.isInAwardArea = true;
                if (this.isAutoRolling) {
                    this.isAutoRolling = false;
                    this.autoSpeed = this.DEFAULT_ROLL_SPEED;
                }
            }
            
            // 自动模式下延迟后关闭modal（进入 award_close 步骤）
            this.TryAutoRolling('RollNextStep');

        } else {
            // 没获奖，直接继续
            this.currentRollStep = null;
            this.ClearJudgingHighlight();
            
            // 自动模式下延迟后继续
            this.TryAutoRolling('JudgeConfirm');
        }
    }
    
    /**
     * 清除高亮
     */
    ClearJudgingHighlight() {
        if (this.container) {
            this.container.querySelectorAll('.rank-row.roll-judging').forEach(row => {
                row.classList.remove('roll-judging');
            });
            this.container.querySelectorAll('.problem-item.roll-judging-problem').forEach(item => {
                item.classList.remove('roll-judging-problem');
            });
        }
    }
    /**
     * 获取滚动容器（全屏模式下是container，否则是window）
     */
    getScrollContainer() {
        if (this.container && this.container.classList.contains('fullscreen')) {
            return this.container;
        }
        // 非全屏模式下，尝试找到有滚动条的父容器，否则使用window
        let element = this.container;
        while (element && element !== document.body) {
            const overflow = window.getComputedStyle(element).overflowY;
            if (overflow === 'auto' || overflow === 'scroll') {
                return element;
            }
            element = element.parentElement;
        }
        return window;
    }

    /**
     * 滚动到指定队伍（确保窗口跟随，队伍出现在屏幕下方1/3位置）
     * 使用防抖机制，快速调用时只执行最后一次滚动
     * 如果连续滚动到同一个队伍，且位置变化很小，则跳过滚动以避免抖动
     */
    scrollToTeam(teamId) {
        // 中断 f 键的滚动动画（如果正在运行）
        if (this.scrollAnimationRunning && this.scrollAnimationId !== null) {
            cancelAnimationFrame(this.scrollAnimationId);
            this.scrollAnimationRunning = false;
            this.scrollAnimationId = null;
        }
        
        // 清理 f 键滚动的所有相关定时器（避免延迟触发导致窗口滑动）
        if (this.fastSkipScrollTimeout !== null) {
            clearTimeout(this.fastSkipScrollTimeout);
            this.fastSkipScrollTimeout = null;
        }
        if (this.fastSkipJudgeConfirmTimeout !== null) {
            clearTimeout(this.fastSkipJudgeConfirmTimeout);
            this.fastSkipJudgeConfirmTimeout = null;
        }
        
        // 记录待滚动的队伍ID
        this.pendingScrollTeamId = teamId;
        
        // 取消之前的滚动
        if (this.scrollTimeout) {
            clearTimeout(this.scrollTimeout);
            this.scrollTimeout = null;
        }
        
        // 使用多个 requestAnimationFrame 确保DOM完全更新后再滚动
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    // 再次检查是否还是这个队伍（避免被新的调用覆盖）
                    if (this.pendingScrollTeamId === teamId) {
                        const teamRow = document.getElementById(`rank-grid-${teamId}`);
                        if (teamRow) {
                            // 如果切换到不同队伍，清除上次滚动记录
                            if (this.lastScrollTeamId !== null && this.lastScrollTeamId !== teamId) {
                                this.lastScrollTeamId = null;
                                this.lastScrollY = null;
                            }
                            
                            // 如果是同一个队伍，且上次滚动位置很近，则跳过滚动以避免抖动
                            if (this.lastScrollTeamId === teamId && this.lastScrollY !== null) {
                                const scrollContainer = this.getScrollContainer();
                                let currentScrollY;
                                if (scrollContainer === window) {
                                    currentScrollY = window.scrollY || window.pageYOffset;
                                } else {
                                    currentScrollY = scrollContainer.scrollTop;
                                }
                                
                                // 如果当前位置和上次滚动位置相差小于10像素，且队伍仍然在视口下方1/3附近，则跳过滚动
                                const scrollDiff = Math.abs(currentScrollY - this.lastScrollY);
                                if (scrollDiff < 10) {
                                    const elementRect = teamRow.getBoundingClientRect();
                                    const viewportHeight = scrollContainer === window ? window.innerHeight : scrollContainer.clientHeight;
                                    const targetOffset = scrollContainer === window ? viewportHeight / 3 : viewportHeight / 3 * 2;
                                    const currentOffset = scrollContainer === window ? elementRect.top : (elementRect.top - scrollContainer.getBoundingClientRect().top);
                                    
                                    // 如果当前偏移和目标偏移相差小于20像素，则认为位置稳定，跳过滚动
                                    if (Math.abs(currentOffset - targetOffset) < 20) {
                                        this.pendingScrollTeamId = null;
                                        return;
                                    }
                                }
                            }
                            
                            // 计算目标滚动位置
                            const scrollContainer = this.getScrollContainer();
                            const elementRect = teamRow.getBoundingClientRect();
                            let targetScrollY;
                            
                            if (scrollContainer === window) {
                                const viewportHeight = window.innerHeight;
                                const scrollY = window.scrollY || window.pageYOffset;
                                const elementTop = elementRect.top + scrollY;
                                targetScrollY = elementTop - (viewportHeight / 3);
                            } else {
                                const containerRect = scrollContainer.getBoundingClientRect();
                                const containerScrollTop = scrollContainer.scrollTop;
                                const containerHeight = containerRect.height;
                                const elementTopRelative = elementRect.top - containerRect.top + containerScrollTop;
                                targetScrollY = elementTopRelative - (containerHeight / 3 * 2);
                            }
                            
                            // 记录目标位置（在滚动完成前就记录，用于下次判断）
                            this.lastScrollY = targetScrollY;
                            this.lastScrollTeamId = teamId;
                            
                            // 执行滚动
                            this.scrollToElementBottomThird(teamRow);
                        }
                        this.pendingScrollTeamId = null;
                    }
                });
            });
        });
    }
    
    /**
     * 滚动元素到屏幕下方1/3位置
     */
    scrollToElementBottomThird(element, scrollSpeed = null) {
        const scrollContainer = this.getScrollContainer();
        const elementRect = element.getBoundingClientRect();
        
        if (scrollContainer === window) {
            // 窗口滚动
            const viewportHeight = window.innerHeight;
            const scrollY = window.scrollY || window.pageYOffset;
            const elementTop = elementRect.top + scrollY;
            // 目标：元素顶部距离视口顶部 = 视口高度的 1/3
            const targetScrollY = elementTop - (viewportHeight / 3);
            const currentScrollY = scrollY;
            const scrollDistance = Math.abs(targetScrollY - currentScrollY);
            
            // 如果提供了滚动速度，使用自定义滚动速度实现平滑滚动
            if (scrollSpeed && scrollSpeed > 0) {
                const scrollDuration = (scrollDistance / scrollSpeed) * 1000; // 转为毫秒
                const minScrollDuration = 500; // 最小滚动时长
                const maxScrollDuration = 30000; // 最大滚动时长（增加到30秒，让慢速滚动完全生效）
                const finalScrollDuration = Math.max(minScrollDuration, Math.min(maxScrollDuration, scrollDuration));
                
                // 使用requestAnimationFrame实现平滑滚动
                const startScrollY = currentScrollY;
                const startTime = performance.now();
                
                const animateScroll = (currentTime) => {
                    const elapsed = currentTime - startTime;
                    const progress = Math.min(elapsed / finalScrollDuration, 1);
                    
                    // 使用线性插值，实现匀速滚动（不使用缓动函数）
                    const currentScrollY = startScrollY + (targetScrollY - startScrollY) * progress;
                    window.scrollTo(0, Math.max(0, currentScrollY));
                    
                    if (progress < 1) {
                        requestAnimationFrame(animateScroll);
                    }
                };
                
                requestAnimationFrame(animateScroll);
            } else {
                // 使用浏览器原生平滑滚动
                window.scrollTo({
                    top: Math.max(0, targetScrollY),
                    behavior: 'smooth'
                });
            }
        } else {
            // 容器滚动
            const containerRect = scrollContainer.getBoundingClientRect();
            const containerScrollTop = scrollContainer.scrollTop;
            const containerHeight = containerRect.height;
            
            // 元素相对于容器的位置
            const elementTopRelative = elementRect.top - containerRect.top + containerScrollTop;
            // 目标：元素顶部距离容器顶部 = 容器高度的 1/3
            const targetScrollTop = elementTopRelative - (containerHeight / 3 * 2);
            const currentScrollTop = containerScrollTop;
            const scrollDistance = Math.abs(targetScrollTop - currentScrollTop);
            
            // 如果提供了滚动速度，使用自定义滚动速度实现平滑滚动
            if (scrollSpeed && scrollSpeed > 0) {
                const scrollDuration = (scrollDistance / scrollSpeed) * 1000; // 转为毫秒
                const minScrollDuration = 500; // 最小滚动时长
                const maxScrollDuration = 30000; // 最大滚动时长（增加到30秒，让慢速滚动完全生效）
                const finalScrollDuration = Math.max(minScrollDuration, Math.min(maxScrollDuration, scrollDuration));
                
                // 使用requestAnimationFrame实现平滑滚动
                const startScrollTop = currentScrollTop;
                const startTime = performance.now();
                
                const animateScroll = (currentTime) => {
                    const elapsed = currentTime - startTime;
                    const progress = Math.min(elapsed / finalScrollDuration, 1);
                    
                    // 使用线性插值，实现匀速滚动（不使用缓动函数）
                    const currentScrollTop = startScrollTop + (targetScrollTop - startScrollTop) * progress;
                    scrollContainer.scrollTop = Math.max(0, currentScrollTop);
                    
                    if (progress < 1) {
                        requestAnimationFrame(animateScroll);
                    }
                };
                
                requestAnimationFrame(animateScroll);
            } else {
                // 使用浏览器原生平滑滚动
                scrollContainer.scrollTo({
                    top: Math.max(0, targetScrollTop),
                    behavior: 'smooth'
                });
            }
        }
    }
    
    /**
     * 在动画完成后滚动到当前激活的队伍（确保位置变化后窗口跟随，队伍出现在屏幕下方1/3位置）
     */
    scrollToTeamAfterAnimation(teamId) {
        if (!teamId) return;
        
        // 延迟一点，确保动画完成
        if (this.scrollTimeout) {
            clearTimeout(this.scrollTimeout);
        }
        
        this.scrollTimeout = setTimeout(() => {
            const teamRow = document.getElementById(`rank-grid-${teamId}`);
            if (teamRow && teamRow.classList.contains('roll-judging')) {
                // 使用 requestAnimationFrame 确保在DOM更新后滚动
                requestAnimationFrame(() => {
                    this.scrollToElementBottomThird(teamRow);
                });
            }
            this.scrollTimeout = null;
        }, 150); // 稍微延迟，确保动画完成
    }
    /**
     * 跳到滚榜特定阶段：到达剩余 x 个队伍未揭晓时停住
     * @param {number} remainingTeams - 剩余未揭晓队伍数（x）
     * @param {function(number, number): void} progressCallback - 进度回调 (current, total)
     * @returns {Promise<void>}
     */
    async JumpToSpecificStage(remainingTeams, progressCallback = null, skipAnimation = false) {
        if (!this.rollData || this.rollData.length === 0 || !this.rollSolutionMap) {
            console.warn('JumpToSpecificStage: 数据未初始化');
            return;
        }
        
        // 清空状态跟踪变量，确保模拟过程不会遗留状态
        // 注意：这些变量在 ResetRollDataToInitial 中也会被清空，但在这里再次清空确保更彻底
        this.awardShownTeams.clear();
        this.settledTeams.clear();
        this.isInAwardArea = false;
        
        // 计算总队伍数（考虑打星模式）
        const displayList = this.FilterByStarMode(this.rollData, this.starMode);
        const totalTeams = displayList.length;
        
        // 计算需要完成滚榜的队伍数 y = totalTeams - remainingTeams
        const targetCompletedTeams = Math.max(0, totalTeams - remainingTeams);
        
        if (targetCompletedTeams >= totalTeams) {
            // 所有队伍都已揭晓，无需跳转
            return;
        }
        
        // 备份原始数据
        const rollDataBackup = JSON.parse(JSON.stringify(this.rollData));
        const solutionMapBackup = {};
        for (const [key, value] of Object.entries(this.rollSolutionMap)) {
            solutionMapBackup[key] = {
                problems: value.problems,
                ac: { ...value.ac },
                frozen: { ...value.frozen }
            };
        }
        
        // 创建深拷贝用于模拟
        const simulatedRollData = rollDataBackup.map(teamData => {
            const newTeam = {
                ...teamData,
                problemStats: {}
            };
            for (const problemId in teamData.problemStats) {
                newTeam.problemStats[problemId] = { ...teamData.problemStats[problemId] };
            }
            return newTeam;
        });
        
        const simulatedSolutionMap = {};
        for (const [key, value] of Object.entries(solutionMapBackup)) {
            simulatedSolutionMap[key] = {
                problems: value.problems,
                ac: { ...value.ac },
                frozen: { ...value.frozen }
            };
        }
        
        // 创建优先级队列比较函数
        // solved 小的优先，solved相同则penalty大的优先，penalty也相同则team_id大的优先
        const compareFn = (a, b) => {
            if (a.solved !== b.solved) {
                return b.solved - a.solved; // solved 小的优先（升序）
            }
            if (a.penalty !== b.penalty) {
                return a.penalty - b.penalty; // penalty 大的优先（降序，所以是 a - b）
            }
            // penalty也相同，team_id大的优先（降序）
            return b.team_id.localeCompare(a.team_id);
        };
        
        // 创建优先级队列
        const pq = new window.RankToolPriorityQueue(compareFn);
        
        // 将所有队伍加入优先级队列（深拷贝）
        simulatedRollData.forEach(teamData => {
            pq.push({
                team_id: teamData.team_id,
                solved: teamData.solved,
                penalty: teamData.penalty,
                problemStats: { ...teamData.problemStats }
            });
        });
        
        // 记录已完成的队伍和处理过的队伍ID（用于特效）
        const completedTeams = [];
        const processedTeamIds = new Set(); // 记录所有在跳过过程中被处理的队伍（出队的都算）
        let completedCount = 0;
        
        // 模拟揭晓过程
        while (!pq.empty() && completedCount < targetCompletedTeams) {
            const teamItem = pq.pop();
            
            // 记录该队伍被处理（用于特效）
            processedTeamIds.add(teamItem.team_id);
            
            // 检查该队伍是否还有未揭晓的题目
            const solutions = simulatedSolutionMap[teamItem.team_id];
            if (!solutions || !solutions.frozen) {
                // 没有未揭晓的题目，彻底出队
                completedTeams.push(teamItem);
                completedCount++;
                if (progressCallback) {
                    progressCallback(completedCount, targetCompletedTeams);
                }
                continue;
            }
            
            // 找出编号最小的未揭晓题目
            const frozenProblems = Object.keys(solutions.frozen)
                .filter(problemId => solutions.frozen[problemId])
                .map(problemId => {
                    const problem = this.problemMap[problemId];
                    return {
                        problemId: problemId,
                        num: problem ? (problem.num !== undefined ? problem.num : 9999) : 9999
                    };
                })
                .sort((a, b) => a.num - b.num);
            
            if (frozenProblems.length === 0) {
                // 没有未揭晓的题目了，彻底出队
                completedTeams.push(teamItem);
                completedCount++;
                if (progressCallback) {
                    progressCallback(completedCount, targetCompletedTeams);
                }
                continue;
            }
            
            // 揭晓编号最小的题目
            const problemToReveal = frozenProblems[0];
            const problemId = problemToReveal.problemId;
            const problemSolutions = solutions.problems[problemId] || [];
            
            // 检查是否AC，并计算submitCount
            let isAC = false;
            let submitCount = 0;
            let acTime = '';
            let firstAcIndex = -1;
            
            // 找到第一次AC的位置
            for (let i = 0; i < problemSolutions.length; i++) {
                if (problemSolutions[i].result === 4) {
                    isAC = true;
                    acTime = problemSolutions[i].in_date;
                    firstAcIndex = i;
                    break;
                }
            }
            
            if (isAC && firstAcIndex >= 0) {
                submitCount = firstAcIndex + 1;
            } else {
                submitCount = problemSolutions.length;
            }
            
            // 更新队伍数据
            if (isAC) {
                teamItem.solved += 1;
                const startTime = new Date(this.data.contest.start_time).getTime();
                const acTimeMs = new Date(acTime).getTime();
                const deltaSeconds = Math.floor((acTimeMs - startTime) / 1000);
                const penaltyChange = deltaSeconds + (submitCount - 1) * 20 * 60;
                teamItem.penalty += penaltyChange;
                // 更新题目状态
                if (!teamItem.problemStats[problemId]) {
                    teamItem.problemStats[problemId] = {};
                }
                teamItem.problemStats[problemId].status = 'ac';
                teamItem.problemStats[problemId].submitCount = submitCount;
                teamItem.problemStats[problemId].lastSubmitTime = this.formatDuration(deltaSeconds * 1000);
            } else {
                if (!teamItem.problemStats[problemId]) {
                    teamItem.problemStats[problemId] = {};
                }
                teamItem.problemStats[problemId].status = 'wa';
                teamItem.problemStats[problemId].submitCount = submitCount;
                if (submitCount > 0) {
                    const lastTime = new Date(problemSolutions[submitCount - 1].in_date).getTime();
                    const startTime = new Date(this.data.contest.start_time).getTime();
                    const deltaSeconds = Math.floor((lastTime - startTime) / 1000);
                    teamItem.problemStats[problemId].lastSubmitTime = this.formatDuration(deltaSeconds * 1000);
                }
            }
            
            // 移除frozen标记
            delete solutions.frozen[problemId];
            
            // 重新入队
            pq.push(teamItem);
            
            // 让出控制权，避免阻塞UI
            if (completedCount % 100 === 0) {
                await new Promise(resolve => setTimeout(resolve, 0));
            }
        }
        
        // 收集所有队伍（队列中的 + 已完成的）
        const finalTeams = [];
        
        // 先将队列中的所有元素取出
        while (!pq.empty()) {
            finalTeams.push(pq.pop());
        }
        
        // 然后加入已完成的队伍
        finalTeams.push(...completedTeams);
        
        // 关键修复：验证 finalTeams 的数量必须等于原始队伍数
        // 如果数量不匹配，说明模拟过程有问题，应该报错而不是继续
        if (finalTeams.length !== rollDataBackup.length) {
            console.error(`JumpToSpecificStage: 队伍数量不匹配！原始: ${rollDataBackup.length}, 最终: ${finalTeams.length}`);
            // 不更新 rollData，保持原样，并返回已处理的队伍ID
            return Array.from(processedTeamIds);
        }
        
        // 按照优先级队列的比较规则排序（注意：这里需要反转比较逻辑，因为我们希望最终排序是按照排名顺序）
        finalTeams.sort((a, b) => {
            // 排名规则：solved 大的在前，相同则 penalty 小的在前，相同则 team_id 小的在前
            if (a.solved !== b.solved) {
                return b.solved - a.solved;
            }
            if (a.penalty !== b.penalty) {
                return a.penalty - b.penalty;
            }
            return a.team_id.localeCompare(b.team_id);
        });
        
        // 更新 rollData 和 rollDataMap
        const teamIdMap = new Map();
        rollDataBackup.forEach(team => {
            teamIdMap.set(team.team_id, team);
        });
        
        // 关键修复：确保每个队伍都能找到对应的原始数据，并验证数量
        const updatedRollData = [];
        const processedTeamIdSet = new Set(); // 用于检测重复的 team_id
        
        for (const teamItem of finalTeams) {
            // 关键修复：检测重复的 team_id，避免同一个队伍被添加多次
            if (processedTeamIdSet.has(teamItem.team_id)) {
                console.error(`JumpToSpecificStage: 发现重复的队伍ID: ${teamItem.team_id}`);
                continue; // 跳过重复的队伍
            }
            processedTeamIdSet.add(teamItem.team_id);
            
            const originalTeam = teamIdMap.get(teamItem.team_id);
            if (!originalTeam) {
                console.error(`JumpToSpecificStage: 找不到队伍 ${teamItem.team_id} 的原始数据`);
                // 关键修复：如果找不到原始数据，跳过这个队伍，避免产生 null 或 undefined
                continue;
            }
            
            updatedRollData.push({
                ...originalTeam,
                solved: teamItem.solved,
                penalty: teamItem.penalty,
                rank: updatedRollData.length + 1, // 使用当前数组长度 + 1 作为排名
                problemStats: teamItem.problemStats
            });
        }
        
        // 关键修复：验证更新后的数据数量必须等于原始数量
        if (updatedRollData.length !== rollDataBackup.length) {
            console.error(`JumpToSpecificStage: 更新后队伍数量不匹配！原始: ${rollDataBackup.length}, 更新后: ${updatedRollData.length}`);
            // 不更新 rollData，保持原样，并返回已处理的队伍ID
            return Array.from(processedTeamIds);
        }
        
        this.rollData = updatedRollData;
        
        // 更新 rollDataMap
        this.rollDataMap = new Map();
        this.rollData.forEach(item => {
            this.rollDataMap.set(item.team_id, item);
        });
        
        // 更新 rollSolutionMap 的 frozen 状态
        for (const [teamId, solutions] of Object.entries(simulatedSolutionMap)) {
            if (this.rollSolutionMap[teamId]) {
                this.rollSolutionMap[teamId].frozen = { ...solutions.frozen };
            }
        }
        
        // currentJudgingIndex 应该指向剩余未揭晓队伍的最后一个
        // remainingTeams 是剩余队伍数，索引应该是 remainingTeams - 1
        // 关键修复：确保 remainingTeams 在有效范围内，避免计算错误
        const validRemainingTeams = Math.max(0, Math.min(remainingTeams, this.rollData.length));
        if (validRemainingTeams > 0) {
            this.currentJudgingIndex = validRemainingTeams - 1;
        } else {
            // 如果没有剩余队伍或计算错误，从最后一个队伍开始（初始状态）
            this.currentJudgingIndex = this.rollData.length > 0 ? this.rollData.length - 1 : -1;
        }
        // 双重保险：确保索引在有效范围内
        if (this.currentJudgingIndex < 0 && this.rollData.length > 0) {
            this.currentJudgingIndex = this.rollData.length - 1;
        }
        if (this.currentJudgingIndex >= this.rollData.length && this.rollData.length > 0) {
            this.currentJudgingIndex = this.rollData.length - 1;
        }
        this.judgingTeamId = null;
        this.judgingProblemId = null;
        this.judgingTeamIdLast = null;
        this.animatingRisingTeamId = null;
        
        // 再次确保状态变量被清空（模拟过程不应该修改这些状态，但为了保险起见再次清空）
        this.awardShownTeams.clear();
        this.settledTeams.clear();
        this.isInAwardArea = false;
        this.currentAwardLevel = 0;
        this.startAwardLevel = 0;
        this.currentRollStep = null;
        
        // 关键修复：如果 skipAnimation 为 true，不在这里调用 RenderRank
        // 因为调用方（FastSkipToAwardArea/FinalizeJumpToStage）会在自己的逻辑中调用 RenderRank
        // 如果在这里调用，会导致 RenderRank 被调用两次，可能产生重复DOM
        
        // 返回被处理的队伍ID列表（所有出队的都算），用于应用特效
        return Array.from(processedTeamIds);
    }
    
    /**
     * 获取当前位置所属的奖区级别
     * @returns {number} 0=无奖区, 1=铜奖, 2=银奖, 3=金奖
     */
    GetCurrentAwardLevel() {
        const displayList = this.FilterByStarMode(this.rollData, this.starMode);
        const rankedList = this.CalculateRankInfo(displayList);
        
        // 获取当前队伍的排名
        let checkingTeamId = this.judgingTeamId;
        if (!checkingTeamId && this.currentJudgingIndex >= 0 && this.currentJudgingIndex < displayList.length) {
            checkingTeamId = displayList[this.currentJudgingIndex].team_id;
        }
        
        if (!checkingTeamId) {
            return 0;
        }
        
        const currentItem = rankedList.find(item => item.team_id === checkingTeamId);
        if (!currentItem || currentItem.isStar || currentItem.displayRank === '*') {
            return 0;
        }
        
        const rank = parseInt(currentItem.displayRank);
        if (isNaN(rank) || rank <= 0) {
            return 0;
        }
        
        if (rank <= this.rankGold) {
            return 3; // 金奖
        } else if (rank <= this.rankSilver) {
            return 2; // 银奖
        } else if (rank <= this.rankBronze) {
            return 1; // 铜奖
        }
        
        return 0; // 无奖区
    }
    
    /**
     * 找到指定奖区的最后一个队伍
     * @param {number} targetAwardLevel - 目标奖区级别: 1=铜奖, 2=银奖, 3=金奖
     * @param {Array} rankedList - 排名列表
     * @returns {Object|null} 最后一个队伍，如果没有则返回null
     */
    FindLastTeamInAwardLevel(targetAwardLevel, rankedList) {
        let maxRank = 0;
        if (targetAwardLevel === 3) {
            maxRank = this.realRankGold;
        } else if (targetAwardLevel === 2) {
            maxRank = this.realRankSilver;
        } else if (targetAwardLevel === 1) {
            maxRank = this.realRankBronze;
        } else {
            return null;
        }
        // 从后往前找最后一个符合条件的队伍
        for (let i = rankedList.length - 1; i >= 0; i--) {
            const item = rankedList[i];
            if (item.displayRank !== '*' && !item.isStar) {
                const rank = parseInt(item.displayRank);
                if (!isNaN(rank) && rank > 0) {
                    // 计算该队伍所属的奖区级别
                    let itemLevel = 0;
                    if (rank <= this.realRankGold) {
                        itemLevel = 3;
                    } else if (rank <= this.realRankSilver) {
                        itemLevel = 2;
                    } else if (rank <= this.realRankBronze) {
                        itemLevel = 1;
                    }
                    // 如果该队伍属于目标奖区，返回它
                    if (itemLevel === targetAwardLevel) {
                        return item;
                    }
                    
                    // 如果遇到更高级别的奖区，说明目标奖区已经找不到，返回null
                    if (itemLevel > targetAwardLevel) {
                        return null;
                    }
                }
            }
        }
        
        return null;
    }
    
    /**
     * 快速跳过到获奖区域（增强版：支持在奖区间跳跃）
     * - 如果当前在无奖区，跳到奖区（有视口上升动画）
     * - 如果当前在铜奖区，跳到银奖区；如果没有银奖区，跳到金奖区
     * - 如果当前在银奖区，跳到金奖区
     * - 奖区间跳跃无额外动画，像 g 操作一样直接跳转
     */
    async FastSkipToAwardArea() {
        // 关键修复：在 f 行为开始时，将 currentRollStep 设为 null
        // 这样当用户按 N 时，会执行一次 JudgeConfirm，就正常了
        this.currentRollStep = null;
                
        if (!this.rankList || this.rankList.length === 0) {
            this.ShowKeyHint('无法计算获奖区域', 'F');
            return;
        }
        
        // 获取当前位置所属的奖区级别
        const currentAwardLevel = this.GetCurrentAwardLevel();
        
        // 计算目标奖区级别
        let targetAwardLevel = 0;
        if (currentAwardLevel === 0) {
            // 当前在无奖区，跳到奖区（铜奖）
            targetAwardLevel = 1;
        } else if (currentAwardLevel === 1) {
            // 当前在铜奖区，跳到银奖区；如果没有银奖区，跳到金奖区
            const silverTeam = this.FindLastTeamInAwardLevel(2, this.rankList);
            if (silverTeam) {
                targetAwardLevel = 2;
            } else {
                targetAwardLevel = 3;
            }
        } else if (currentAwardLevel === 2) {
            // 当前在银奖区，跳到金奖区
            targetAwardLevel = 3;
        } else if (currentAwardLevel === 3) {
            // 已经在金奖区，提示用户
            this.ShowKeyHint('当前位置已在最高奖区', 'F');
            return;
        }
        
        // 找到目标奖区的最后一个队伍
        let lastAwardTeam = this.FindLastTeamInAwardLevel(targetAwardLevel, this.rankList);
        // 如果没有找到目标奖区，尝试找下一个存在的奖区
        if (!lastAwardTeam && targetAwardLevel === 2) {
            // 如果没有银奖，尝试找金奖
            targetAwardLevel = 3;
            lastAwardTeam = this.FindLastTeamInAwardLevel(3, this.rankList);
        }
        
        if (!lastAwardTeam) {
            // 如果没有找到任何奖区，说明当前已经在最高奖区或没有奖区
            if (currentAwardLevel === 0) {
                this.ShowKeyHint('没有找到获奖队伍', 'F');
            } else {
                this.ShowKeyHint('当前位置已在最高奖区', 'F');
            }
            return;
        }
        
        // 获取当前的 displayList（用于查找索引）
        const displayListBeforeJump = this.FilterByStarMode(this.rollData, this.starMode);
        
        // 找到 lastAwardTeam 在 displayList 中的实际索引
        let lastAwardTeamIndexInDisplayList = -1;
        for (let i = 0; i < displayListBeforeJump.length; i++) {
            if (displayListBeforeJump[i].team_id === lastAwardTeam.team_id) {
                lastAwardTeamIndexInDisplayList = i;
                break;
            }
        }
        
        if (lastAwardTeamIndexInDisplayList < 0) {
            this.ShowKeyHint('无法找到获奖队伍在列表中的位置', 'F');
            return;
        }
        // 使用最后一个获奖队伍的 displayIdx 作为剩余队伍数
        // displayIdx 表示该队伍在榜单中的顺序位置（包括打星队）
        const remainingTeams = lastAwardTeam.displayIdx;
        
        const displayList = displayListBeforeJump;
        const totalTeams = displayList.length;
        
        if (remainingTeams >= totalTeams) {
            this.ShowKeyHint('获奖区域过大，无需跳过', 'F');
            return;
        }
        
        this.ShowFastSkipProgress();
        try {
            // 计算总步数用于进度显示
            const targetCompletedTeams = totalTeams - remainingTeams;
            this.UpdateFastSkipProgress(0, targetCompletedTeams);
            
            const revealedTeamIds = await this.JumpToSpecificStage(remainingTeams, (current, total) => {
                this.UpdateFastSkipProgress(current, total);
            });
            
            this.HideFastSkipProgress();
            
            const viewportHeight = window.innerHeight;
            
            // 步骤1：按下 f 后，先进行数据计算和更新
            // 重要：必须先排序数据，确保队伍顺序正确（但不执行排序动画）
            // 1. 更新数据排序
            this.RollSort();
                        
            // 2. 修正 currentJudgingIndex：应该指向奖区最后一名（lastAwardTeam）
            // lastAwardTeam 是解榜后的奖区最后一名，但并不表示滚榜第一次到这个位置的队伍，所以不能直接找这个队，而应该找这个位置
            let correctJudgingIndex = -1;
            for (let i = 0; i < this.rollData.length; i++) {
                if (this.rollData[i].displayIdx === lastAwardTeam.displayIdx) {
                    correctJudgingIndex = i;
                    break;
                }
            }
            
            // 如果找到了奖区最后一名，设置为 currentJudgingIndex；否则使用 JumpToSpecificStage 设置的值
            if (correctJudgingIndex >= 0) {
                this.currentJudgingIndex = correctJudgingIndex;
                // 关键修复：立即设置 judgingTeamId 和 judgingTeamIdLast，确保即使动画被打断，状态也是正确的
                // 设置 judgingTeamIdLast 非常重要，因为 FindNextJudging 需要用它来判断是否需要显示获奖
                const targetTeamData = this.rollData[correctJudgingIndex];
                if (targetTeamData) {
                    this.judgingTeamId = targetTeamData.team_id;
                    this.judgingTeamIdLast = targetTeamData.team_id; // 关键修复：设置 judgingTeamIdLast，避免 FindNextJudging 跳过获奖检查
                }
            } else {
                // 如果找不到，说明数据有问题，保持 JumpToSpecificStage 设置的值
                // 但仍然需要设置 judgingTeamId 和 judgingTeamIdLast
                if (this.currentJudgingIndex >= 0 && this.currentJudgingIndex < this.rollData.length) {
                    const targetTeamData = this.rollData[this.currentJudgingIndex];
                    if (targetTeamData) {
                        this.judgingTeamId = targetTeamData.team_id;
                        this.judgingTeamIdLast = targetTeamData.team_id; // 关键修复：设置 judgingTeamIdLast
                    }
                }
            }
            
            // 3. 更新DOM显示（但不执行IncrementalUpdate动画，避免与f键动画冲突）
            // 使用RenderRank直接渲染，确保DOM顺序正确，但不带动画
            await this.RenderRank(this.rollData);
            
            // 判断是否需要视口上升动画：只有从非奖区跳到奖区时才执行动画
            const shouldAnimateScroll = (currentAwardLevel === 0);
            
            // 如果不需要动画，像 g 操作一样直接跳转
            if (!shouldAnimateScroll) {
                // 直接执行跳转，无额外动画
                // 关键修复：RenderRank 已经更新了DOM，不需要再调用 IncrementalUpdate
                // IncrementalUpdate 会追加新元素，导致重复DOM
                // 如果需要动画，可以调用 IncrementalUpdate，但这里已经 RenderRank 了，不需要
                
                // 应用特效（如果有）
                if (revealedTeamIds && revealedTeamIds.length > 0) {
                    this.ApplySkipRevealEffect(revealedTeamIds);
                }
                // 执行一次JudgeConfirm（立即执行，不等待动画）
                this.JudgeConfirm();
                
                // 获取奖区名称用于提示
                let awardName = '';
                if (targetAwardLevel === 3) {
                    awardName = this.CreateBilingualText('金奖区', 'Gold Award Area');
                } else if (targetAwardLevel === 2) {
                    awardName = this.CreateBilingualText('银奖区', 'Silver Award Area');
                } else if (targetAwardLevel === 1) {
                    awardName = this.CreateBilingualText('铜奖区', 'Bronze Award Area');
                }
                
                this.ShowKeyHint(`已跳到${awardName}`, 'F');
                return;
            }
            
            // 以下是从非奖区跳到奖区的逻辑，保持原有的视口上升动画
            // 构建元素ID数组（用于后续计算）
            const allElementIds = this.rollData.map(item => `rank-grid-${item.team_id}`);
            
            // 4. 等待DOM更新完成，确保所有元素都已正确排序和渲染
            // 需要等待足够的时间，让浏览器完成布局计算
            await new Promise(resolve => requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    requestAnimationFrame(resolve);
                });
            }));
            
            // 强制重排，确保浏览器完成布局计算
            const tempElement = document.querySelector('.rank-grid');
            if (tempElement) {
                tempElement.offsetHeight; // 强制重排
            }
            
            // 5. DOM更新完成后，直接向上滚动到跳过后第一个未处理队伍的上方位置
            // 找到 currentJudgingIndex 对应的队伍（应该是奖区最后一名）
            let targetTeamId = null;
            if (this.currentJudgingIndex >= 0 && this.currentJudgingIndex < this.rollData.length) {
                // 使用 this.rollData 而不是 rankedList，因为 currentJudgingIndex 是基于 displayList 的
                targetTeamId = this.rollData[this.currentJudgingIndex].team_id;
            } else if (this.rollData.length > 0) {
                // 如果索引超出范围，使用最后一个队伍
                targetTeamId = this.rollData[this.rollData.length - 1].team_id;
            }
            
            // 从非奖区跳到奖区，需要执行视口上升动画
            if (targetTeamId) {
                const targetElementId = `rank-grid-${targetTeamId}`;
                const targetEl = document.getElementById(targetElementId);
                if (targetEl) {
                    // 获取滚动容器
                    const scrollContainer = this.getScrollContainer();
                    const isWindowScroll = scrollContainer === window;
                    
                    // 获取当前滚动位置
                    const scrollUpStartY = isWindowScroll
                        ? (window.scrollY || window.pageYOffset || document.documentElement.scrollTop)
                        : scrollContainer.scrollTop;
                    
                    // 等待一帧，确保DOM完全稳定
                    await new Promise(resolve => requestAnimationFrame(resolve));
                    
                    // 重新获取元素位置（DOM可能已经更新）
                    const refreshedTargetEl = document.getElementById(targetElementId);
                    if (!refreshedTargetEl) {
                        console.warn('目标元素未找到:', targetElementId);
                        return;
                    }
                    
                    // 计算目标位置（跳过后第一个未处理的队伍应该在视口下方1/3处）
                    let elementOffsetTop = refreshedTargetEl.offsetTop;
                    let offsetParent = refreshedTargetEl.offsetParent;
                    while (offsetParent && offsetParent !== document.body && offsetParent !== document.documentElement) {
                        elementOffsetTop += offsetParent.offsetTop;
                        offsetParent = offsetParent.offsetParent;
                    }
                    
                    // 计算目标滚动位置
                    let finalTargetScrollY;
                    if (isWindowScroll) {
                        // window 滚动：目标：元素顶部距离视口顶部 = 视口高度的 1/3
                        finalTargetScrollY = elementOffsetTop - (viewportHeight / 3);
                    } else {
                        // 容器滚动：需要计算元素相对于容器的位置
                        const containerRect = scrollContainer.getBoundingClientRect();
                        const containerScrollTop = scrollContainer.scrollTop;
                        const elementTopRelative = refreshedTargetEl.getBoundingClientRect().top - containerRect.top + containerScrollTop;
                        finalTargetScrollY = elementTopRelative - (viewportHeight / 3);
                    }
                    
                    // 优化滚动性能：为所有元素启用硬件加速，避免滚动时的动态模糊
                    // 给所有队伍元素添加硬件加速样式
                    allElementIds.forEach(elementId => {
                        const element = document.getElementById(elementId);
                        if (element) {
                            // 启用硬件加速，避免滚动时的动态模糊
                            element.style.willChange = 'transform';
                            element.style.transform = 'translateZ(0)'; // 启用硬件加速
                            element.style.backfaceVisibility = 'hidden'; // 避免渲染问题
                        }
                    });
                    
                    // 等待一帧，确保样式已应用
                    await new Promise(resolve => requestAnimationFrame(resolve));
                    
                    // 检查滚动距离是否足够（至少10px才滚动）
                    const scrollDistance = finalTargetScrollY - scrollUpStartY;
                    
                    if (Math.abs(scrollDistance) > 10) {
                        // 使用优化的滚动函数，平滑滚动到目标位置
                        // 滚动速度：150像素/秒，优雅缓慢
                        const scrollSpeed = 150;
                        const scrollDistanceAbs = Math.abs(scrollDistance);
                        const scrollDuration = (scrollDistanceAbs / scrollSpeed) * 1000;
                        const minScrollDuration = 500;
                        const maxScrollDuration = 30000;
                        const finalScrollDuration = Math.max(minScrollDuration, Math.min(maxScrollDuration, scrollDuration));
                        
                        // 中断之前的滚动动画（如果有）
                        if (this.scrollAnimationRunning && this.scrollAnimationId !== null) {
                            cancelAnimationFrame(this.scrollAnimationId);
                            this.scrollAnimationRunning = false;
                        }
                        
                        // 使用 requestAnimationFrame 实现平滑滚动，确保流畅不模糊
                        const scrollUpStartTime = performance.now();
                        this.scrollAnimationRunning = true;
                        let lastScrollY = scrollUpStartY;
                        
                        const animateScrollUp = (currentTime) => {
                            // 检查是否被中断（scrollToTeam 会设置 scrollAnimationRunning = false）
                            if (!this.scrollAnimationRunning) {
                                // 动画被中断，清理硬件加速样式
                                allElementIds.forEach(elementId => {
                                    const element = document.getElementById(elementId);
                                    if (element) {
                                        element.style.willChange = '';
                                        element.style.transform = '';
                                        element.style.backfaceVisibility = '';
                                    }
                                });
                                // 清理相关定时器，避免延迟触发
                                if (this.fastSkipScrollTimeout !== null) {
                                    clearTimeout(this.fastSkipScrollTimeout);
                                    this.fastSkipScrollTimeout = null;
                                }
                                if (this.fastSkipJudgeConfirmTimeout !== null) {
                                    clearTimeout(this.fastSkipJudgeConfirmTimeout);
                                    this.fastSkipJudgeConfirmTimeout = null;
                                }
                                return;
                            }
                            
                            const elapsed = currentTime - scrollUpStartTime;
                            const progress = Math.min(elapsed / finalScrollDuration, 1);
                            
                            // 使用线性插值，实现匀速滚动
                            const scrollY = scrollUpStartY + (finalTargetScrollY - scrollUpStartY) * progress;
                            const targetScrollY = Math.max(0, scrollY);
                            
                            // 检查是否需要滚动（至少0.5px变化才滚动）
                            if (Math.abs(targetScrollY - lastScrollY) > 0.5) {
                                // 使用正确的滚动方法
                                if (isWindowScroll) {
                                    // window 滚动：直接使用 scrollTo
                                    window.scrollTo({
                                        top: targetScrollY,
                                        left: 0,
                                        behavior: 'auto'
                                    });
                                } else {
                                    // 容器滚动：直接设置 scrollTop
                                    scrollContainer.scrollTop = targetScrollY;
                                }
                                lastScrollY = targetScrollY;
                            }
                            
                            if (progress < 1) {
                                this.scrollAnimationId = requestAnimationFrame(animateScrollUp);
                            } else {
                                this.scrollAnimationRunning = false;
                                this.scrollAnimationId = null;
                                // 确保最终位置正确
                                if (isWindowScroll) {
                                    window.scrollTo({
                                        top: finalTargetScrollY,
                                        left: 0,
                                        behavior: 'auto'
                                    });
                                } else {
                                    scrollContainer.scrollTop = finalTargetScrollY;
                                }
                                
                                // 滚动完成后，清理硬件加速样式
                                allElementIds.forEach(elementId => {
                                    const element = document.getElementById(elementId);
                                    if (element) {
                                        element.style.willChange = '';
                                        element.style.transform = '';
                                        element.style.backfaceVisibility = '';
                                    }
                                });
                            }
                        };
                        
                        requestAnimationFrame(animateScrollUp);
                        
                        // 等待滚动动画完成（使用可取消的 Promise）
                        await new Promise(resolve => {
                            // 保存定时器ID，以便中断时可以清理
                            this.fastSkipScrollTimeout = setTimeout(() => {
                                this.fastSkipScrollTimeout = null;
                                resolve();
                            }, finalScrollDuration + 100);
                        });
                        
                        // 验证滚动是否成功
                        const afterScrollY = isWindowScroll
                            ? (window.scrollY || window.pageYOffset || document.documentElement.scrollTop)
                            : scrollContainer.scrollTop;
                        
                        // 如果滚动失败，尝试直接定位
                        if (Math.abs(afterScrollY - finalTargetScrollY) > 10) {
                            console.warn('滚动动画未达到目标位置，尝试直接定位');
                            if (isWindowScroll) {
                                window.scrollTo({
                                    top: finalTargetScrollY,
                                    left: 0,
                                    behavior: 'auto'
                                });
                            } else {
                                scrollContainer.scrollTop = finalTargetScrollY;
                            }
                        }
                    } else {
                        
                    }
                }
            } else {
                // 如果没有非奖区已揭晓队伍，执行排序动画并应用特效
                await this.IncrementalUpdate(this.rollData);
                if (revealedTeamIds && revealedTeamIds.length > 0) {
                    this.ApplySkipRevealEffect(revealedTeamIds);
                }
            }
            
            // 等待动画完全完成后再执行JudgeConfirm
            // 延迟一点时间，确保所有滚动动画也完成（使用可取消的定时器）
            this.fastSkipJudgeConfirmTimeout = setTimeout(() => {
                this.fastSkipJudgeConfirmTimeout = null;
                // 再次检查滚动是否被中断（如果被中断，不应该执行JudgeConfirm）
                if (this.scrollAnimationRunning) {
                    // 滚动还在进行，不执行JudgeConfirm（等待滚动完成）
                    return;
                }
                // 执行一次JudgeConfirm
                this.JudgeConfirm();
                
                this.ShowKeyHint('已快速跳到获奖区域', 'F');
            }, 500); // 额外等待500ms，确保滚动和动画完全完成
        } catch (error) {
            console.error('快速跳过失败:', error);
            this.HideFastSkipProgress();
            this.ShowMessage('快速跳过失败，请重试');
        }
    }
    
    /**
     * 往前跳 10 个队（快捷键 g）
     * 基于 currentJudgingIndex 前进10个位置
     */
    async JumpForward10Teams() {
        if (!this.isRolling) {
            this.ShowKeyHint('请先启动滚榜', 'G');
            return;
        }
        
        const displayList = this.FilterByStarMode(this.rollData, this.starMode);
        const totalTeams = displayList.length;
        
        // 初始化 currentJudgingIndex 如果无效
        if (this.currentJudgingIndex < 0 || this.currentJudgingIndex >= totalTeams) {
            this.currentJudgingIndex = totalTeams - 1;
        }
        
        // 往前跳 10 个队，即 currentJudgingIndex 减少 10
        const targetIndex = Math.max(0, this.currentJudgingIndex - 10);
        
        if (targetIndex === this.currentJudgingIndex) {
            this.ShowKeyHint('无法再往前跳', 'G');
            return;
        }
        
        // 计算需要"完成"到目标索引（即剩余队伍数）
        const remainingTeams = targetIndex + 1; // currentJudgingIndex 是索引，剩余队伍数 = 索引 + 1
        
        this.ShowFastSkipProgress();
        
        try {
            // 计算总步数用于进度显示（需要完成的队伍数）
            const currentRemainingTeams = this.currentJudgingIndex + 1;
            const targetCompletedTeams = currentRemainingTeams - remainingTeams;
            this.UpdateFastSkipProgress(0, targetCompletedTeams);
            
            const revealedTeamIds = await this.JumpToSpecificStage(remainingTeams, (current, total) => {
                this.UpdateFastSkipProgress(current, total);
            });
            
            this.HideFastSkipProgress();
            
            // 关键修复：使用 FinalizeJumpToStage 统一处理，确保数据一致性
            // FinalizeJumpToStage 会调用 RenderRank 并处理所有后续逻辑
            await this.FinalizeJumpToStage(remainingTeams, {
                skipAnimation: true,
                callJudgeConfirm: true
            });
            
            // 应用特效（如果有）
            if (revealedTeamIds && revealedTeamIds.length > 0) {
                this.ApplySkipRevealEffect(revealedTeamIds);
            }
            
            this.ShowKeyHint(`已往前跳 10 个队（当前位置: ${this.currentJudgingIndex + 1}/${totalTeams}）`, 'G');
        } catch (error) {
            console.error('往前跳失败:', error);
            this.HideFastSkipProgress();
            this.ShowMessage('往前跳失败，请重试');
        }
    }
    
    
    /**
     * 显示快速跳过进度条
     */
    ShowFastSkipProgress() {
        this.HideFastSkipProgress();
        
        const overlay = document.createElement('div');
        overlay.id = 'fast-skip-progress-overlay';
        overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 100000;
            backdrop-filter: blur(4px);
        `;
        
        const progressContainer = document.createElement('div');
        progressContainer.style.cssText = `
            background: white;
            padding: 32px 48px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            min-width: 400px;
            max-width: 600px;
        `;
        
        progressContainer.innerHTML = `
            <div style="margin-bottom: 20px; font-size: 18px; font-weight: 600; color: #333;">
                ${this.CreateBilingualText('快速跳过到获奖区域...', 'Fast Skip to Award Area...')}
            </div>
            <div style="width: 100%; height: 24px; background: #e9ecef; border-radius: 6px; overflow: hidden; margin-bottom: 12px;">
                <div id="fast-skip-progress-bar" style="width: 0%; height: 100%; background: linear-gradient(90deg, #3b82f6, #2563eb); transition: width 0.3s ease; border-radius: 6px;"></div>
            </div>
            <div id="fast-skip-progress-text" style="font-size: 14px; color: #666; text-align: center;">
                ${this.CreateBilingualText('计算中...', 'Calculating...')}
            </div>
        `;
        
        overlay.appendChild(progressContainer);
        document.body.appendChild(overlay);
    }
    
    /**
     * 更新快速跳过进度条
     */
    UpdateFastSkipProgress(current, total) {
        const progressBar = document.getElementById('fast-skip-progress-bar');
        const progressText = document.getElementById('fast-skip-progress-text');
        
        if (progressBar && progressText) {
            const percentage = total > 0 ? Math.min(100, Math.round((current / total) * 100)) : 0;
            progressBar.style.width = `${percentage}%`;
            progressText.textContent = `${this.CreateBilingualText('处理中', 'Processing')}: ${current} / ${total} (${percentage}%)`;
        }
    }
    
    /**
     * 隐藏快速跳过进度条
     */
    HideFastSkipProgress() {
        const overlay = document.getElementById('fast-skip-progress-overlay');
        if (overlay) {
            overlay.remove();
        }
    }
    
    /**
     * 执行跳转到指定阶段后的通用后处理逻辑
     * 参考 FastSkipToAwardArea 的正确实现，确保状态一致性
     * @param {number} remainingTeams - 剩余队伍数（已由JumpToSpecificStage处理）
     * @param {Object} options - 选项
     * @param {boolean} options.skipAnimation - 是否跳过动画（默认true）
     * @param {boolean} options.callJudgeConfirm - 是否调用JudgeConfirm定位（默认true）
     */
    async FinalizeJumpToStage(remainingTeams, options = {}) {
        const { skipAnimation = true, callJudgeConfirm = true } = options;
        
        // 1. 数据排序（确保顺序正确，参考 FastSkipToAwardArea）
        this.RollSort();
                
        // 3. 重新计算并设置 currentJudgingIndex（基于 remainingTeams）
        // remainingTeams 表示剩余未处理的队伍数，所以 currentJudgingIndex = remainingTeams - 1
        // 参考 JumpToSpecificStage 和 FastSkipToAwardArea 的逻辑
        // 关键修复：确保 remainingTeams 在有效范围内，避免计算错误
        const validRemainingTeams = Math.max(0, Math.min(remainingTeams, this.rollData.length));
        if (validRemainingTeams > 0) {
            this.currentJudgingIndex = validRemainingTeams - 1;
        } else {
            // 如果没有剩余队伍或计算错误，从最后一个队伍开始（初始状态）
            this.currentJudgingIndex = this.rollData.length > 0 ? this.rollData.length - 1 : -1;
        }
        
        // 确保索引在有效范围内（双重保险）
        if (this.currentJudgingIndex < 0 && this.rollData.length > 0) {
            this.currentJudgingIndex = this.rollData.length - 1;
        }
        if (this.currentJudgingIndex >= this.rollData.length && this.rollData.length > 0) {
            this.currentJudgingIndex = this.rollData.length - 1;
        }
        
        // 4. 设置 judgingTeamId 和 judgingTeamIdLast（确保状态一致性，参考 FastSkipToAwardArea）
        // 关键修复：立即设置 judgingTeamId 和 judgingTeamIdLast，确保即使后续操作被打断，状态也是正确的
        // 设置 judgingTeamIdLast 非常重要，因为 FindNextJudging 需要用它来判断是否需要显示获奖
        if (this.currentJudgingIndex >= 0 && this.currentJudgingIndex < this.rollData.length) {
            const targetTeamData = this.rollData[this.currentJudgingIndex];
            if (targetTeamData) {
                this.judgingTeamId = targetTeamData.team_id;
                this.judgingTeamIdLast = targetTeamData.team_id; // 关键修复：设置 judgingTeamIdLast，避免 FindNextJudging 跳过获奖检查
            }
        }
        
        // 5. 更新DOM显示（无动画）
        // 参考 FastSkipToAwardArea：使用 RenderRank 直接渲染，确保DOM顺序正确
        await this.RenderRank(this.rollData);
        
        // 6. 等待DOM更新完成（参考 FastSkipToAwardArea）
        // 需要等待足够的时间，让浏览器完成布局计算
        await new Promise(resolve => requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                requestAnimationFrame(resolve);
            });
        }));
        
        // 7. 定位到下一个要判的队伍（如果需要）
        if (callJudgeConfirm) {
            this.JudgeConfirm();
        }
        
    }
    
    async RollUndo() {
        if (!this.isRolling) {
            this.ShowKeyHint('请先启动滚榜', 'U');
            return;
        }
        
        // 1. 保存当前的 currentJudgingIndex（重置前的位置）
        const currentIndex = this.currentJudgingIndex;
        
        // 获取当前数据用于验证
        const totalTeams = this.rollData.length;
        
        // 验证当前索引有效性
        if (currentIndex < 0 || currentIndex >= totalTeams) {
            this.ShowKeyHint('无法再撤回', 'U');
            return;
        }
        
        // 2. 计算目标位置：撤回1步，即 currentJudgingIndex + 1
        const targetIndex = Math.min(totalTeams - 1, currentIndex + 1);
        
        if (targetIndex <= currentIndex) {
            this.ShowKeyHint('无法再撤回', 'U');
            return;
        }
        
        // 3. 计算剩余队伍数（用于 JumpToSpecificStage）
        // targetIndex 表示目标位置，remainingTeams = targetIndex + 1
        const remainingTeams = targetIndex + 1;
        
        try {
            // 4. 完全重置滚榜数据（复用 ResetRoll 的初始化逻辑）
            await this.ResetRollDataToInitial();
            
            // 5. 验证重置后的数据
            const displayListAfterReset = this.FilterByStarMode(this.rollData, this.starMode);
            if (displayListAfterReset.length !== totalTeams) {
                console.error('RollUndo: 重置前后队伍数不一致', totalTeams, displayListAfterReset.length);
                this.ShowMessage('撤回失败：数据不一致');
                return;
            }
            
            // 6. 使用 JumpToSpecificStage 跳转到目标位置（无动画）
            await this.JumpToSpecificStage(remainingTeams, null, true);
            
            // 7. 执行通用后处理逻辑
            await this.FinalizeJumpToStage(remainingTeams, {
                skipAnimation: true,
                callJudgeConfirm: true
            });
            
            this.ShowKeyHint(`已撤回一步（当前位置: ${this.currentJudgingIndex + 1}/${this.rollData.length}）`, 'U');
        } catch (error) {
            console.error('撤回失败:', error);
            this.ShowMessage('撤回失败，请重试');
        }
    }
    
    /**
     * 完全重置滚榜数据（复用 ResetRoll 的核心逻辑）
     * 与 ResetRoll 的区别：不会停止滚榜、不会重新获取服务器数据
     * 用于 u 和 i 操作，确保数据完全一致
     */
    async ResetRollDataToInitial() {
        // 直接复用 ResetRoll 的核心重置逻辑（不重新获取数据）
        await this._ResetRollDataCore(false);
    }
    
    /**
     * 回退10个队伍（I键）：完全重置数据，然后暴力跳转到 currentJudgingIndex + 10 的位置，无动画
     */
    async JumpBack10Teams() {
        if (!this.isRolling) {
            this.ShowKeyHint('请先启动滚榜', 'I');
            return;
        }
        
        // 1. 保存当前的 currentJudgingIndex（重置前的位置）
        const currentIndex = this.currentJudgingIndex;
        
        // 获取当前数据用于验证
        const displayListBeforeReset = this.FilterByStarMode(this.rollData, this.starMode);
        const totalTeams = displayListBeforeReset.length;
        // 验证当前索引有效性
        if (currentIndex < 0 || currentIndex >= totalTeams) {
            this.ShowKeyHint('无法再往前跳', 'I');
            return;
        }
        
        // 2. 计算目标位置：往后跳10个队，即 currentJudgingIndex + 10
        const stepsToJump = 10;
        const targetIndex = Math.min(totalTeams - 1, currentIndex + stepsToJump);
        
        if (targetIndex <= currentIndex) {
            this.ShowKeyHint('无法再往前跳', 'I');
            return;
        }
        
        // 3. 计算剩余队伍数（用于 JumpToSpecificStage）
        // targetIndex 表示目标位置，remainingTeams = targetIndex + 1
        const remainingTeams = targetIndex + 1;
        
        try {
            // 4. 完全重置滚榜数据（复用 ResetRoll 的初始化逻辑）
            await this.ResetRollDataToInitial();
            
            // 5. 验证重置后的数据
            const displayListAfterReset = this.FilterByStarMode(this.rollData, this.starMode);
            if (displayListAfterReset.length !== totalTeams) {
                console.error('JumpBack10Teams: 重置前后队伍数不一致', totalTeams, displayListAfterReset.length);
                this.ShowMessage('回退失败：数据不一致');
                return;
            }
            // 6. 使用 JumpToSpecificStage 跳转到目标位置（无动画）
            await this.JumpToSpecificStage(remainingTeams, null, true);
            
            // 7. 执行通用后处理逻辑
            await this.FinalizeJumpToStage(remainingTeams, {
                skipAnimation: true,
                callJudgeConfirm: true
            });
            
            this.ShowKeyHint(`已回退10个队伍（当前位置: ${this.currentJudgingIndex + 1}/${this.rollData.length}）`, 'I');
        } catch (error) {
            console.error('回退失败:', error);
            this.ShowMessage('回退失败，请重试');
        }
    }
    
    /**
     * 优雅的队伍落下动画（使用 CSGAnim 库）
     * @param {string[]} teamIds - 要落下的队伍ID数组
     * @param {number} duration - 动画持续时间（ms），默认慢速优雅
     * @param {boolean} shouldScrollFollow - 是否让窗口跟随上升，默认true
     * @param {string} targetTeamId - 滚动目标队伍ID（用于窗口跟随）
     * @returns {Promise<void>}
     */
    async AnimateTeamsFallingDown(teamIds, duration = 1500, shouldScrollFollow = true, targetTeamId = null) {
        // 关键修复：此方法已废弃，不再需要跳过的队伍的优雅降落动画
        // 直接返回，不执行任何操作，避免产生额外的DOM操作
        return;
    }
    
    /**
     * 获取所有已揭晓的非奖区队伍ID
     * @param {Array} rankedList - 排名列表
     * @returns {string[]} - 队伍ID数组
     */
    GetNonAwardRevealedTeams(rankedList) {
        const nonAwardTeams = [];
        
        rankedList.forEach(item => {
            // 跳过打星队和奖区队伍
            if (item.isStar) {
                return;
            }
            
            if (item.displayRank !== '*' && item.displayRank <= this.rankBronze) {
                return; // 奖区队伍，跳过
            }
            
            // 检查该队伍是否已全部揭晓（没有frozen题目）)
            const solutions = this.rollSolutionMap[item.team_id];
            const hasFrozenProblems = solutions && solutions.frozen && Object.keys(solutions.frozen).length > 0;
            
            if (!hasFrozenProblems) {
                // 已全部揭晓的非奖区队伍
                nonAwardTeams.push(item.team_id);
            }
        });
        
        return nonAwardTeams;
    }
    
    /**
     * 获取已尘埃落定的队伍ID（currentJudgingIndex 之前的队伍，名次不会再变化）
     * @param {Array} rankedList - 排名列表
     * @param {number} currentJudgingIndex - 当前判题索引
     * @returns {string[]} - 队伍ID数组
     */
    GetSettledTeams(rankedList, currentJudgingIndex) {
        if (currentJudgingIndex <= 0 || currentJudgingIndex >= rankedList.length) {
            return [];
        }
        
        const settledTeams = [];
        
        // currentJudgingIndex 之前的队伍都已尘埃落定（名次不会再变化）
        // 注意：不包括 currentJudgingIndex 本身
        for (let i = 0; i < currentJudgingIndex; i++) {
            const item = rankedList[i];
            if (item) {
                settledTeams.push(item.team_id);
            }
        }
        
        return settledTeams;
    }
    
    /**
     * 为跳过过程中被处理的队伍应用特效
     * @param {string[]} teamIds - 被处理的队伍ID数组（所有出队的都算）
     */
    ApplySkipRevealEffect(teamIds) {
        if (!teamIds || teamIds.length === 0 || !this.container) {
            return;
        }
        
        // // 使用 requestAnimationFrame 确保不阻塞动画和DOM操作
        // // 批量添加特效类，异步执行，不阻塞主线程
        // requestAnimationFrame(() => {
        //     teamIds.forEach((teamId, index) => {
        //         // 使用 requestAnimationFrame 分批处理，避免阻塞
        //         requestAnimationFrame(() => {
        //             const teamRow = document.getElementById(`rank-grid-${teamId}`);
        //             if (teamRow) {
        //                 // 延迟添加，形成逐个闪现的效果
        //                 setTimeout(() => {
        //                     teamRow.classList.add('roll-skip-revealed');
        //                     // 2秒后移除特效类
        //                     setTimeout(() => {
        //                         teamRow.classList.remove('roll-skip-revealed');
        //                     }, 1000);
        //                 }, index * 30); // 每个队伍间隔30ms，形成流水效果
        //             }
        //         });
        //     });
        // });
    }
    
    /**
     * 速度控制
     */
    RollSpeedUp() {
        this.rollSpeedMultiplier = Math.min(5.0, this.rollSpeedMultiplier * 1.5);
        this.autoSpeed = Math.max(this.MIN_ROLL_SPEED, Math.floor(this.DEFAULT_ROLL_SPEED / this.rollSpeedMultiplier));
    }
    
    RollSpeedDown() {
        this.rollSpeedMultiplier = Math.max(0.2, this.rollSpeedMultiplier / 1.5);
        this.autoSpeed = Math.min(this.MAX_ROLL_SPEED, Math.floor(this.DEFAULT_ROLL_SPEED / this.rollSpeedMultiplier));
    }
    
    RollResetSpeed() {
        this.rollSpeedMultiplier = 1.0;
        this.autoSpeed = this.DEFAULT_ROLL_SPEED;
    }
    
    /**
     * 切换自动滚榜
     */
    ToggleAutoRoll() {
        this.isAutoRolling = !this.isAutoRolling;
        if (this.isAutoRolling) {
            if (!this.isRolling) {
                this.pendingAutoRoll = true;
                this.StartRollProcess().then(() => {
                    if (this.pendingAutoRoll && this.isRolling) {
                        this.pendingAutoRoll = false;
                        const displayList = this.FilterByStarMode(this.rollData, this.starMode);
                        const rankedList = this.CalculateRankInfo(displayList);
                        
                        if (this.judgingTeamId) {
                            const currentItem = rankedList.find(item => item.team_id === this.judgingTeamId);
                            if (currentItem && !currentItem.isStar) {
                                this.startAwardLevel = this.GetAwardLevel(currentItem.displayRank);
                            } else {
                                this.startAwardLevel = this.currentAwardLevel;
                            }
                        } else {
                            this.startAwardLevel = this.currentAwardLevel;
                        }
                        
                        this.ShowKeyHint('开启自动滚榜', 'A');
                        this.RollNext();
                    }
                });
                return;
            }
            
            const displayList = this.FilterByStarMode(this.rollData, this.starMode);
            const rankedList = this.CalculateRankInfo(displayList);
            
            if (this.judgingTeamId) {
                const currentItem = rankedList.find(item => item.team_id === this.judgingTeamId);
                if (currentItem && !currentItem.isStar) {
                    this.startAwardLevel = this.GetAwardLevel(currentItem.displayRank);
                } else {
                    this.startAwardLevel = this.currentAwardLevel;
                }
            } else {
                this.startAwardLevel = this.currentAwardLevel;
            }
            
            this.ShowKeyHint('开启自动滚榜', 'A');
            this.RollNext();
        } else {
            this.ShowKeyHint('关闭自动滚榜', 'A');
        }
    }
    
    /**
     * 加载获奖modal中的学校logo（直接加载，不使用懒加载）
     * @param {HTMLElement} element - logo元素
     * @param {string} school - 学校名称
     */
    async LoadAwardSchoolLogo(element, school) {
        if (!element || !school) {
            return;
        }
        
        try {
            // 获取学校logo的base URL
            const baseUrl = this.config.school_badge_url || '/static/image/school_badge';
            
            // 构建完整的URL路径（与FetchSchoolLogoDataUrl的逻辑一致）
            // 注意：学校名称需要进行URL编码，以处理特殊字符和中文字符
            const encodedSchool = encodeURIComponent(school);
            const fileKey = `${baseUrl}/${encodedSchool}`;
            
            // 使用父类的FetchSchoolLogoDataUrl方法加载logo
            const dataUrl = await this.FetchSchoolLogoDataUrl(fileKey);
            
            // 设置CSS变量
            element.style.setProperty('--rank-school-logo-bg', `url("${dataUrl}")`);
            element.classList.add('has-background');
        } catch (e) {
            // 加载失败，保持透明（不显示错误，因为logo是可选的）
            element.classList.remove('has-background');
            element.style.setProperty('--rank-school-logo-bg', 'none');
        }
    }
    
    /**
     * 计算真实最终榜的一血状态（基于所有题目都已揭晓）
     * @returns {Object} - { global: {...}, regular: {...} } 格式的一血映射
     */
    CalculateRealFirstBlood() {
        if (!this.rollSolutionMap || !this.teamMap) {
            return { global: {}, regular: {} };
        }
        
        const realGlobalFB = {};
        const realRegularFB = {};
        
        // 遍历所有题目
        for (const problemId in this.problemMap) {
            let globalFirstBlood = null;  // { team_id, in_date, isStarTeam }
            let regularFirstBlood = null; // { team_id, in_date } (仅非打星队)
            
            // 遍历所有队伍，找出该题目的最早AC（基于所有题目都已揭晓的假设）
            for (const teamId in this.rollSolutionMap) {
                const solutions = this.rollSolutionMap[teamId];
                if (!solutions || !solutions.problems || !solutions.problems[problemId]) {
                    continue;
                }
                
                // 遍历该队伍的所有提交，找出第一次AC
                const problemSolutions = solutions.problems[problemId];
                for (const solution of problemSolutions) {
                    if (solution.result === 4) { // AC
                        const team = this.teamMap[teamId];
                        const isStarTeam = team && team.tkind === 2;
                        const inDate = solution.in_date;
                        
                        // 更新全局一血（所有队伍）
                        if (!globalFirstBlood || inDate < globalFirstBlood.in_date) {
                            globalFirstBlood = {
                                team_id: teamId,
                                in_date: inDate,
                                isStarTeam: isStarTeam
                            };
                        }
                        
                        // 更新常规一血（仅非打星队）
                        if (!isStarTeam) {
                            if (!regularFirstBlood || inDate < regularFirstBlood.in_date) {
                                regularFirstBlood = {
                                    team_id: teamId,
                                    in_date: inDate
                                };
                            }
                        }
                        
                        // 找到第一次AC就停止
                        break;
                    }
                }
            }
            
            // 记录全局一血
            if (globalFirstBlood) {
                realGlobalFB[problemId] = {
                    team_id: globalFirstBlood.team_id,
                    in_date: globalFirstBlood.in_date,
                    isStarTeam: globalFirstBlood.isStarTeam
                };
            }
            
            // 记录常规一血
            if (regularFirstBlood) {
                realRegularFB[problemId] = {
                    team_id: regularFirstBlood.team_id,
                    in_date: regularFirstBlood.in_date,
                    isStarTeam: false
                };
            }
        }
        
        return {
            global: realGlobalFB,
            regular: realRegularFB
        };
    }
    
    /**
     * 获取队伍的一血列表（首答题目）- 基于真实最终榜的结果
     * @param {string} team_id - 队伍ID
     * @returns {string} - 一血题目列表，格式如 "A,B,E"，如果没有则返回 "-"
     */
    GetFirstBloodList(team_id) {
        // 计算真实最终榜的一血状态（基于所有题目都已揭晓）
        const realFB = this.CalculateRealFirstBlood();
        
        // 根据打星模式选择使用regular还是global一血
        const fbMap = this.starMode === 1 ? realFB.regular : realFB.global;
        if (!fbMap || Object.keys(fbMap).length === 0) {
            return null; // 返回null而不是'-'，用于判断是否显示
        }
        
        const fbList = [];
        
        // 遍历所有题目，找出该队的一血
        for (const problemId in fbMap) {
            const fbInfo = fbMap[problemId];
            if (fbInfo && fbInfo.team_id === team_id) {
                // 获取题目编号（A, B, C等）
                const problem = this.problemMap[problemId];
                if (problem) {
                    const alphabetIdx = this.GetProblemAlphabetIdx(problem.num);
                    // GetProblemAlphabetIdx 已经返回字母了（'A', 'B', 'C'），直接使用
                    fbList.push(alphabetIdx);
                }
            }
        }
        
        // 按题目顺序排序
        fbList.sort();
        
        return fbList.length > 0 ? fbList.join(', ') : null;
    }
    
    /**
     * 为元素启用跑马灯效果（如果文本溢出）
     * @param {HTMLElement} element - 目标元素
     * @param {string} text - 要显示的文本
     */
    enableMarqueeIfNeeded(element, text) {
        if (!element || !text) {
            // 如果元素或文本为空，清除跑马灯
            if (element) {
                element.classList.remove('needs-marquee');
                element.textContent = '';
            }
            return;
        }
        
        // 先清除之前的跑马灯状态
        element.classList.remove('needs-marquee');
        const oldWrapper = element.querySelector('.marquee-wrapper');
        if (oldWrapper) {
            oldWrapper.remove();
        }
        
        // 先设置文本，检查是否溢出
        element.textContent = text;
        
        // 等待DOM渲染后检查溢出
        requestAnimationFrame(() => {
            // 再次检查元素是否存在（防止在异步过程中被移除）
            if (!element.parentNode) return;
            
            const containerWidth = element.clientWidth;
            const textWidth = element.scrollWidth;
            const isOverflowing = textWidth > containerWidth;
            
            if (isOverflowing && containerWidth > 0) {
                // 创建包装元素
                const wrapper = document.createElement('span');
                wrapper.className = 'marquee-wrapper';
                
                // 为了实现无缝循环，在文本后面添加空白和重复的文本
                // 格式：文本 + 分隔符 + 文本（重复），这样当第一个文本滚动出去后，
                // 第二个文本会无缝接上，形成连续循环效果
                const separator = '    　    '; // 文本之间的分隔符（4个空格，留出空白让观众有时间阅读）
                wrapper.textContent = text + separator + text; // 复制文本实现无缝循环
                
                element.textContent = '';
                element.appendChild(wrapper);
                element.classList.add('needs-marquee');
                
                // 再次等待DOM渲染，确保wrapper已正确添加到DOM
                requestAnimationFrame(() => {
                    if (!element.parentNode || !wrapper.parentNode) return;
                    
                    // 计算单个文本的宽度（不包括重复部分）
                    // 方法：创建一个临时元素测量原始文本宽度，使用相同的样式
                    const tempElement = document.createElement('span');
                    tempElement.style.visibility = 'hidden';
                    tempElement.style.position = 'absolute';
                    tempElement.style.whiteSpace = 'nowrap';
                    const computedStyle = window.getComputedStyle(wrapper);
                    tempElement.style.fontSize = computedStyle.fontSize;
                    tempElement.style.fontWeight = computedStyle.fontWeight;
                    tempElement.style.fontFamily = computedStyle.fontFamily;
                    tempElement.style.letterSpacing = computedStyle.letterSpacing;
                    tempElement.textContent = text;
                    document.body.appendChild(tempElement);
                    const singleTextWidth = tempElement.offsetWidth;
                    document.body.removeChild(tempElement);
                    
                    // 计算总宽度（包含两个文本和分隔符）
                    const totalWrapperWidth = wrapper.scrollWidth;
                    
                    // 计算分隔符的宽度（包括padding-right的影响）
                    // totalWrapperWidth = 2 * singleTextWidth + separatorWidth + paddingRight(200px)
                    const separatorAndPaddingWidth = totalWrapperWidth - 2 * singleTextWidth;
                    
                    // 滚动距离计算：
                    // 让第一个文本完全滚动出去，然后空白（分隔符）显示一段时间，第二个文本无缝接上
                    // translateDistance = 单个文本宽度 + 分隔符宽度的一半（让空白显示，但不完全显示）
                    // 实际上，我们滚动到第一个文本结束 + 分隔符结束的位置，这样第二个文本会无缝接上
                    const translateDistance = singleTextWidth + separatorAndPaddingWidth / 2;
                    
                    // 计算动画时长：滚动速度约为 80px/秒（快速、麻利）
                    // 根据文本长度和空白长度动态调整，但保证速度足够快
                    const baseSpeed = 80; // 基础速度：80px/秒
                    const duration = Math.max(4, translateDistance / baseSpeed); // 最短4秒，保证观众能看到完整内容
                    
                    // 设置CSS变量
                    element.style.setProperty('--marquee-duration', duration + 's');
                    element.style.setProperty('--marquee-translate', `-${translateDistance}px`);
                });
            } else {
                // 不需要跑马灯，直接显示文本（已经在上面的textContent设置好了）
                element.classList.remove('needs-marquee');
            }
        });
    }
    
    /**
     * 显示获奖
     */
    ShowAward(team_id, award) {
        const team = this.teamMap[team_id];
        if (!team) return;
        const teamData = this.rollDataMap ? this.rollDataMap.get(team_id) : null;
        if (!teamData) return;
        
        // 设置获奖信息
        const awardLevel = this.container.querySelector('#award-level');
        const awardSchool = this.container.querySelector('#award-school');
        const awardTeamName = this.container.querySelector('#award-team-name');
        const awardMembers = this.container.querySelector('#award-members');
        const awardCoach = this.container.querySelector('#award-coach');
        const awardRank = this.container.querySelector('#award-rank');
        const awardSolved = this.container.querySelector('#award-solved');
        const awardFirstBlood = this.container.querySelector('#award-first-blood');
        const awardPhoto = this.container.querySelector('#award-team-photo');
        const awardPhotoPlaceholder = this.container.querySelector('#award-photo-placeholder');
        const awardDetailsCard = this.container.querySelector('.award-details-card');
        const awardSchoolLogo = this.container.querySelector('#award-school-logo');
        
        if (awardLevel) {
            awardLevel.textContent = this.GetAwardName(award);
            awardLevel.className = `award-level-title ${award}`;
        }
        
        // 移除emoji奖牌背景装饰（用户不需要）
        if (awardDetailsCard) {
            awardDetailsCard.style.removeProperty('--award-bg-image');
        }
        
        // 使用跑马灯功能设置文本（会自动检测溢出并启用跑马灯）
        this.enableMarqueeIfNeeded(awardSchool, team.school || this.CreateBilingualText('未知学校/组织', 'Unknown School/Organization'));
        this.enableMarqueeIfNeeded(awardTeamName, team.name || team_id);
        this.enableMarqueeIfNeeded(awardMembers, team.tmember || '');
        this.enableMarqueeIfNeeded(awardCoach, team.coach || '');
        
        if (awardRank) awardRank.textContent = this.GetCurrentRank(team_id);
        if (awardSolved) awardSolved.textContent = teamData.solved;
        
        // 处理首答信息：有首答才显示，没有就隐藏（基于真实最终榜的结果）
        const awardFirstBloodRow = this.container.querySelector('#award-first-blood-row');
        const firstBloodList = this.GetFirstBloodList(team_id);
        if (firstBloodList && firstBloodList !== null && firstBloodList !== '-') {
            this.enableMarqueeIfNeeded(awardFirstBlood, firstBloodList);
            if (awardFirstBloodRow) {
                awardFirstBloodRow.style.display = 'flex';
                awardFirstBloodRow.classList.remove('award-info-row-hidden'); // 移除隐藏标记
            }
        } else {
            this.enableMarqueeIfNeeded(awardFirstBlood, ''); // 清空首答内容
            if (awardFirstBloodRow) {
                awardFirstBloodRow.style.display = 'none';
                awardFirstBloodRow.classList.add('award-info-row-hidden'); // 添加隐藏标记
            }
        }
        
        // 处理学校logo（半透明背景）
        if (awardSchoolLogo && team.school) {
            awardSchoolLogo.setAttribute('data-school', team.school);
            awardSchoolLogo.classList.remove('has-background');
            // 直接加载学校logo（不使用懒加载，因为modal需要立即显示）
            this.LoadAwardSchoolLogo(awardSchoolLogo, team.school);
        } else if (awardSchoolLogo) {
            // 没有学校信息，清除logo
            awardSchoolLogo.removeAttribute('data-school');
            awardSchoolLogo.style.setProperty('--rank-school-logo-bg', 'none');
            awardSchoolLogo.classList.remove('has-background');
        }
        
        // 处理队伍照片
        if (awardPhoto && awardPhotoPlaceholder) {
            const photoUrl = `${this.config.team_photo_url}/${team_id}.jpg`;
            
            // 先显示placeholder（emoji奖牌）
            this.showAwardPlaceholder(awardPhotoPlaceholder, award);
            awardPhoto.style.display = 'none';
            awardPhotoPlaceholder.style.display = 'flex';
            
            // 尝试加载图片
            awardPhoto.onload = () => {
                // 图片加载成功，显示图片，隐藏placeholder
                awardPhoto.style.display = 'block';
                awardPhotoPlaceholder.style.display = 'none';
            };
            
            awardPhoto.onerror = () => {
                // 图片加载失败，保持显示placeholder
                awardPhoto.style.display = 'none';
                awardPhotoPlaceholder.style.display = 'flex';
            };
            
            awardPhoto.src = photoUrl;
        }
        
        this.ShowModal('award');
    }
    
    /**
     * 显示获奖placeholder（emoji奖牌）
     */
    showAwardPlaceholder(placeholderElement, award) {
        const emojiMap = {
            gold: '🥇',
            silver: '🥈',
            bronze: '🥉'
        };
        const emoji = emojiMap[award] || '🏆';
        placeholderElement.textContent = emoji;
        placeholderElement.className = `award-photo-placeholder award-photo-placeholder-${award}`;
    }
    
    /**
     * 获取获奖名称
     */
    GetAwardName(award) {
        const names = {
            gold: '金奖',
            silver: '银奖',
            bronze: '铜奖'
        };
        return names[award] || '';
    }
    
    /**
     * 获取当前排名
     * 关键修复：通过 CalculateRankInfo 获取正确的 displayRank，而不是使用错误的 team.rank
     */
    GetCurrentRank(team_id) {
        if (!this.rollData || this.rollData.length === 0) {
            return '-';
        }
        const rollTeamItem = this.rollDataMap.get(team_id);
        if (!rollTeamItem) {
            return '-';
        }
        // 使用 CalculateRankInfo 获取正确的排名
        return rollTeamItem.displayRank;
    }
    
    /**
     * 显示模态框
     */
    ShowModal(type) {
        const modalId = type === 'award' ? 'award-modal' : 
                        type === 'rollHelp' ? 'roll-help-modal' : null;
        if (modalId) {
            const modal = this.container.querySelector(`#${modalId}`);
            if (modal) {
                modal.style.display = 'flex';
            }
        }
    }
    
    /**
     * 隐藏模态框
     */
    HideModal(type) {
        const modalId = type === 'award' ? 'award-modal' : 
                        type === 'rollHelp' ? 'roll-help-modal' : null;
        if (modalId) {
            const modal = this.container.querySelector(`#${modalId}`);
            if (modal) {
                modal.style.display = 'none';
            }
        }
        
        // 如果是获奖modal关闭，且当前在award_close步骤，标记为已关闭
        // 注意：不要在HideModal中自动继续，让RollNextStep控制流程
        if (type === 'award' && this.currentRollStep === 'award_close' && this.isRolling) {
            // modal已关闭，标记完成，但不在这里继续（由RollNextStep控制）
            // 这样确保手动按N键时，流程正确
        }
        
        // 兼容旧逻辑：如果是award步骤且modal关闭，也继续下一步
        if (type === 'award' && this.currentRollStep === 'award' && this.isRolling) {
            this.currentRollStep = null;
            this.ClearJudgingHighlight();
            
            // 自动模式下延迟后继续
            this.TryAutoRolling('JudgeConfirm');
        }
    }
    
    /**
     * 显示滚榜帮助
     */
    ShowRollHelp() {
        this.ShowModal('rollHelp');
    }
    
    /**
     * 处理滚榜键盘事件
     */
    HandleRollKeydown(e) {
        if (!this.isRolling) {
            return;
        }
        
        // 空格键与N键相同功能（单步执行）
        if (e.key === ' ' || e.key === 'Space') {
            e.preventDefault(); // 防止页面滚动
            this.RollNext();
            return;
        }
        
        // 回车键与F键相同功能（跳到下个奖区）
        if (e.key === 'Enter') {
            e.preventDefault();
            this.FastSkipToAwardArea();
            return;
        }
        
        // 退格键与U键相同功能（撤回一步）
        if (e.key === 'Backspace') {
            e.preventDefault(); // 防止浏览器后退
            this.RollUndo();
            return;
        }
        
        if (e.key === 'f' || e.key === 'F') {
            this.FastSkipToAwardArea();
            return;
        }
        
        if (e.key === 'g' || e.key === 'G') {
            this.JumpForward10Teams();
            return;
        }
        
        if (e.key === 'n' || e.key === 'N') {
            this.RollNext();
        } else if (e.key === 'u' || e.key === 'U') {
            this.RollUndo();
        } else if (e.key === 'i' || e.key === 'I') {
            this.JumpBack10Teams();
        } else if (e.key === 'w' || e.key === 'W') {
            const oldSpeed = this.autoSpeed;
            this.RollSpeedUp();
            if (this.autoSpeed < oldSpeed) {
                this.ShowKeyHint(`加速: ${this.autoSpeed}ms (${this.rollSpeedMultiplier.toFixed(1)}x)`, 'W');
            }
        } else if (e.key === 's' || e.key === 'S') {
            const oldSpeed = this.autoSpeed;
            this.RollSpeedDown();
            if (this.autoSpeed > oldSpeed) {
                this.ShowKeyHint(`减速: ${this.autoSpeed}ms (${this.rollSpeedMultiplier.toFixed(1)}x)`, 'S');
            }
        } else if (e.key === 'r' || e.key === 'R') {
            this.RollResetSpeed();
            this.ShowKeyHint(`恢复默认速度: ${this.autoSpeed}ms`, 'R');
        } else if (e.key === 'a' || e.key === 'A') {
            this.ToggleAutoRoll();
        }
    }
    
    /**
     * 获取滚榜数据
     */
    getRollData() {
        return this.rollData;
    }
    
    /**
     * 导出离线滚榜包
     */
    async ExportOfflineRoll() {
        if (!window.RankToolExportOfflineRollPack) {
            this.ShowMessage('导出功能未加载，请刷新页面重试');
            return;
        }
        
        if (!this.data) {
            this.ShowMessage('数据未加载，无法导出');
            return;
        }
        
        // 显示进度模态框
        const progressOverlay = document.createElement('div');
        progressOverlay.id = 'export-offline-progress-overlay';
        progressOverlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 100000;
            backdrop-filter: blur(4px);
        `;
        
        const progressContainer = document.createElement('div');
        progressContainer.style.cssText = `
            background: white;
            padding: 32px 48px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            min-width: 400px;
            max-width: 600px;
        `;
        
        progressContainer.innerHTML = `
            <div style="margin-bottom: 20px; font-size: 18px; font-weight: 600; color: #333;">
                ${this.CreateBilingualText('正在导出离线滚榜包...', 'Exporting Offline Roll Pack...')}
            </div>
            <div style="width: 100%; height: 24px; background: #e9ecef; border-radius: 6px; overflow: hidden; margin-bottom: 12px;">
                <div id="export-offline-progress-bar" style="width: 0%; height: 100%; background: linear-gradient(90deg, #3b82f6, #2563eb); transition: width 0.3s ease; border-radius: 6px;"></div>
            </div>
            <div id="export-offline-progress-text" style="font-size: 14px; color: #666; text-align: center;">
                ${this.CreateBilingualText('准备中...', 'Preparing...')}
            </div>
        `;
        
        progressOverlay.appendChild(progressContainer);
        document.body.appendChild(progressOverlay);
        
        try {
            // 进度回调
            const progressCallback = (message, progress) => {
                const progressBar = document.getElementById('export-offline-progress-bar');
                const progressText = document.getElementById('export-offline-progress-text');
                if (progressBar && progressText) {
                    const percentage = Math.min(100, Math.round(progress));
                    progressBar.style.width = `${percentage}%`;
                    progressText.textContent = message || `${this.CreateBilingualText('处理中', 'Processing')}: ${percentage}%`;
                }
            };
            
            // 调用导出功能
            const zipBlob = await window.RankToolExportOfflineRollPack(this, progressCallback);
            
            // 生成下载链接
            const url = URL.createObjectURL(zipBlob);
            const a = document.createElement('a');
            a.href = url;
            
            // 生成文件名：滚榜离线包-<比赛标题>-<14位时间戳>.zip
            const contestTitle = this.data?.contest?.title || '滚榜';
            const sanitizedTitle = window.RankToolSanitizeFilename ? window.RankToolSanitizeFilename(contestTitle) : contestTitle.replace(/[<>:"/\\|?*]+/g, '-').replace(/\s+/g, '_');
            const timestamp = window.RankToolGenerateTimestamp14 ? window.RankToolGenerateTimestamp14() : new Date().toISOString().slice(0, 19).replace(/[:-]/g, '').slice(0, 14);
            a.download = `滚榜离线包-${sanitizedTitle}-${timestamp}.zip`;
            
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            
            // 移除进度模态框
            progressOverlay.remove();
            
            this.ShowMessage(this.CreateBilingualText('导出成功！', 'Export successful!'));
        } catch (error) {
            console.error('导出离线滚榜包失败:', error);
            progressOverlay.remove();
            this.ShowMessage(this.CreateBilingualText('导出失败：' + error.message, 'Export failed: ' + error.message));
        }
    }
}

// 导出到全局
if (typeof window !== 'undefined') {
    window.RankRollSystem = RankRollSystem;
}

