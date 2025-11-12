/**
 * 气球管理系统
 * 继承自 RankSystem，用于管理气球发放状态
 */

if (typeof BalloonManagerSystem === "undefined") {
    class BalloonManagerSystem extends RankSystem {
        constructor(containerId, config = {}) {
            // 调用父类构造函数
            super(containerId, config);

            // 气球管理特定配置
            this.autoRefreshInterval = 10000; // 默认10秒刷新一次
            this.refreshTimer = null;
            this.countdownTimer = null;
            this.countdownSeconds = 0;
            this.autoRefreshEnabled = false;

            // 气球状态映射
            this.balloonStatusMap = {
                0: { cn: "未处理", en: "Not Sent", color: "#dc3545" }, // 红色 - 未发
                10: { cn: "已通知", en: "Printed/Issued", color: "#ffc107" }, // 黄色 - 已通知
                20: { cn: "已分配", en: "Assigned", color: "#0dcaf0" }, // 青色 - 已分配
                30: { cn: "已发放", en: "Delivered", color: "#198754" }, // 绿色 - 已发放
            };

            // 气球状态统计
            this.balloonStats = {
                0: 0, // 未发气球
                10: 0, // 已通知
                20: 0, // 已分配
                30: 0, // 已发放
            };

            // 注册problem-item的特殊tooltip处理函数
            this.specialTooltipHandlers = {
                "problem-item": this.GenerateProblemItemTooltip.bind(this),
            };
        }

        /**
         * 重写 Init 方法，确保容器添加balloon-manager-system类
         */
        Init() {
            // 调用父类Init
            super.Init();

            // 为容器添加balloon-manager-system类，用于CSS选择器
            if (this.container) {
                this.container.classList.add("balloon-manager-system");
            }
        }

        /**
         * 重写 CreateHeader 方法，隐藏默认header（模板中已有自定义header）
         */
        CreateHeader() {
            // 不调用父类方法，因为我们使用模板中的自定义header
            // 初始化自动刷新开关
            setTimeout(() => {
                this.InitAutoRefreshSwitch();
            }, 100);
        }

        /**
         * 初始化自动刷新开关
         */
        InitAutoRefreshSwitch() {
            const switchEl = document.querySelector("#balloon-auto-refresh-switch");
            if (!switchEl) return;

            // 初始化 csg-switch
            if (window.csgSwitch) {
                window.csgSwitch.initSwitch(switchEl, {
                    onChange: (checked) => {
                        this.OnAutoRefreshToggle(checked);
                    },
                });
            }
        }

        /**
         * 处理自动刷新开关切换
         */
        OnAutoRefreshToggle(checked) {
            this.autoRefreshEnabled = checked;

            if (checked) {
                // 开启自动刷新
                this.StartAutoRefresh();
            } else {
                // 关闭自动刷新
                this.StopAutoRefresh();
            }
        }

        /**
         * 启动自动刷新
         */
        StartAutoRefresh() {
            // 清除已有定时器
            this.StopAutoRefresh();

            // 重置倒计时
            this.countdownSeconds = Math.floor(this.autoRefreshInterval / 1000);
            this.UpdateCountdownDisplay();

            // 显示倒计时
            const countdownEl = document.getElementById("balloon-refresh-countdown");
            if (countdownEl) {
                countdownEl.style.display = "inline-block";
            }

            // 启动倒计时定时器
            this.countdownTimer = setInterval(() => {
                this.countdownSeconds--;
                this.UpdateCountdownDisplay();

                if (this.countdownSeconds <= 0) {
                    // 倒计时结束，刷新数据
                    this.RefreshData();
                    // 重置倒计时
                    this.countdownSeconds = Math.floor(this.autoRefreshInterval / 1000);
                }
            }, 1000);
        }

        /**
         * 停止自动刷新
         */
        StopAutoRefresh() {
            if (this.refreshTimer) {
                clearInterval(this.refreshTimer);
                this.refreshTimer = null;
            }

            if (this.countdownTimer) {
                clearInterval(this.countdownTimer);
                this.countdownTimer = null;
            }

            // 隐藏倒计时
            const countdownEl = document.getElementById("balloon-refresh-countdown");
            if (countdownEl) {
                countdownEl.style.display = "none";
            }

            this.countdownSeconds = 0;
        }

        /**
         * 更新倒计时显示
         */
        UpdateCountdownDisplay() {
            const countdownTextEl = document.getElementById("balloon-countdown-text");
            if (countdownTextEl) {
                countdownTextEl.textContent = this.countdownSeconds;
            }
        }

        /**
         * 重写 HandleKeydown 方法
         * 只允许 F5 刷新数据，禁用其他所有快捷键
         */
        HandleKeydown(e) {
            // 允许 F5 刷新数据（阻止默认刷新行为）
            if (e.key === "F5" && !e.ctrlKey) {
                e.preventDefault();
                this.RefreshData();
                return;
            }

            // 其他所有快捷键都被禁用
            // 不调用父类方法
            return;
        }

        /**
         * 重写 ProcessData 方法，处理气球数据
         */
        ProcessData(flg_real_rank = false) {
            if (!this.data) return;

            // 过滤solution，只保留AC结果（result=4）
            if (this.data.solution) {
                this.data.solution = this.data.solution.filter(
                    (solution) => solution.result === 4
                );
            }

            // 构建contest_balloon映射表，方便后续查找
            this.balloonMap = new Map();
            if (
                this.data.contest_balloon &&
                Array.isArray(this.data.contest_balloon)
            ) {
                this.data.contest_balloon.forEach((item) => {
                    // item格式: [contest_id, problem_id, team_id, ac_time, pst, bst, balloon_sender]
                    const key = `${item[2]}_${item[1]}`; // team_id_problem_id
                    this.balloonMap.set(key, {
                        team_id: item[2],
                        problem_id: item[1],
                        ac_time: item[3],
                        pst: item[4],
                        bst: item[5], // 0未发 10已通知 20已分配 30已发放
                        balloon_sender: item[6],
                    });
                });
            }

            // 调用父类方法处理数据
            super.ProcessData(flg_real_rank);

            // 统计气球状态
            this.CalculateBalloonStats();

            // 更新全局统计显示
            this.UpdateGlobalStats();
        }

        /**
         * 计算气球状态统计
         */
        CalculateBalloonStats() {
            // 重置统计
            this.balloonStats = {
                0: 0, // 未发气球
                10: 0, // 已通知
                20: 0, // 已分配
                30: 0, // 已发放
            };

            if (!this.data || !this.data.solution) return;

            // 统计所有AC的solution（这些都应该有气球）
            // 对于每个AC，如果在contest_balloon中有记录，使用记录中的bst状态
            // 如果没有记录，则默认为0（未发气球）
            this.data.solution.forEach((solution) => {
                const teamId = solution.team_id;
                const problemId = solution.problem_id;
                const key = `${teamId}_${problemId}`;

                // 从balloonMap中获取气球状态，如果没有记录则默认为0（未发气球）
                const balloon = this.balloonMap?.get(key);
                const bst = balloon ? balloon.bst || 0 : 0;
                if (this.balloonStats.hasOwnProperty(bst)) {
                    this.balloonStats[bst]++;
                } else {
                    // 未知状态，归入未发气球
                    this.balloonStats[0]++;
                }
            });
        }

        /**
         * 更新全局统计信息显示
         */
        UpdateGlobalStats() {
            // 计算全局统计
            this.CalculateBalloonStats();

            // 更新DOM中的统计数值（不重新生成HTML）
            const statValue0 = document.getElementById("balloon-stat-value-0");
            const statValue10 = document.getElementById("balloon-stat-value-10");
            const statValue20 = document.getElementById("balloon-stat-value-20");
            const statValue30 = document.getElementById("balloon-stat-value-30");

            if (statValue0) statValue0.textContent = this.balloonStats[0] || 0;
            if (statValue10) statValue10.textContent = this.balloonStats[10] || 0;
            if (statValue20) statValue20.textContent = this.balloonStats[20] || 0;
            if (statValue30) statValue30.textContent = this.balloonStats[30] || 0;

            // 绑定点击事件（只需要绑定一次）
            this.BindGlobalStatsClickEvents();
        }

        /**
         * 绑定全局统计点击事件（只绑定一次）
         */
        BindGlobalStatsClickEvents() {
            const statsContainer =
                document.getElementById("balloon-global-stats") ||
                document.getElementById("balloon-queue-stats");
            if (!statsContainer) return;

            // 防止重复绑定
            if (statsContainer.dataset.eventsBound === "true") return;

            const statItems = statsContainer.querySelectorAll(".balloon-stat-item");
            statItems.forEach((statItem) => {
                statItem.addEventListener("click", (e) => {
                    e.stopPropagation();
                    const status = parseInt(statItem.getAttribute("data-status"));

                    // 如果是队列系统，使用筛选功能；否则使用滚动功能
                    if (this instanceof BalloonQueueSystem) {
                        this.OnStatClick(status);
                    } else {
                        this.ScrollToFirstBalloonStatus(null, status);
                    }
                });
            });

            // 标记已绑定
            statsContainer.dataset.eventsBound = "true";
        }

        /**
         * 滚动到第一个符合指定气球状态的行
         * @param {string|null} problemId - 题目ID，null表示所有题目
         * @param {number} targetStatus - 目标状态
         */
        ScrollToFirstBalloonStatus(problemId, targetStatus) {
            if (!this.rankList || !this.data) return;

            // 查找第一个符合状态的队伍
            for (const rankItem of this.rankList) {
                const teamId = rankItem.team_id;

                if (problemId) {
                    // 指定题目
                    const balloonStatus = this.GetProblemBalloonStatus(teamId, problemId);
                    if (balloonStatus === targetStatus) {
                        this.ScrollToTeamAndHighlight(teamId, problemId);
                        return;
                    }
                } else {
                    // 所有题目中查找第一个符合状态的
                    if (this.data.problem) {
                        for (const problem of this.data.problem) {
                            const balloonStatus = this.GetProblemBalloonStatus(
                                teamId,
                                problem.problem_id
                            );
                            if (balloonStatus === targetStatus) {
                                this.ScrollToTeamAndHighlight(teamId, problem.problem_id);
                                return;
                            }
                        }
                    }
                }
            }
        }

        /**
         * 滚动到队伍并高亮题目
         */
        ScrollToTeamAndHighlight(teamId, problemId) {
            const row = document.getElementById(`rank-grid-${teamId}`);
            if (row) {
                // 滚动到该行
                row.scrollIntoView({ behavior: "smooth", block: "center" });

                // 高亮显示该题目
                if (problemId && this.problemMap[problemId]) {
                    const problemAlphabetIdx = RankToolGetProblemAlphabetIdx(
                        this.problemMap[problemId].num
                    );
                    const problemItem = row.querySelector(
                        `[d-pro-idx="${problemAlphabetIdx}"]`
                    );
                    if (problemItem) {
                        problemItem.style.transition = "all 0.3s ease";
                        problemItem.style.transform = "scale(1.1)";
                        problemItem.style.boxShadow = "0 0 20px rgba(13, 110, 253, 0.5)";

                        setTimeout(() => {
                            problemItem.style.transform = "";
                            problemItem.style.boxShadow = "";
                        }, 2000);
                    }
                }
            }
        }

        /**
         * 重写 CreateProblemGroup 方法，显示气球状态边框
         */
        CreateProblemGroup(problemStats, item = null) {
            let html = "";

            if (!this.problemMap || Object.keys(this.problemMap).length === 0) {
                return '<div class="problem-group"><!-- 题目数据加载中 --></div>';
            }

            const problemIds = Object.keys(this.problemMap).sort(
                (a, b) => this.problemMap[a].num - this.problemMap[b].num
            );

            problemIds.forEach((problemId) => {
                const stats = problemStats[problemId] || {
                    status: "none",
                    submitCount: 0,
                    lastSubmitTime: "",
                    problemAlphabetIdx: RankToolGetProblemAlphabetIdx(
                        this.problemMap[problemId].num
                    ),
                };

                // 检查一血状态（保留一血效果）
                let isGlobalFirstBlood = false;
                let isRegularFirstBlood = false;

                // 直接比较team_id
                isGlobalFirstBlood =
                    this.map_fb?.global?.[problemId]?.team_id === item?.team_id;
                isRegularFirstBlood =
                    this.map_fb?.regular?.[problemId]?.team_id === item?.team_id;

                // 构建一血相关的CSS类
                let firstBloodClasses = "";
                if (isRegularFirstBlood) {
                    firstBloodClasses += " pro-first-blood-regular";
                }
                if (isGlobalFirstBlood) {
                    firstBloodClasses += " pro-first-blood-global";
                }

                // 只在有AC时才获取气球状态和添加边框
                let balloonBorderClass = "";
                let balloonBorderStyle = "";

                if (stats.status === "ac") {
                    // 获取该题目的气球状态
                    const balloonStatus = this.GetProblemBalloonStatus(
                        item.team_id,
                        problemId
                    );
                    const balloonStatusInfo =
                        this.balloonStatusMap[balloonStatus] || this.balloonStatusMap[0];

                    // 构建气球状态边框类名
                    balloonBorderClass = `balloon-status-${balloonStatus}`;

                    // 构建气球状态边框样式（使用outline，不占DOM空间，更粗的边框）
                    balloonBorderStyle = `outline: 6px solid ${balloonStatusInfo.color}; outline-offset: -6px;`;
                }

                // 计算总分钟数
                let briefMinute = "";
                if (
                    stats.status === "ac" &&
                    stats.lastSubmitTime &&
                    /^(\d{1,2}:)?\d{1,2}:\d{2}$/.test(stats.lastSubmitTime)
                ) {
                    const timeParts = stats.lastSubmitTime.split(":");
                    let totalMinutes = 0;
                    if (timeParts.length === 3) {
                        const hours = parseInt(timeParts[0]) || 0;
                        const minutes = parseInt(timeParts[1]) || 0;
                        totalMinutes = hours * 60 + minutes;
                    } else if (timeParts.length === 2) {
                        totalMinutes = parseInt(timeParts[0]) || 0;
                    }
                    briefMinute = totalMinutes + "'";
                }

                // 存储额外信息用于tooltip（通过data属性）
                const teamId = item?.team_id || "";
                const globalFB = this.map_fb?.global?.[problemId];
                const regularFB = this.map_fb?.regular?.[problemId];
                const globalFBTeamId = globalFB?.team_id || "";
                const regularFBTeamId = regularFB?.team_id || "";
                const balloonStatus =
                    stats.status === "ac"
                        ? this.GetProblemBalloonStatus(teamId, problemId)
                        : null;
                const balloonStatusInfo =
                    balloonStatus !== null
                        ? this.balloonStatusMap[balloonStatus] || this.balloonStatusMap[0]
                        : null;

                html += `
                    <div class="rank-col rank-col-problem">
                        <div class="${this.GetProblemStatusClass(
                    stats
                )}${firstBloodClasses} ${balloonBorderClass}" 
                            ${balloonBorderStyle
                        ? `style="${balloonBorderStyle}"`
                        : ""
                    }
                            d-pro-idx="${stats.problemAlphabetIdx}"
                            d-sub-cnt="${stats.submitCount || 0}"
                            d-last-sub="${this.GetLastSubmitTimeDisplay(stats)}"
                            d-team-id="${teamId}"
                            d-problem-id="${problemId}"
                            d-global-fb-team="${globalFBTeamId}"
                            d-regular-fb-team="${regularFBTeamId}"
                            d-balloon-status="${balloonStatus !== null ? balloonStatus : ""
                    }">
                            <div class="problem-content balloon-problem-content">
                                ${briefMinute
                        ? `<span class="time-brief">${briefMinute}</span>`
                        : '<span class="time-brief"></span>'
                    }
                            </div>
                        </div>
                    </div>
                `;
            });

            return html;
        }

        /**
         * 重写 CreateHeaderRow 方法，移除罚时列
         */
        CreateHeaderRow() {
            const headerRow = super.CreateHeaderRow();
            // 移除罚时列
            const penaltyCol = headerRow.querySelector(".rank-col-penalty");
            if (penaltyCol) {
                penaltyCol.remove();
            }
            return headerRow;
        }

        /**
         * 重写 CreateRankRow 方法，在校名前面加上team_id，并移除罚时列
         */
        async CreateRankRow(item, rank, index) {
            // 调用父类方法创建基础行
            const row = await super.CreateRankRow(item, rank, index);

            // 移除罚时列
            const penaltyCol = row.querySelector(".rank-col-penalty");
            if (penaltyCol) {
                penaltyCol.remove();
            }

            // team_id 现在由基类 CreateSchoolName 方法处理，不需要额外操作

            return row;
        }

        /**
         * 重写 GenerateProblemItemTooltip 方法，显示完整的气球管理信息
         */
        GenerateProblemItemTooltip(element) {
            // 从data属性中提取信息
            const problemAlphabetIdx = element.getAttribute("d-pro-idx") || "?";
            const teamId = element.getAttribute("d-team-id") || "";
            const problemId = element.getAttribute("d-problem-id") || "";
            const globalFBTeamId = element.getAttribute("d-global-fb-team") || "";
            const regularFBTeamId = element.getAttribute("d-regular-fb-team") || "";
            const balloonStatus = element.getAttribute("d-balloon-status");

            // 构建tooltip内容，使用更好的排版
            let titlecn = "";
            let titleen = "";

            // 分隔线
            const separator = "─".repeat(15);

            // 题号（第一行）
            titlecn += `题目 ${problemAlphabetIdx}`;
            titleen += `Problem ${problemAlphabetIdx}`;

            // 分隔线
            titlecn += `\n${separator}`;
            titleen += `\n${separator}`;

            // 队伍ID
            if (teamId) {
                titlecn += `\n队伍ID：${teamId}`;
                titleen += `\nTeam ID: ${teamId}`;
            }

            // 气球状态（仅在有AC时显示）
            if (balloonStatus !== null && balloonStatus !== "") {
                const balloonStatusInfo =
                    this.balloonStatusMap[balloonStatus] || this.balloonStatusMap[0];
                titlecn += `\n气球状态：${balloonStatusInfo.cn}`;
                titleen += `\nBalloon Status: ${balloonStatusInfo.en}`;
            }

            // 分隔线
            titlecn += `\n${separator}`;
            titleen += `\n${separator}`;

            // 正式队首答
            if (regularFBTeamId) {
                const isRegularFB = regularFBTeamId === teamId;
                if (isRegularFB) {
                    titlecn += `\n正式队首答：✓ 是`;
                    titleen += `\nRegular First Blood: ✓ Yes`;
                } else {
                    titlecn += `\n正式队首答：${regularFBTeamId}`;
                    titleen += `\nRegular First Blood: ${regularFBTeamId}`;
                }
            } else {
                titlecn += `\n正式队首答：—`;
                titleen += `\nRegular First Blood: —`;
            }

            // 全场首答
            if (globalFBTeamId) {
                const isGlobalFB = globalFBTeamId === teamId;
                if (isGlobalFB) {
                    titlecn += `\n全场首答：★ 是`;
                    titleen += `\nGlobal First Blood: ★ Yes`;
                } else {
                    titlecn += `\n全场首答：${globalFBTeamId}`;
                    titleen += `\nGlobal First Blood: ${globalFBTeamId}`;
                }
            } else {
                titlecn += `\n全场首答：—`;
                titleen += `\nGlobal First Blood: —`;
            }

            return { titlecn, titleen };
        }

        /**
         * 获取指定队伍在指定题目的气球状态
         */
        GetProblemBalloonStatus(teamId, problemId) {
            if (!this.balloonMap) return 0;

            // 从contest_balloon映射表中查找
            const key = `${teamId}_${problemId}`;
            const balloon = this.balloonMap.get(key);

            if (balloon) {
                return balloon.bst || 0;
            }

            // 如果没有对应的contest_balloon条目，等同于bst为0（未发气球）
            return 0;
        }

        /**
         * 重写 UpdatePageTitle 方法
         */
        UpdatePageTitle() {
            if (!this.data) return;

            const shouldShowTitle = this.isFullscreen
                ? this.config.flg_show_fullscreen_contest_title
                : this.config.flg_show_page_contest_title;

            if (!shouldShowTitle) return;

            const title = this.data.contest.title;
            const modeText = "气球总览";

            if (this.elements.pageTitle) {
                // 更新标题，保持双语结构
                const pageTitleEl = document.getElementById("balloon-page-title");
                if (pageTitleEl) {
                    pageTitleEl.innerHTML = `${title} - 气球总览<en-text>${title} - Balloon Overview</en-text>`;
                } else if (this.elements.pageTitle) {
                    this.elements.pageTitle.innerHTML = `${title} - 气球总览<en-text>${title} - Balloon Overview</en-text>`;
                }
            }
        }

        /**
         * 重写 Cleanup 方法，清理定时器
         */
        Cleanup() {
            // 停止自动刷新
            this.StopAutoRefresh();

            // 调用父类清理方法
            super.Cleanup();
        }

        /**
         * 重写 RefreshData 方法，保持自动刷新状态
         */
        async RefreshData() {
            const wasAutoRefreshEnabled = this.autoRefreshEnabled;
            
            // 🔥 数据刷新后，清空当前视图，强制重新获取
            this.sortedBalloonList = [];

            // 调用父类刷新方法
            await super.RefreshData();

            // 如果之前开启了自动刷新，重新启动
            if (wasAutoRefreshEnabled) {
                this.StartAutoRefresh();
            }
        }
    }

    // #########################################
    //  BalloonQueueSystem - 气球队列系统
    //  继承自 BalloonManagerSystem
    // #########################################

    class BalloonQueueSystem extends BalloonManagerSystem {
        constructor(containerId, config = {}) {
            // 合并全局配置和传入配置
            const globalConfig = window.RANK_CONFIG || {};
            const mergedConfig = Object.assign({}, globalConfig, config);

            // 在调用 super() 之前，先读取队列配置（因为 super() 会立即触发 Init()）
            const queueConfig = window.BALLOON_QUEUE_CONFIG || {};

            // 调用父类构造函数（传入合并后的配置）
            // 注意：父类构造函数会立即调用 this.Init()，所以需要在 Init() 中也能访问这些配置
            super(containerId, mergedConfig);

            // 队列系统特定配置（从window.BALLOON_QUEUE_CONFIG获取）
            // 优先判断是管理员，如果是管理员就不再判断is_balloon_sender
            this.isBalloonManager = queueConfig.is_balloon_manager || false;
            this.isBalloonSender = this.isBalloonManager
                ? false
                : queueConfig.is_balloon_sender || false;
            this.currentUser = queueConfig.current_user || null;
            this.teamRoom = queueConfig.team_room || null;
            this.changeStatusUrl =
                queueConfig.change_status_url ||
                "/cpcsys/contest/balloon_change_status_ajax";

            // 获取 contest_id（用于 localStorage key）
            this.contestId = mergedConfig.cid_list || globalConfig.cid_list || "";

            // 从 localStorage 读取筛选条件
            this.filters = this.LoadFiltersFromStorage();

            // 当前标签页（仅balloonSender使用）
            this.currentTab = "queue"; // 'queue' 或 'my_balloons'

            // 数据缓存
            this.balloonList = []; // 处理后的气球列表
            this.rooms = []; // 所有room列表
            this.senders = []; // 所有配送员列表
            this.schools = []; // 所有学校列表
            this.problems = []; // 所有题号列表

            // 自动打印相关
            this.autoPrintEnabled = false; // 自动打印开关状态
            this.autoPrintTimer = null; // 自动打印定时器
            this.autoPrintCountdown = 10; // 倒计时（秒）
            this.autoPrintCountdownTimer = null; // 倒计时定时器（只在数量不足时使用）
            this.autoPrintRefreshTimer = null; // 刷新定时器（用于数量不足时的 setTimeout 刷新）
            this.autoPrintWaitStartTime = null; // 等待开始时间（用于20秒超时判断）
            this.sortedBalloonList = []; // 排序后的气球列表（用于自动打印）
            this.autoPrintLastIdx = 0; // 上次打印的最大序号（用于判断是否需要刷新数据）
            this.autoPrintPageFinishTime = null; // 第一页打印完成的时间
            this.autoPrintPrintableCount = 0; // 可打印数量统计值（bst=0 的数量）
            this.autoPrintInsufficientRefreshCount = 0; // 数量不足时的刷新次数
            
            // 🔥 状态更新频率控制
            this.statusChangeCallCount = 0; // 连续调用 ChangeBalloonStatus 的次数
            this.statusChangeWaitTimer = null; // 等待定时器
            this.statusChangeWaitCountdown = 0; // 等待倒计时（秒）
            this.statusChangeWaitCountdownTimer = null; // 等待倒计时定时器
            
            // 🔥 排序筛选等待控制
            this.sortWaitCountdown = 0; // 排序筛选等待倒计时（秒）
            this.sortWaitCountdownTimer = null; // 排序筛选等待倒计时定时器
        }

        /**
         * 重写 Init 方法（external mode，不依赖容器）
         */
        Init() {
            // 初始化缓存管理器（如果还没有初始化）
            // 注意：父类 RankSystem.Init() 在 externalMode 时会提前返回，不会初始化缓存
            // 所以我们需要自己初始化缓存
            if (!this.cache) {
                this.cache = new IndexedDBCache("csgoj_rank", "logotable");
                this.logoCache = new IndexedDBCache("csgoj_rank", "logotable");
            }

            // external mode：不依赖容器ID
            this.externalMode = true;
            this.container = null;

            // 清理之前的状态（调用父类方法）
            this.Cleanup();

            // 从 window.BALLOON_QUEUE_CONFIG 读取配置（因为构造函数可能还没执行完）
            // 这样可以确保在 Init() 中也能正确判断 isBalloonManager 和 isBalloonSender
            const queueConfig = window.BALLOON_QUEUE_CONFIG || {};
            const isBalloonManager = queueConfig.is_balloon_manager || false;
            const isBalloonSender = isBalloonManager
                ? false
                : queueConfig.is_balloon_sender || false;

            // 如果构造函数已经初始化了这些属性，使用实例属性；否则使用配置中的值
            // 这样可以兼容构造函数执行前后的两种情况
            const finalIsBalloonManager =
                this.isBalloonManager !== undefined
                    ? this.isBalloonManager
                    : isBalloonManager;
            const finalIsBalloonSender =
                this.isBalloonSender !== undefined
                    ? this.isBalloonSender
                    : isBalloonSender;

            // 初始化标签页事件（如果是配送员）
            if (finalIsBalloonSender) {
                this.InitTabs();
            }

            // 初始化筛选器事件（如果是管理员）
            if (finalIsBalloonManager) {
                this.InitFilters();
                // 初始化自动打印功能
                this.InitAutoPrint();
            }

            // 绑定键盘事件（F5刷新数据）
            // 注意：父类 RankSystem.BindEvents() 在 externalMode 时不会执行
            // 所以我们需要自己绑定 keydown 事件
            document.addEventListener("keydown", (e) => this.HandleKeydown(e));

            // 先初始化缓存，然后加载数据
            // 注意：父类 RankSystem.Init() 在 externalMode 时会提前返回，不会执行这部分
            // 所以我们需要自己处理
            this.cache.init().then(() => {
                this.LoadData();
            });
        }

        /**
         * 初始化自动打印功能
         */
        InitAutoPrint() {
            // 获取比赛 ID（用于 localStorage key）
            const contestId = this.config.cid_list || "";
            const storageKeyPageSize = `${contestId}_balloon_print_page_size`;
            const storageKeyPerPage = `${contestId}_balloon_print_per_page`;
            const storageKeyCustomWidth = `${contestId}_balloon_print_custom_width`;
            const storageKeyCustomHeight = `${contestId}_balloon_print_custom_height`;

            // 初始化自动打印开关（参考 print_manager.js 的实现）
            const switchEl = document.getElementById("balloon-auto-print-box");
            if (switchEl && window.csgSwitch) {
                window.csgSwitch.initSwitch(switchEl, {
                    onChange: (checked) => {
                        this.OnAutoPrintToggle(checked);
                    },
                });
            }
            // 初始化页面尺寸选择（使用普通 select，避免 multiple-select 冲突）
            const pageSizeSelect = document.getElementById("balloon-print-page-size");
            if (pageSizeSelect) {
                // 从 localStorage 读取保存的值
                const savedPageSize = csg.store(storageKeyPageSize);
                if (savedPageSize) {
                    // 设置值（在绑定事件之前，避免触发 change 事件）
                    pageSizeSelect.value = savedPageSize;
                }

                // 监听尺寸变化
                pageSizeSelect.addEventListener("change", () => {
                    this.OnPageSizeChange();
                });
            }

            // 初始化自定义尺寸输入
            const customWidth = document.getElementById("balloon-print-custom-width");
            const customHeight = document.getElementById(
                "balloon-print-custom-height"
            );

            // 从 localStorage 读取保存的自定义尺寸
            if (customWidth) {
                const savedWidth = csg.store(storageKeyCustomWidth);
                if (savedWidth) {
                    customWidth.value = savedWidth;
                }
                customWidth.addEventListener("input", () => {
                    // 保存到 localStorage
                    csg.store(storageKeyCustomWidth, customWidth.value);
                    // 自定义尺寸变化时，重新计算最大数量（联动更新）
                    this.CalculateMaxPerPage();
                });
            }
            if (customHeight) {
                const savedHeight = csg.store(storageKeyCustomHeight);
                if (savedHeight) {
                    customHeight.value = savedHeight;
                }
                customHeight.addEventListener("input", () => {
                    // 保存到 localStorage
                    csg.store(storageKeyCustomHeight, customHeight.value);
                    // 自定义尺寸变化时，重新计算最大数量（联动更新）
                    this.CalculateMaxPerPage();
                });
            }

            // 初始化每页数量输入
            const perPageInput = document.getElementById("balloon-print-per-page");
            if (perPageInput) {
                // 从 localStorage 读取保存的值
                const savedPerPage = csg.store(storageKeyPerPage);
                if (savedPerPage) {
                    perPageInput.value = savedPerPage;
                }

                // 输入时实时验证
                perPageInput.addEventListener("input", () => {
                    this.OnPerPageChange();
                });
                // 失去焦点时验证并修正
                perPageInput.addEventListener("blur", () => {
                    this.OnPerPageChange();
                });
                // 键盘事件：Enter 键时验证
                perPageInput.addEventListener("keydown", (e) => {
                    if (e.key === "Enter") {
                        e.preventDefault();
                        this.OnPerPageChange();
                        perPageInput.blur();
                    }
                });
            }

            // 初始计算最大数量和显示状态
            // 使用 setTimeout 确保 multiple-select 已完全初始化，并且值已设置完成
            // 注意：这里只计算最大数量，不触发 change 事件（因为值已经在上面设置过了）
            setTimeout(() => {
                // 只计算最大数量，不保存到 localStorage（避免重复保存）
                this.CalculateMaxPerPage();
                // 验证并保存每页数量（确保值在有效范围内）
                this.OnPerPageChange();
            }, 100);
        }

        /**
         * 处理自动打印开关切换
         */
        OnAutoPrintToggle(checked) {
            this.autoPrintEnabled = checked;

            if (checked) {
                // 开启自动打印
                this.StartAutoPrint();
            } else {
                // 关闭自动打印
                this.StopAutoPrint();
            }
        }

        /**
         * 启动自动打印
         */
        StartAutoPrint() {
            this.StopAutoPrint(); // 先清除已有定时器

            // 设置全局变量，启用自动打印排序（必须在 LoadData 之前设置）
            window.AUTO_PRINT_SORT_ENABLED = true;

            // 重置倒计时相关状态
            this.autoPrintCountdown = 10;
            this.autoPrintRefreshTimer = null;

            // 重置已打印序号
            this.autoPrintLastIdx = 0;

            // 重置第一页打印完成的时间
            this.autoPrintPageFinishTime = null;
            
            // 🔥 重置可打印数量统计和刷新计数
            this.autoPrintPrintableCount = 0;
            this.autoPrintInsufficientRefreshCount = 0;
            
            // 🔥 清空当前视图，确保重新启动时使用最新数据
            this.sortedBalloonList = [];

            // 显示状态区域
            const countdownEl = document.getElementById("balloon-print-countdown");
            if (countdownEl) {
                countdownEl.style.display = "inline-block";
            }
            
            // 立即执行一次数据加载（按 bst、in_date 排序）
            // 数据加载完成后执行打印
            (async () => {
                try {
                    await this.LoadData();
                    // 数据加载完成后执行打印
                    this.DoAutoPrint();
                } catch (error) {
                    // 加载失败时停止自动打印
                    this.StopAutoPrint();
                    const switchInput = document.getElementById("balloon-auto-print-box");
                    if (switchInput && window.csgSwitch) {
                        // 使用正确的方法：setChecked（直接传入 input 元素）
                        window.csgSwitch.setChecked(switchInput, false);
                    }
                }
            })();
        }

        /**
         * 停止自动打印
         */
        StopAutoPrint() {
            if (this.autoPrintTimer) {
                clearTimeout(this.autoPrintTimer);
                this.autoPrintTimer = null;
            }

            // 🔥 清除倒计时定时器
            if (this.autoPrintCountdownTimer) {
                clearInterval(this.autoPrintCountdownTimer);
                this.autoPrintCountdownTimer = null;
            }
            
            // 🔥 清除刷新定时器
            if (this.autoPrintRefreshTimer) {
                clearTimeout(this.autoPrintRefreshTimer);
                this.autoPrintRefreshTimer = null;
            }
            
            // 🔥 清除状态更新等待定时器
            if (this.statusChangeWaitTimer) {
                clearTimeout(this.statusChangeWaitTimer);
                this.statusChangeWaitTimer = null;
            }
            this.HideStatusChangeWaitCountdown();
            
            // 🔥 清除排序筛选等待定时器
            this.HideSortWaitCountdown();

            // 清除全局变量，禁用自动打印排序
            window.AUTO_PRINT_SORT_ENABLED = false;

            // 隐藏倒计时
            const countdownEl = document.getElementById("balloon-print-countdown");
            if (countdownEl) {
                countdownEl.style.display = "none";
            }

            this.autoPrintCountdown = 10;
            this.autoPrintWaitStartTime = null;
            this.autoPrintPageFinishTime = null;
            
            // 🔥 清理可打印数量统计和刷新计数
            this.autoPrintPrintableCount = 0;
            this.autoPrintInsufficientRefreshCount = 0;
            
            // 🔥 清空当前视图，确保下次启动时使用最新数据
            this.sortedBalloonList = [];
        }

        /**
         * 检查并刷新数据（优化：只在数量不足时使用 setTimeout 刷新）
         * 核心原则：
         * - 如果可打印数量 >= 用户设置的打印数量（如 A4 打印 9 张），不显示倒计时，显示"正在打印"
         * - 如果可打印数量 < 用户设置的打印数量，显示倒计时，10秒后刷新
         */
        CheckAndRefreshForAutoPrint() {
            // 🔥 清除之前的刷新定时器
            if (this.autoPrintRefreshTimer) {
                clearTimeout(this.autoPrintRefreshTimer);
                this.autoPrintRefreshTimer = null;
            }
            
            // 获取用户设置的打印数量（如 A4 打印 9 张）
            const perPageInput = document.getElementById("balloon-print-per-page");
            const userPrintCount = parseInt(perPageInput?.value || 1);
            
            // 判断是否需要刷新（可打印数量 < 用户设置的打印数量时）
            const needsRefresh = (this.autoPrintPrintableCount === 0) || 
                                (this.autoPrintPrintableCount > 0 && this.autoPrintPrintableCount < userPrintCount);
            
            if (needsRefresh) {
                // 数量不足，显示倒计时，10秒后刷新
                this.autoPrintCountdown = 10;
                this.UpdateAutoPrintCountdown();
                
                // 启动倒计时显示
                if (this.autoPrintCountdownTimer) {
                    clearInterval(this.autoPrintCountdownTimer);
                }
                this.autoPrintCountdownTimer = setInterval(() => {
                    this.autoPrintCountdown--;
                    this.UpdateAutoPrintCountdown();
                    if (this.autoPrintCountdown <= 0) {
                        clearInterval(this.autoPrintCountdownTimer);
                        this.autoPrintCountdownTimer = null;
                        // 刷新数据（RefreshData 会清空 sortedBalloonList，DoAutoPrint 会重新获取）
                        this.RefreshData().then(() => {
                            this.DoAutoPrint();
                        });
                    }
                }, 1000);
            } else {
                // 可打印数量 >= 用户设置的打印数量，显示"正在打印"，不显示倒计时
                this.ShowPrintingStatus();
                // 🔥 继续打印下一批
                this.DoAutoPrint();
            }
        }

        /**
         * 显示"正在打印"状态
         */
        ShowPrintingStatus() {
            const countdownTextEl = document.getElementById("balloon-print-countdown-text");
            if (countdownTextEl) {
                // 显示"正在打印"的文本（中英文）
                countdownTextEl.textContent = "打印中...";
                countdownTextEl.setAttribute("data-en-text", "Printing...");
            }
            
            // 清除倒计时定时器
            if (this.autoPrintCountdownTimer) {
                clearInterval(this.autoPrintCountdownTimer);
                this.autoPrintCountdownTimer = null;
            }
        }

        /**
         * 检查并提示过滤条件（除房间外）
         */
        CheckAndNotifyFilters() {
            // 检查是否有除房间外的过滤条件
            const hasNonRoomFilters =
                (this.filters.status && this.filters.status.length > 0) ||
                this.filters.balloon_sender !== null ||
                (this.filters.schools && this.filters.schools.length > 0) ||
                (this.filters.problems && this.filters.problems.length > 0) ||
                (this.filters.searchText && this.filters.searchText.trim() !== "");

            if (!hasNonRoomFilters) {
                return; // 没有过滤条件，不提示
            }

            // 检查 localStorage，避免重复提示
            const contestId = this.config.cid_list || "";
            const storageKey = `${contestId}_balloon_print_filter_notified`;
            const hasNotified = csg.store(storageKey);

            if (hasNotified) {
                return; // 已经提示过，不再提示
            }

            // 提示用户
            alerty.notify({
                message: "当前筛选条件可能导致没有可打印的气球，请检查筛选设置",
                message_en:
                    "Current filter conditions may result in no printable balloons, please check filter settings",
                type: "warning",
                duration: 5000,
            });

            // 记录到 localStorage
            csg.store(storageKey, true);
        }

        /**
         * 更新自动打印倒计时显示
         */
        UpdateAutoPrintCountdown() {
            const countdownTextEl = document.getElementById(
                "balloon-print-countdown-text"
            );
            if (countdownTextEl) {
                countdownTextEl.textContent = this.autoPrintCountdown;
                // 清除"正在打印"的标记
                countdownTextEl.removeAttribute("data-en-text");
            }
        }

        /**
         * 处理页面尺寸变化
         */
        OnPageSizeChange() {
            const pageSizeSelect = document.getElementById("balloon-print-page-size");
            const customWidthGroup = document.getElementById(
                "balloon-print-custom-size-group"
            );
            const customHeightGroup = document.getElementById(
                "balloon-print-custom-size-group2"
            );

            if (!pageSizeSelect) return;

            // 获取比赛 ID（用于 localStorage key）
            const contestId = this.config.cid_list || "";
            const storageKeyPageSize = `${contestId}_balloon_print_page_size`;

            // 使用普通 select 的 value 属性
            const selectedValue = pageSizeSelect.value;

            // 保存到 localStorage
            if (selectedValue) {
                csg.store(storageKeyPageSize, selectedValue);
            }

            // 显示/隐藏自定义尺寸输入
            if (selectedValue === "custom") {
                // 显示自定义尺寸输入框
                if (customWidthGroup) {
                    customWidthGroup.style.display = "flex";
                    customWidthGroup.classList.add("show");
                }
                if (customHeightGroup) {
                    customHeightGroup.style.display = "flex";
                    customHeightGroup.classList.add("show");
                }
            } else {
                // 隐藏自定义尺寸输入框
                if (customWidthGroup) {
                    customWidthGroup.style.display = "none";
                    customWidthGroup.classList.remove("show");
                }
                if (customHeightGroup) {
                    customHeightGroup.style.display = "none";
                    customHeightGroup.classList.remove("show");
                }
            }

            // 计算最大数量（纸张尺寸变化时联动更新）
            this.CalculateMaxPerPage();
        }

        /**
         * 处理每页数量变化
         */
        OnPerPageChange() {
            const perPageInput = document.getElementById("balloon-print-per-page");
            const maxCount = parseInt(
                document.getElementById("balloon-print-max-count")?.textContent || "1"
            );

            if (perPageInput) {
                let value = parseInt(perPageInput.value);

                // 如果输入为空或无效，设为1
                if (isNaN(value) || value === "") {
                    value = 1;
                }

                // 限制在有效范围内
                if (value > maxCount) {
                    value = maxCount;
                }
                if (value < 1) {
                    value = 1;
                }

                // 更新输入框值
                perPageInput.value = value;

                // 保存到 localStorage
                const contestId = this.config.cid_list || "";
                const storageKeyPerPage = `${contestId}_balloon_print_per_page`;
                csg.store(storageKeyPerPage, value.toString());

                // 添加视觉反馈：如果值被修正，短暂高亮
                if (
                    parseInt(perPageInput.value) !==
                    parseInt(perPageInput.getAttribute("data-last-value") || "1")
                ) {
                    perPageInput.style.backgroundColor = "#fff3cd";
                    setTimeout(() => {
                        perPageInput.style.backgroundColor = "";
                    }, 500);
                }

                // 记录当前值
                perPageInput.setAttribute("data-last-value", value);
            }
        }

        /**
         * 计算每页最大数量（根据纸张尺寸）
         */
        CalculateMaxPerPage() {
            const pageSizeSelect = document.getElementById("balloon-print-page-size");
            const customWidth = document.getElementById("balloon-print-custom-width");
            const customHeight = document.getElementById(
                "balloon-print-custom-height"
            );
            const maxCountEl = document.getElementById("balloon-print-max-count");

            if (!pageSizeSelect || !maxCountEl) return;

            let width, height;
            // 使用普通 select 的 value 属性
            const selectedValue = pageSizeSelect.value;

            if (selectedValue === "custom") {
                width = parseFloat(customWidth?.value || 58);
                height = parseFloat(customHeight?.value || 80);
            } else {
                const parts = selectedValue.split("x");
                width = parseFloat(parts[0]) || 58;
                height = parseFloat(parts[1]) || 80;
            }

            // 每个小票需要的最小尺寸（估算：58mm x 80mm）
            const minTicketWidth = 58;
            const minTicketHeight = 80;

            // 计算可以放置的数量（横向和纵向）
            const cols = Math.floor(width / minTicketWidth);
            const rows = Math.floor(height / minTicketHeight);
            const maxCount = Math.max(1, cols * rows);

            // 更新最大数量显示
            maxCountEl.textContent = maxCount;

            // 更新每页数量输入的最大值（联动更新）
            const perPageInput = document.getElementById("balloon-print-per-page");
            if (perPageInput) {
                // 更新 max 属性（HTML 属性和 JavaScript 属性）
                perPageInput.setAttribute("max", maxCount);
                perPageInput.max = maxCount;

                // 获取当前值
                const currentValue = parseInt(perPageInput.value) || 1;

                // 如果当前值超过新的最大值，自动调整为最大值
                if (currentValue > maxCount) {
                    perPageInput.value = maxCount;
                    // 触发验证，确保值正确
                    this.OnPerPageChange();
                } else if (currentValue < 1) {
                    // 如果当前值小于1，设为1
                    perPageInput.value = 1;
                }

                // 如果当前值为空或无效，设为1
                if (!perPageInput.value || isNaN(parseInt(perPageInput.value))) {
                    perPageInput.value = 1;
                }
            }
        }
        TableLoadData(data) {
            if(!Array.isArray(data)) {
                console.warn('TableLoadData data is not an array');
            }
            
            const table = $("#balloon-queue-table");
            if (!table.length) return;

            // 如果表格已经初始化，直接加载数据
            if (table.data("bootstrap.table")) {
                table.bootstrapTable("load", data);
            } else {
                // 首次初始化表格（列定义在模板的thead中）
                table.bootstrapTable({
                    data: data,
                    uniqueId: "solution_id", // 设置唯一ID，用于 updateByUniqueId
                });
            }
            
        }
        /**
         * 执行自动打印
         */
        DoAutoPrint() {
            if (!this.autoPrintEnabled) return;

            // 获取用户设置的打印数量（如 A4 打印 9 张）
            const perPageInput = document.getElementById("balloon-print-per-page");
            const userPrintCount = parseInt(perPageInput?.value || 1);

            // 获取表格每页大小（bootstrap-table 的 pageSize，如 25 条）
            const tablePageSize = this.GetTablePageSize();

            // 1. 获取当前视图（维护的排序筛选后的数据，bst=0 在前）
            // 🔥 如果当前视图不存在，才执行 GetSortedBalloonListForPrint
            let sortedList = this.sortedBalloonList;
            if (!sortedList || sortedList.length === 0) {
                sortedList = this.GetSortedBalloonListForPrint();
                this.TableLoadData(sortedList);
                
                this.sortedBalloonList = sortedList; // 保存当前视图
            }
            
            // 2. 判断第一页是否有可打内容（前 tablePageSize 个中是否有 bst=0/空/不存在）
            // 注意：这里的"第一页"是指 bootstrap-table 的一页大小，不是用户设置的打印数量
            let hasPrintableInFirstPage = false;
            for (let i = 0; i < Math.min(tablePageSize, sortedList.length); i++) {
                const bst = Number(sortedList[i].bst) || 0;
                if (bst === 0) {
                    hasPrintableInFirstPage = true;
                    break;
                }
            }
            
            // 🔥 如果第一页没有可打内容，才重新排序筛选
            if (!hasPrintableInFirstPage) {
                sortedList = this.GetSortedBalloonListForPrint(); // 这里会更新 autoPrintPrintableCount
                this.sortedBalloonList = sortedList; // 更新当前视图
                
                // 🔥 避免打印过快：排序筛选后等待 3 秒，并显示倒计时
                this.ShowSortWaitCountdown();
                
                setTimeout(() => {
                    // 重新判断第一页
                    hasPrintableInFirstPage = false;
                    for (let i = 0; i < Math.min(tablePageSize, sortedList.length); i++) {
                        const bst = Number(sortedList[i].bst) || 0;
                        if (bst === 0) {
                            hasPrintableInFirstPage = true;
                            break;
                        }
                    }
                    this.HideSortWaitCountdown();
                    
                    // 如果重新排序筛选后第一页还是没有可打内容，等待刷新
                    if (!hasPrintableInFirstPage) {
                        this.CheckAndRefreshForAutoPrint();
                        return;
                    }
                    
                    // 继续执行后续逻辑
                    this.ContinueAutoPrint(sortedList, userPrintCount);
                }, 3000);
                return;
            }
            
            // 继续执行后续逻辑（使用当前视图）
            this.ContinueAutoPrint(sortedList, userPrintCount);
        }

        /**
         * 继续执行自动打印逻辑（提取公共逻辑）
         * @param {Array} sortedList - 排序后的气球列表
         * @param {Number} userPrintCount - 用户设置的打印数量（如 A4 打印 9 张）
         */
        ContinueAutoPrint(sortedList, userPrintCount) {
            // 3. 统计可打印数量（bst=0 的数量）
            // 注意：GetSortedBalloonListForPrint 已经统计了，这里直接使用统计值
            // 如果统计值未初始化，则手动统计
            if (this.autoPrintPrintableCount === undefined || this.autoPrintPrintableCount === null) {
                this.autoPrintPrintableCount = this.CountPrintableItems(sortedList);
            }
            
            // 4. 判断可打印数量（与用户设置的打印数量比较，如 A4 打印 9 张）
            if (this.autoPrintPrintableCount === 0) {
                // 情况C：完全没有可打印数据
                this.CheckAndNotifyFilters();
                this.CheckAndRefreshForAutoPrint(); // 显示倒计时并刷新
                return;
            } else if (this.autoPrintPrintableCount >= userPrintCount) {
                // 情况A：可打印数量 >= 用户设置的打印数量，直接打印（不需要从后台加载）
                this.autoPrintInsufficientRefreshCount = 0; // 重置刷新计数
                this.ShowPrintingStatus(); // 显示"正在打印"
                this.ExecutePrint(sortedList, userPrintCount);
            } else {
                // 情况B：有数据但数量不足（0 < 可打印数量 < 用户设置的打印数量）
                // 从后台加载数据确认是否真的不足
                this.RefreshData().then(() => {
                    // 加载后重新获取视图（GetSortedBalloonListForPrint 会自动更新 autoPrintPrintableCount）
                    const refreshedList = this.GetSortedBalloonListForPrint();
                    this.sortedBalloonList = refreshedList; // 🔥 更新当前视图
                    
                    if (this.autoPrintPrintableCount >= userPrintCount) {
                        // 加载后可打印数量 >= 用户设置的打印数量，重置刷新计数，打印
                        this.autoPrintInsufficientRefreshCount = 0;
                        this.ShowPrintingStatus(); // 显示"正在打印"
                        this.ExecutePrint(refreshedList, userPrintCount);
                    } else if (this.autoPrintPrintableCount > 0) {
                        // 加载后还是不足（但 > 0），刷新计数 +1
                        this.autoPrintInsufficientRefreshCount++;
                        
                        if (this.autoPrintInsufficientRefreshCount >= 2) {
                            // 刷新2次后还是不足，打印现有的（即使不足）
                            this.autoPrintInsufficientRefreshCount = 0; // 重置计数
                            this.ShowPrintingStatus(); // 显示"正在打印"
                            this.ExecutePrint(refreshedList, userPrintCount);
                        } else {
                            // 刷新计数 < 2，等待刷新
                            this.CheckAndRefreshForAutoPrint();
                        }
                    } else {
                        // 加载后完全没有可打印数据（= 0），重置刷新计数，等待刷新
                        this.autoPrintInsufficientRefreshCount = 0;
                        this.CheckAndRefreshForAutoPrint();
                    }
                });
            }
        }

        /**
         * 执行打印逻辑（提取公共逻辑）
         * @param {Array} sortedList - 排序后的气球列表（包含所有符合筛选条件的数据）
         * @param {Number} perPage - 每页数量
         */
        ExecutePrint(sortedList, perPage) {
            // 🔥 使用 for 循环收集 bst=0 的气球，数量够了就 break
            const balloonsToPrint = [];
            for (let i = 0; i < sortedList.length; i++) {
                const bst = Number(sortedList[i].bst) || 0;
                if (bst === 0) {
                    balloonsToPrint.push(sortedList[i]);
                    if (balloonsToPrint.length >= perPage) {
                        break; // 数量够了就 break
                    }
                }
            }
            
            if (balloonsToPrint.length === 0) {
                // 没有可打印的气球，检查刷新逻辑
                this.CheckAndRefreshForAutoPrint();
                return;
            }

            // 获取当前打印的最大序号（idx）
            const maxIdx = Math.max(
                ...balloonsToPrint.map((b) => b.idx || 0),
                this.autoPrintLastIdx
            );

            // 获取表格分页大小（如果无法获取，默认使用25）
            let pageSize = 25;
            try {
                const $table = $("#balloon-queue-table");
                if ($table.length && $table.data("bootstrap.table")) {
                    const tableOptions = $table.bootstrapTable("getOptions");
                    pageSize = tableOptions.pageSize || 25;
                }
            } catch (e) {
                // 使用默认值25
            }

            // 调用打印函数
            if (
                window.BalloonPrint &&
                typeof window.BalloonPrint.printBalloons === "function"
            ) {
                // 如果数量不足，需要填充空数据到 perPage 数量
                const balloonsToPrintWithPadding =
                    balloonsToPrint.length < perPage
                        ? [
                            ...balloonsToPrint,
                            ...Array(perPage - balloonsToPrint.length).fill(null),
                        ]
                        : balloonsToPrint;

                window.BalloonPrint.printBalloons(
                    balloonsToPrintWithPadding,
                    perPage,
                    (printError, printResult) => {
                        // 如果打印失败，不执行状态更新，并立即取消自动打印
                        if (printError) {
                            // 显示合并的失败通知
                            const errorMsg = printError.message || printError;
                            const balloonCount = balloonsToPrint.length;
                            alerty.alert({
                                title:
                                    '自动打印失败<span class="en-text">Auto Print Failed</span>',
                                message: `共 ${balloonCount} 个气球打印失败<br/><br/>错误: ${errorMsg}<br/><br/>自动打印已停止`,
                                message_en: `${balloonCount} balloons failed to print<br/><br/>Error: ${errorMsg}<br/><br/>Auto print stopped`,
                                width: "500px",
                            });
                            // 立即停止自动打印
                            this.StopAutoPrint();
                            // 关闭自动打印开关
                            const switchInput = document.getElementById(
                                "balloon-auto-print-box"
                            );
                            if (switchInput && window.csgSwitch) {
                                window.csgSwitch.setChecked(switchInput, false);
                            }
                            return;
                        }

                        // 打印成功后，先更新状态
                        const solutionIds = balloonsToPrint
                            .map((b) => b.solution_id)
                            .filter((id) => id);
                        
                        // 🔥 立即更新数据源，避免重复打印
                        solutionIds.forEach(solutionId => {
                            this.UpdateBalloonItem(solutionId, { bst: 10 }, false);
                        });
                        
                        // 🔥 立即更新数据源，避免重复打印
                        // 注意：UpdateBalloonItem 会更新数据源，sortedBalloonList 中的引用也会自动更新
                        // 所以这些项的 bst 会从 0 变成 10，但项仍然在 sortedBalloonList 中
                        // 由于排序后 bst=0 的项会排在前面，如果第一页中还有可打印的项，它们应该还在第一页
                        
                        // 🔥 更新可打印数量统计（减去已打印的数量）
                        // this.autoPrintPrintableCount -= balloonsToPrint.length; // update balloon 已经减了
                        
                        if (this.autoPrintPrintableCount < 0) {
                            this.autoPrintPrintableCount = 0;
                        }
                        
                        const updatedBalloons = this.balloonList.filter((item) =>
                            solutionIds.includes(item.solution_id)
                        );

                        // 更新状态（异步，后端确认）
                        const updatePromise =
                            updatedBalloons.length > 0
                                ? this.UpdateBalloonsStatus(updatedBalloons, 10)
                                : Promise.resolve();

                        updatePromise
                            .then(() => {
                                // 更新已打印的最大序号
                                this.autoPrintLastIdx = maxIdx;

                                // 记录第一页打印完成的时间
                                if (balloonsToPrint.length > 0) {
                                    this.autoPrintPageFinishTime = Date.now();
                                }

                                // 🔥 打印完成后，检查第一页是否还有可打印内容
                                // 如果第一页没有可打印内容，需要重新排序获取新视图
                                const tablePageSize = this.GetTablePageSize();
                                let hasPrintableInFirstPage = false;
                                if (this.sortedBalloonList && this.sortedBalloonList.length > 0) {
                                    for (let i = 0; i < Math.min(tablePageSize, this.sortedBalloonList.length); i++) {
                                        const bst = Number(this.sortedBalloonList[i].bst) || 0;
                                        if (bst === 0) {
                                            hasPrintableInFirstPage = true;
                                            break;
                                        }
                                    }
                                }
                                
                                // 如果第一页没有可打印内容，清空当前视图，强制重新排序
                                if (!hasPrintableInFirstPage) {
                                    this.sortedBalloonList = [];
                                }
                                
                                // 检查是否需要刷新（根据可打印数量决定）
                                this.CheckAndRefreshForAutoPrint();
                            })
                            .catch((updateError) => {
                                // 更新状态或加载数据失败，显示错误通知
                                const balloonCount = balloonsToPrint.length;
                                alerty.alert({
                                    title: '部分失败<span class="en-text">Partial Failure</span>',
                                    message: `共 ${balloonCount} 个气球打印成功，但更新状态或加载数据失败: ${updateError.message || updateError
                                        }<br/>请手动检查`,
                                    message_en: `${balloonCount} balloons printed successfully, but status update or data load failed: ${updateError.message || updateError
                                        }<br/>Please check manually`,
                                    width: "500px",
                                });
                                // 即使失败，也继续尝试打印（不中断自动打印流程）
                                this.DoAutoPrint();
                            });
                    },
                );
            }
        }

        /**
         * 获取表格每页大小（bootstrap-table 的 pageSize）
         * @returns {Number} 表格每页大小，默认 25
         */
        GetTablePageSize() {
            let tablePageSize = 25;
            try {
                const $table = $("#balloon-queue-table");
                if ($table.length && $table.data("bootstrap.table")) {
                    const tableOptions = $table.bootstrapTable("getOptions");
                    tablePageSize = tableOptions.pageSize || 25;
                }
            } catch (e) {
                // 使用默认值25
            }
            return tablePageSize;
        }

        /**
         * 统计可打印数量（bst=0 的数量）
         * @param {Array} sortedList - 排序后的列表
         * @returns {Number} 可打印数量
         */
        CountPrintableItems(sortedList) {
            let count = 0;
            
            for (let i = 0; i < sortedList.length; i++) {
                const bst = Number(sortedList[i].bst) || 0;
                if (bst === 0) {
                    count++;
                }
            }
            return count;
        }

        /**
         * 获取排序后的气球列表（用于自动打印）
         * 排序规则：状态优先（bst=0在前），状态相同按时间排序
         * 🔥 只使用用户筛选条件，不额外过滤 bst=0，保持用户视图一致性
         * 🔥 同时统计可打印数量（bst=0 的数量）
         */
        GetSortedBalloonListForPrint() {
            // 🔥 从数据源 balloonList 获取，而不是从表格
            if (!this.balloonList || this.balloonList.length === 0) {
                this.autoPrintPrintableCount = 0;
                return [];
            }
            
            // 🔥 使用统一的筛选逻辑（只使用用户筛选条件，不额外过滤 bst=0）
            let filtered = this.GetFilteredBalloonList();
            
            // 排序：先按 bst 排序（bst=0 在前），然后按时间升序（早的在前）
            const sorted = filtered.sort((a, b) => {
                const bstA = Number(a.bst) || 0;
                const bstB = Number(b.bst) || 0;
                
                // 第一优先级：bst（bst=0 在前）
                if (bstA !== bstB) {
                    return bstA - bstB;
                }
                
                // 第二优先级：时间（早的在前）
                const timeA = new Date(a.in_date).getTime();
                const timeB = new Date(b.in_date).getTime();
                if (isNaN(timeA) || isNaN(timeB)) {
                    return 0;
                }
                return timeA - timeB;
            });
            
            // 🔥 统计可打印数量（bst=0 的数量）
            this.autoPrintPrintableCount = this.CountPrintableItems(sorted);
            
            return sorted;
        }

        /**
         * 打印单个气球的小票
         * @param {Object} row - 表格行数据
         */
        PrintSingleBalloon(row) {
            if (!row) {
                alerty.error({
                    message: "无法获取气球信息",
                    message_en: "Unable to get balloon information",
                });
                return;
            }

            // 检查打印功能是否可用
            if (
                !window.BalloonPrint ||
                typeof window.BalloonPrint.printBalloons !== "function"
            ) {
                alerty.error({
                    message: "打印功能未初始化，请刷新页面重试",
                    message_en:
                        "Print function not initialized, please refresh the page and try again",
                });
                return;
            }

            // 从 row 构建打印所需的气球对象
            // 打印函数需要的字段：team_id, team (包含 room), problem_alphabet, is_global_fb, is_regular_fb
            const balloon = {
                team_id: row.team_id || "",
                team: row.team || { room: row.room || "" },
                problem_alphabet: row.problem_alphabet || "",
                is_global_fb: row.is_global_fb || false,
                is_regular_fb: row.is_regular_fb || false,
                solution_id: row.solution_id,
                bst: row.bst,
            };

            // 调用打印函数
            window.BalloonPrint.printBalloons([balloon], 1, () => {
                // 打印成功后的回调
                // 如果当前状态是 0（未处理），自动更新为 10（已通知）
                if (row.bst === 0) {
                    // ChangeBalloonStatus 内部会显示成功/失败消息，这里只显示打印成功消息
                    this.ChangeBalloonStatus(row, 10, null, "");
                } else {
                    alerty.success({
                        message: "小票打印成功",
                        message_en: "Ticket printed successfully",
                    });
                }
            });
        }

        /**
         * 更新气球状态（批量）
         */
        async UpdateBalloonsStatus(balloons, newStatus) {
            for (const balloon of balloons) {
                try {
                    // 🔥 先更新数据源（立即生效，避免重复打印）
                    // 注意：ChangeBalloonStatus 会再次调用 UpdateBalloonItem，但这里先更新可以避免重复打印
                    // 如果数据源已经更新过（bst=10），这里可以跳过，避免重复更新
                    if (balloon.bst !== newStatus) {
                        this.UpdateBalloonItem(balloon.solution_id, { bst: newStatus }, false);
                    }
                    
                    // 然后调用后端更新（会再次调用 UpdateBalloonItem 更新视图）
                    await this.ChangeBalloonStatus(balloon, newStatus, null, "", false);
                } catch (error) {
                    // 更新气球状态失败，静默处理
                }
            }

            // 只需要更新统计（如果批量更新时统计没更新）
            this.UpdateQueueStats();
            // 不再需要全量刷新，因为 ChangeBalloonStatus 已经用 updateByUniqueId 更新了
        }

        /**
         * 初始化标签页事件
         */
        InitTabs() {
            const tabQueue = document.getElementById("tab-queue");
            const tabMy = document.getElementById("tab-my-balloons");

            // 设置初始标签页状态
            if (tabQueue && tabQueue.classList.contains("active")) {
                this.currentTab = "queue";
            } else if (tabMy && tabMy.classList.contains("active")) {
                this.currentTab = "my_balloons";
            }

            // 监听标签页切换事件
            if (tabQueue) {
                tabQueue.addEventListener("shown.bs.tab", () => {
                    this.SwitchTab("queue");
                });
            }

            if (tabMy) {
                tabMy.addEventListener("shown.bs.tab", () => {
                    this.SwitchTab("my_balloons");
                });
            }
        }

        /**
         * 从 localStorage 加载筛选条件
         */
        LoadFiltersFromStorage() {
            if (!this.contestId) return this.getDefaultFilters();

            const baseKey = `${this.contestId}_balloon_filter`;
            const savedStatus = csg.store(`${baseKey}_status`);
            const savedSender = csg.store(`${baseKey}_balloon_sender`);
            const savedRooms = csg.store(`${baseKey}_rooms`);
            const savedSchools = csg.store(`${baseKey}_schools`);
            const savedProblems = csg.store(`${baseKey}_problems`);
            const savedSearchText = csg.store(`${baseKey}_searchText`);

            return {
                status: Array.isArray(savedStatus) ? savedStatus : [],
                balloon_sender: savedSender || null,
                rooms: Array.isArray(savedRooms) ? savedRooms : [],
                schools: Array.isArray(savedSchools) ? savedSchools : [],
                problems: Array.isArray(savedProblems) ? savedProblems : [],
                searchText: typeof savedSearchText === "string" ? savedSearchText : "",
            };
        }

        /**
         * 保存筛选条件到 localStorage
         */
        SaveFiltersToStorage() {
            if (!this.contestId || !this.filters) return;

            const baseKey = `${this.contestId}_balloon_filter`;
            csg.store(`${baseKey}_status`, this.filters.status);
            csg.store(`${baseKey}_balloon_sender`, this.filters.balloon_sender);
            csg.store(`${baseKey}_rooms`, this.filters.rooms);
            csg.store(`${baseKey}_schools`, this.filters.schools);
            csg.store(`${baseKey}_problems`, this.filters.problems);
            csg.store(`${baseKey}_searchText`, this.filters.searchText);
        }

        /**
         * 获取默认筛选条件
         */
        getDefaultFilters() {
            return {
                status: [],
                balloon_sender: null,
                rooms: [],
                schools: [],
                problems: [],
                searchText: "",
            };
        }

        /**
         * 初始化筛选器事件
         */
        InitFilters() {
            // 注意：multiple-select 的事件绑定需要在插件初始化时通过回调函数设置
            // 原生 change 事件可能不会正确触发，所以我们在 UpdateFilters 中初始化时绑定

            // 绑定搜索框的输入事件
            const searchInput = document.getElementById("filter-search");
            if (searchInput) {
                // 使用 input 事件支持实时搜索，debounce 避免频繁触发
                let searchTimeout = null;
                searchInput.addEventListener("input", (e) => {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        this.OnSearchChange(e.target.value);
                    }, 300); // 300ms 延迟
                });
            }

            // 绑定清空按钮事件
            const clearButton = document.getElementById("balloon-filter-clear");
            if (clearButton) {
                clearButton.addEventListener("click", () => {
                    this.ClearAllFilters();
                });
            }
        }

        /**
         * 重写 ShowLoading 方法
         */
        ShowLoading() {
            if (this.externalMode) return;
            const loadingEl = document.getElementById("balloon-queue-loading");
            if (loadingEl) {
                loadingEl.style.display = "block";
            }
        }

        /**
         * 重写 HideLoading 方法
         */
        HideLoading() {
            if (this.externalMode) return;
            const loadingEl = document.getElementById("balloon-queue-loading");
            if (loadingEl) {
                loadingEl.style.display = "none";
            }
        }

        /**
         * 切换标签页
         */
        SwitchTab(tab) {
            this.currentTab = tab;

            // 重新渲染列表
            this.RenderQueueList();
        }

        /**
         * 重写 OriInit 方法（数据加载完成后调用）
         */
        OriInit(raw_data) {
            this.data = raw_data;
            // 将 list 格式转换为 dict 格式（父类方法）
            this.ConvertListToDict();
            // 处理数据
            this.ProcessData();
            // 隐藏加载提示
            this.HideLoading();
            this.isInitialLoad = false;
        }

        /**
         * 重写 ProcessData 方法，处理队列数据
         */
        ProcessData(flg_real_rank = false) {
            // 调用父类方法处理数据（会过滤AC、构建balloonMap等）
            super.ProcessData(flg_real_rank);

            // 构建气球列表
            this.BuildBalloonList();

            // 检查全局变量，如果启用自动打印排序，则按 bst、in_date 排序（都是 asc）
            if (window.AUTO_PRINT_SORT_ENABLED) {
                this.SortBalloonListForAutoPrint();
            } else if (this.autoPrintEnabled) {
                // 如果只是自动打印开启但未启用排序，使用原来的排序逻辑
                this.SortBalloonListForAutoPrint();
            }

            // 提取rooms和senders
            this.ExtractRoomsAndSenders();

            // 更新筛选器
            this.UpdateFilters();

            // 渲染队列列表
            this.RenderQueueList();

            // 更新统计
            this.UpdateQueueStats();
        }

        /**
         * 为自动打印排序气球列表
         * 排序规则：bst 优先（asc），相同 bst 按 in_date 排序（asc）
         */
        SortBalloonListForAutoPrint() {
            if (!this.balloonList || this.balloonList.length === 0) {
                return;
            }

            this.balloonList.sort((a, b) => {
                // 确保 bst 是数字类型
                const bstA = Number(a.bst) || 0;
                const bstB = Number(b.bst) || 0;

                // 第一优先级：bst（升序）
                if (bstA !== bstB) {
                    return bstA - bstB;
                }

                // 第二优先级：in_date（升序，早的在前）
                const timeA = new Date(a.in_date).getTime();
                const timeB = new Date(b.in_date).getTime();
                if (isNaN(timeA) || isNaN(timeB)) {
                    return 0;
                }
                return timeA - timeB;
            });
        }

        /**
         * 重排序气球列表（用于打印完成后更新排序）
         */
        ReSortBalloonList() {
            if (window.AUTO_PRINT_SORT_ENABLED) {
                this.SortBalloonListForAutoPrint();
                // 重新渲染表格
                this.RenderQueueList();
            }
        }

        /**
         * 构建气球列表（包含所有需要的字段，避免在渲染时重复计算）
         */
        IsFrozen(solution) {
            // 重写 frozen 规则，以便看出封榜后的气球
            const inDate = solution.in_date;
            const submitTime = new Date(inDate).getTime();
            const endTime = new Date(this.data.contest.end_time).getTime();
            const frozenMinutes = this.data.contest.frozen_minute || 0;
            const frozenAfter = this.data.contest.frozen_after || 0;
            const frozenStartTime = endTime - frozenMinutes * 60 * 1000;
            const frozenEndTime = endTime + frozenAfter * 60 * 1000;
            // 不在封榜时间内的提交
            if (submitTime <= frozenStartTime) {
                return false;
            }
            // 在封榜期间，且当前时间仍在封榜或揭晓期间内
            const now = this.GetActualCurrentTime().getTime();
            return frozenStartTime <= now && now <= frozenEndTime;
        }
        BuildBalloonList() {
            this.balloonList = [];

            if (
                !this.data ||
                !this.data.solution ||
                !this.data.team ||
                !this.data.problem
            )
                return;

            // 构建team和problem映射（数据已经是dict格式，由父类ConvertListToDict转换）
            const teamMap = {};
            this.data.team.forEach((team) => {
                // 数据已经是dict格式，使用team_id作为key
                teamMap[team.team_id] = team;
            });

            const problemMap = {};
            this.data.problem.forEach((prob) => {
                problemMap[prob.problem_id] = prob;
            });

            // 遍历所有AC提交（父类ProcessData已经过滤为AC），构建气球列表
            this.data.solution.forEach((solution) => {
                // 数据已经是dict格式
                const teamId = solution.team_id;
                const problemId = solution.problem_id;
                const key = `${teamId}_${problemId}`;

                // 获取气球状态（确保是数字类型）
                const balloon = this.balloonMap.get(key);
                const bst = balloon ? Number(balloon.bst) || 0 : 0;
                const pst = balloon ? Number(balloon.pst) || 0 : 0;
                const balloonSender = balloon ? balloon.balloon_sender || "" : "";

                // 获取队伍和题目信息
                const team = teamMap[teamId];
                const problem = problemMap[problemId];

                if (!team || !problem) return;

                // 计算首答信息（map_fb 已在父类 ProcessData 中填充）
                const regularFB = this.map_fb?.regular?.[problemId];
                const globalFB = this.map_fb?.global?.[problemId];
                const isRegularFB = regularFB && regularFB.team_id === teamId;
                const isGlobalFB = globalFB && globalFB.team_id === teamId;

                // 构建气球项（包含所有需要的字段）
                const balloonItem = {
                    solution_id: solution.solution_id,
                    team_id: teamId,
                    problem_id: problemId,
                    bst: bst,
                    pst: pst,
                    balloon_sender: balloonSender,
                    in_date: solution.in_date,
                    team: team,
                    problem: problem,
                    flg_frozen: this.IsFrozen(solution),
                    // 预计算的字段，避免在渲染时重复计算
                    problem_num: problem.num,
                    problem_alphabet: RankToolGetProblemAlphabetIdx(problem.num),
                    school: team.school || "",
                    team_name: team.name || teamId,
                    is_regular_fb: isRegularFB,
                    is_global_fb: isGlobalFB,
                };

                this.balloonList.push(balloonItem);
            });
        }

        /**
         * 提取rooms、senders、schools和problems列表
         */
        ExtractRoomsAndSenders() {
            this.rooms = [];
            this.senders = [];
            this.schools = [];
            this.problems = [];

            // 从team中提取rooms（数据已经是dict格式）
            const roomSet = new Set();
            this.balloonList.forEach((item) => {
                const room = item.team.room; // room字段（dict格式）
                if (room && room.trim()) {
                    const rooms = room
                        .split(",")
                        .map((r) => r.trim())
                        .filter((r) => r);
                    rooms.forEach((r) => roomSet.add(r));
                }
            });
            this.rooms = Array.from(roomSet).sort();

            // 从balloonList中提取senders
            const senderSet = new Set();
            this.balloonList.forEach((item) => {
                if (item.balloon_sender && item.balloon_sender.trim()) {
                    senderSet.add(item.balloon_sender);
                }
            });
            this.senders = Array.from(senderSet).sort();

            // 从team中提取schools
            const schoolSet = new Set();
            this.balloonList.forEach((item) => {
                const school = item.team.school; // school字段
                if (school && school.trim()) {
                    schoolSet.add(school.trim());
                }
            });
            this.schools = Array.from(schoolSet).sort();

            // 从problem中提取problems（题号）
            const problemSet = new Set();
            this.balloonList.forEach((item) => {
                if (item.problem_alphabet) {
                    problemSet.add(item.problem_alphabet);
                }
            });
            this.problems = Array.from(problemSet).sort();
        }

        /**
         * 更新筛选器
         */
        UpdateFilters() {
            // 更新sender筛选器
            if (this.isBalloonManager) {
                const senderSelect = document.getElementById("filter-sender");
                if (senderSelect) {
                    // 保存当前选中的值（安全获取）
                    const $senderSelect = $(senderSelect);
                    let currentSender = [];
                    try {
                        if ($senderSelect.data("multipleSelect")) {
                            currentSender = $senderSelect.multipleSelect("getSelects") || [];
                        } else {
                            // 如果未初始化，直接从原生 select 获取
                            currentSender = senderSelect.value ? [senderSelect.value] : [];
                        }
                    } catch (e) {
                        // 如果获取失败，使用空数组
                        currentSender = [];
                    }

                    senderSelect.innerHTML =
                        '<option value="">全部<en-text>All</en-text></option>';
                    this.senders.forEach((sender) => {
                        const option = document.createElement("option");
                        option.value = sender;
                        option.textContent = sender;
                        senderSelect.appendChild(option);
                    });

                    // 如果已经初始化，使用 refresh() 方法更新选项
                    if ($senderSelect.data("multipleSelect")) {
                        try {
                            // 保存当前选中值
                            const savedSender = this.filters.balloon_sender;
                            const senderToRestore =
                                savedSender && this.senders.includes(savedSender)
                                    ? [savedSender]
                                    : currentSender.length > 0 &&
                                        currentSender[0] !== "" &&
                                        this.senders.includes(currentSender[0])
                                        ? currentSender
                                        : [];

                            // 使用 refresh() 方法刷新选项（会从更新后的 DOM 读取选项）
                            $senderSelect.multipleSelect("refresh");

                            // 恢复选中值
                            if (senderToRestore.length > 0) {
                                $senderSelect.multipleSelect("setSelects", senderToRestore);
                            }
                        } catch (e) {
                            // 如果 refresh 失败，回退到 destroy + 重建
                            console.warning("刷新 MultipleSelect 失败");
                            try {
                                const currentOptions =
                                    $senderSelect.multipleSelect("getOptions");
                                currentOptions.onClick = () => {
                                    this.OnFilterChange();
                                };
                                $senderSelect.multipleSelect("destroy");
                                $senderSelect.removeData("multipleSelect");
                                $senderSelect.multipleSelect(currentOptions);
                                $senderSelect.off("change").on("change", () => {
                                    this.OnFilterChange();
                                });
                                const savedSender = this.filters.balloon_sender;
                                if (savedSender && this.senders.includes(savedSender)) {
                                    $senderSelect.multipleSelect("setSelects", [savedSender]);
                                } else if (
                                    currentSender.length > 0 &&
                                    currentSender[0] !== "" &&
                                    this.senders.includes(currentSender[0])
                                ) {
                                    $senderSelect.multipleSelect("setSelects", currentSender);
                                }
                            } catch (e2) {
                                // 重新初始化失败，静默处理
                            }
                        }
                    } else {
                        // 如果未初始化，直接设置选中值（从 localStorage 恢复）
                        const savedSender = this.filters.balloon_sender;
                        if (savedSender) {
                            senderSelect.value = savedSender;
                        }
                    }
                }
            }

            // 更新room筛选器（使用multiple-select）
            const roomSelect = document.getElementById("filter-rooms");
            if (roomSelect) {
                // balloonSender 且 teamRoom 为空时，显示筛选器
                // balloonManager 总是显示筛选器
                if (!this.isBalloonSender || !this.teamRoom) {
                    // 保存当前选中的值（安全获取）
                    const $roomSelect = $(roomSelect);
                    let currentRooms = [];
                    try {
                        if ($roomSelect.data("multipleSelect")) {
                            currentRooms = $roomSelect.multipleSelect("getSelects") || [];
                        } else {
                            // 如果未初始化，直接从原生 select 获取
                            currentRooms = Array.from(roomSelect.selectedOptions)
                                .map((opt) => opt.value)
                                .filter((v) => v);
                        }
                    } catch (e) {
                        // 如果获取失败，使用空数组
                        currentRooms = [];
                    }

                    let html = "";
                    if (this.rooms.length === 0) {
                        html =
                            '<option value="">无房间/区域<en-text>No rooms</en-text></option>';
                    } else {
                        this.rooms.forEach((room) => {
                            html += `<option value="${room}">${room}</option>`;
                        });
                    }
                    roomSelect.innerHTML = html;

                    // 如果已经初始化，使用 refresh() 方法更新选项
                    if ($roomSelect.data("multipleSelect")) {
                        try {
                            // 保存要恢复的选中值（优先使用保存的值，否则使用当前值）
                            const savedRooms = this.filters.rooms || [];
                            const roomsToRestore =
                                savedRooms.length > 0
                                    ? savedRooms.filter((r) => this.rooms.includes(r))
                                    : currentRooms.filter((r) => this.rooms.includes(r));

                            // 使用 refresh() 方法刷新选项（会从更新后的 DOM 读取选项）
                            $roomSelect.multipleSelect("refresh");

                            // 恢复选中值（只恢复仍然存在的选项）
                            if (roomsToRestore.length > 0) {
                                $roomSelect.multipleSelect("setSelects", roomsToRestore);
                            }
                        } catch (e) {
                            // 如果 refresh 失败，回退到 destroy + 重建
                            try {
                                const currentOptions = $roomSelect.multipleSelect("getOptions");
                                currentOptions.onClick = () => {
                                    this.OnRoomFilterChange();
                                };
                                const savedRooms = this.filters.rooms || [];
                                const roomsToRestore =
                                    savedRooms.length > 0
                                        ? savedRooms.filter((r) => this.rooms.includes(r))
                                        : currentRooms.filter((r) => this.rooms.includes(r));
                                $roomSelect.multipleSelect("destroy");
                                $roomSelect.removeData("multipleSelect");
                                $roomSelect.multipleSelect(currentOptions);
                                $roomSelect.off("change").on("change", () => {
                                    this.OnRoomFilterChange();
                                });
                                if (roomsToRestore.length > 0) {
                                    $roomSelect.multipleSelect("setSelects", roomsToRestore);
                                }
                            } catch (e2) {
                                // 重新初始化失败，静默处理
                            }
                        }
                    } else {
                        // 如果未初始化，直接设置选中值（从 localStorage 恢复）
                        const savedRooms = this.filters.rooms || [];
                        if (savedRooms.length > 0) {
                            Array.from(roomSelect.options).forEach((opt) => {
                                opt.selected = savedRooms.includes(opt.value);
                            });
                        }
                    }

                    // 更新按钮文本
                    this.UpdateRoomFilterText();
                }
            }

            // 更新school筛选器
            const schoolSelect = document.getElementById("filter-schools");
            if (schoolSelect) {
                // 保存当前选中的值（安全获取）
                const $schoolSelect = $(schoolSelect);
                let currentSchools = [];
                try {
                    if ($schoolSelect.data("multipleSelect")) {
                        currentSchools = $schoolSelect.multipleSelect("getSelects") || [];
                    } else {
                        // 如果未初始化，直接从原生 select 获取
                        currentSchools = Array.from(schoolSelect.selectedOptions)
                            .map((opt) => opt.value)
                            .filter((v) => v);
                    }
                } catch (e) {
                    // 如果获取失败，使用空数组
                    currentSchools = [];
                }

                let html = "";
                if (this.schools.length === 0) {
                    html =
                        '<option value="">无学校<en-text>No schools</en-text></option>';
                } else {
                    this.schools.forEach((school) => {
                        html += `<option value="${school}">${school}</option>`;
                    });
                }
                schoolSelect.innerHTML = html;

                // 如果已经初始化，使用 refresh() 方法更新选项
                if ($schoolSelect.data("multipleSelect")) {
                    try {
                        // 保存要恢复的选中值（优先使用保存的值，否则使用当前值）
                        const savedSchools = this.filters.schools || [];
                        const schoolsToRestore =
                            savedSchools.length > 0
                                ? savedSchools.filter((s) => this.schools.includes(s))
                                : currentSchools.filter((s) => this.schools.includes(s));

                        // 使用 refresh() 方法刷新选项（会从更新后的 DOM 读取选项）
                        $schoolSelect.multipleSelect("refresh");

                        // 恢复选中值（只恢复仍然存在的选项）
                        if (schoolsToRestore.length > 0) {
                            $schoolSelect.multipleSelect("setSelects", schoolsToRestore);
                        }
                    } catch (e) {
                        // 如果 refresh 失败，回退到 destroy + 重建
                        try {
                            const currentOptions = $schoolSelect.multipleSelect("getOptions");
                            currentOptions.onClick = () => {
                                this.OnSchoolFilterChange();
                            };
                            const savedSchools = this.filters.schools || [];
                            const schoolsToRestore =
                                savedSchools.length > 0
                                    ? savedSchools.filter((s) => this.schools.includes(s))
                                    : currentSchools.filter((s) => this.schools.includes(s));
                            $schoolSelect.multipleSelect("destroy");
                            $schoolSelect.removeData("multipleSelect");
                            $schoolSelect.multipleSelect(currentOptions);
                            $schoolSelect.off("change").on("change", () => {
                                this.OnSchoolFilterChange();
                            });
                            if (schoolsToRestore.length > 0) {
                                $schoolSelect.multipleSelect("setSelects", schoolsToRestore);
                            }
                        } catch (e2) {
                            // 重新初始化失败，静默处理
                        }
                    }
                } else {
                    // 如果未初始化，直接设置选中值（从 localStorage 恢复）
                    const savedSchools = this.filters.schools || [];
                    if (savedSchools.length > 0) {
                        Array.from(schoolSelect.options).forEach((opt) => {
                            opt.selected = savedSchools.includes(opt.value);
                        });
                    }
                }
            }

            // 更新problem筛选器
            const problemSelect = document.getElementById("filter-problems");
            if (problemSelect) {
                // 保存当前选中的值（安全获取）
                const $problemSelect = $(problemSelect);
                let currentProblems = [];
                try {
                    if ($problemSelect.data("multipleSelect")) {
                        currentProblems = $problemSelect.multipleSelect("getSelects") || [];
                    } else {
                        // 如果未初始化，直接从原生 select 获取
                        currentProblems = Array.from(problemSelect.selectedOptions)
                            .map((opt) => opt.value)
                            .filter((v) => v);
                    }
                } catch (e) {
                    // 如果获取失败，使用空数组
                    currentProblems = [];
                }

                let html = "";
                if (this.problems.length === 0) {
                    html =
                        '<option value="">无题号<en-text>No problems</en-text></option>';
                } else {
                    this.problems.forEach((problem) => {
                        html += `<option value="${problem}">${problem}</option>`;
                    });
                }
                problemSelect.innerHTML = html;

                // 如果已经初始化，使用 refresh() 方法更新选项
                if ($problemSelect.data("multipleSelect")) {
                    try {
                        // 保存要恢复的选中值（优先使用保存的值，否则使用当前值）
                        const savedProblems = this.filters.problems || [];
                        const problemsToRestore =
                            savedProblems.length > 0
                                ? savedProblems.filter((p) => this.problems.includes(p))
                                : currentProblems.filter((p) => this.problems.includes(p));

                        // 使用 refresh() 方法刷新选项（会从更新后的 DOM 读取选项）
                        $problemSelect.multipleSelect("refresh");

                        // 恢复选中值（只恢复仍然存在的选项）
                        if (problemsToRestore.length > 0) {
                            $problemSelect.multipleSelect("setSelects", problemsToRestore);
                        }
                    } catch (e) {
                        // 如果 refresh 失败，回退到 destroy + 重建
                        try {
                            const currentOptions =
                                $problemSelect.multipleSelect("getOptions");
                            currentOptions.onClick = () => {
                                this.OnProblemFilterChange();
                            };
                            const savedProblems = this.filters.problems || [];
                            const problemsToRestore =
                                savedProblems.length > 0
                                    ? savedProblems.filter((p) => this.problems.includes(p))
                                    : currentProblems.filter((p) => this.problems.includes(p));
                            $problemSelect.multipleSelect("destroy");
                            $problemSelect.removeData("multipleSelect");
                            $problemSelect.multipleSelect(currentOptions);
                            $problemSelect.off("change").on("change", () => {
                                this.OnProblemFilterChange();
                            });
                            if (problemsToRestore.length > 0) {
                                $problemSelect.multipleSelect("setSelects", problemsToRestore);
                            }
                        } catch (e2) {
                            // 重新初始化失败，静默处理
                        }
                    }
                } else {
                    // 如果未初始化，直接设置选中值（从 localStorage 恢复）
                    const savedProblems = this.filters.problems || [];
                    if (savedProblems.length > 0) {
                        Array.from(problemSelect.options).forEach((opt) => {
                            opt.selected = savedProblems.includes(opt.value);
                        });
                    }
                }
            }

            // 初始化所有 multiple-select（排除已经初始化过的元素）
            // 注意：balloon-print-page-size 现在使用普通 select，不在此范围内
            const self = this;
            $(".multiple-select").each(function () {
                const $el = $(this);
                const elId = $el.attr("id");

                // 检查是否已经初始化过（通过检查是否有 multipleSelect 数据）
                // 如果已经初始化，先销毁再重新初始化（防止重复实例化）
                if ($el.data("multipleSelect")) {
                    try {
                        $el.multipleSelect("destroy");
                        $el.removeData("multipleSelect");
                    } catch (e) {
                        // 即使销毁失败，也清除数据，强制重新初始化
                        $el.removeData("multipleSelect");
                    }
                }

                // 现在可以安全地初始化
                if (!$el.data("multipleSelect")) {
                    // 根据不同的筛选器设置不同的回调
                    let onClickCallback = null;
                    let savedValues = [];

                    if (elId === "filter-sender") {
                        onClickCallback = function () {
                            self.OnFilterChange();
                        };
                        savedValues = self.filters.balloon_sender
                            ? [self.filters.balloon_sender]
                            : [];
                    } else if (elId === "filter-rooms") {
                        onClickCallback = function () {
                            self.OnRoomFilterChange();
                        };
                        savedValues = self.filters.rooms || [];
                    } else if (elId === "filter-schools") {
                        onClickCallback = function () {
                            self.OnSchoolFilterChange();
                        };
                        savedValues = self.filters.schools || [];
                    } else if (elId === "filter-problems") {
                        onClickCallback = function () {
                            self.OnProblemFilterChange();
                        };
                        savedValues = self.filters.problems || [];
                    }

                    $el.multipleSelect({
                        filter: true,
                        filterPlaceholder: "搜索...",
                        maxHeight: 800,
                        onClick: onClickCallback || undefined,
                    });

                    // 恢复保存的选中值
                    if (savedValues.length > 0) {
                        try {
                            $el.multipleSelect("setSelects", savedValues);
                        } catch (e) {
                            // 恢复选中值失败，静默处理
                        }
                    }

                    // 如果使用 onClick 回调，也需要监听原生 change 事件作为备用
                    if (onClickCallback) {
                        $el.on("change", onClickCallback);
                    }
                }
            });

            // 恢复搜索框的值
            const searchInput = document.getElementById("filter-search");
            if (searchInput && this.filters.searchText) {
                searchInput.value = this.filters.searchText;
            }
        }

        /**
         * 更新room筛选器按钮文本
         */
        UpdateRoomFilterText() {
            const roomText = document.getElementById("filter-rooms-text");
            if (!roomText) return;

            if (this.filters.rooms.length === 0) {
                roomText.innerHTML = "全部<en-text>All</en-text>";
            } else if (this.filters.rooms.length === 1) {
                roomText.textContent = this.filters.rooms[0];
            } else {
                roomText.innerHTML = `已选 ${this.filters.rooms.length} 个<en-text>Selected ${this.filters.rooms.length}</en-text>`;
            }
        }

        /**
         * Room筛选变更事件
         */
        OnRoomFilterChange() {
            const roomSelect = document.getElementById("filter-rooms");
            if (roomSelect) {
                // 从原生 select 元素获取选中的值（multiple-select 会更新原生 select 的 selectedOptions）
                this.filters.rooms = Array.from(roomSelect.selectedOptions)
                    .map((option) => option.value)
                    .filter((value) => value !== ""); // 排除空值
            }

            // 保存到 localStorage
            this.SaveFiltersToStorage();

            // 更新按钮文本
            this.UpdateRoomFilterText();

            // 重新渲染列表
            this.RenderQueueList();
        }

        /**
         * School筛选变更事件
         */
        OnSchoolFilterChange() {
            const schoolSelect = document.getElementById("filter-schools");
            if (schoolSelect) {
                // 从原生 select 元素获取选中的值
                this.filters.schools = Array.from(schoolSelect.selectedOptions)
                    .map((option) => option.value)
                    .filter((value) => value !== ""); // 排除空值
            }

            // 保存到 localStorage
            this.SaveFiltersToStorage();

            // 重新渲染列表
            this.RenderQueueList();
        }

        /**
         * Problem筛选变更事件
         */
        OnProblemFilterChange() {
            const problemSelect = document.getElementById("filter-problems");
            if (problemSelect) {
                // 从原生 select 元素获取选中的值
                this.filters.problems = Array.from(problemSelect.selectedOptions)
                    .map((option) => option.value)
                    .filter((value) => value !== ""); // 排除空值
            }

            // 保存到 localStorage
            this.SaveFiltersToStorage();

            // 重新渲染列表
            this.RenderQueueList();
        }

        /**
         * 搜索文本变更事件
         */
        OnSearchChange(searchText) {
            this.filters.searchText = (searchText || "").trim();

            // 保存到 localStorage
            this.SaveFiltersToStorage();

            // 重新渲染列表
            this.RenderQueueList();
        }

        /**
         * 筛选变更事件
         */
        OnFilterChange() {
            // 更新筛选状态
            if (this.isBalloonManager) {
                const senderSelect = document.getElementById("filter-sender");
                if (senderSelect) {
                    this.filters.balloon_sender = senderSelect.value || null;
                }
            }

            // 保存到 localStorage
            this.SaveFiltersToStorage();

            // 重新渲染列表
            this.RenderQueueList();
        }

        /**
         * 获取筛选后的气球列表
         */
        GetFilteredBalloonList() {
            let filtered = [...this.balloonList];

            // balloonSender模式：根据标签页筛选
            if (this.isBalloonSender) {
                if (this.currentTab === "queue") {
                    // 气球队列：只显示bst=0的
                    filtered = filtered.filter((item) => item.bst === 0);
                } else if (this.currentTab === "my_balloons") {
                    // 我的气球：显示bst!=0且balloon_sender是自己的
                    filtered = filtered.filter(
                        (item) => item.bst !== 0 && item.balloon_sender === this.currentUser
                    );
                }

                // room筛选（固定或自由）
                if (this.teamRoom) {
                    // 固定筛选：只显示team_room中的
                    const allowedRooms = this.teamRoom
                        .split(",")
                        .map((r) => r.trim())
                        .filter((r) => r);
                    filtered = filtered.filter((item) => {
                        const teamRooms = (item.team.room || "")
                            .split(",")
                            .map((r) => r.trim())
                            .filter((r) => r);
                        return teamRooms.some((r) => allowedRooms.includes(r));
                    });
                } else if (this.filters.rooms.length > 0) {
                    // 自由筛选
                    filtered = filtered.filter((item) => {
                        const teamRooms = (item.team.room || "")
                            .split(",")
                            .map((r) => r.trim())
                            .filter((r) => r);
                        return teamRooms.some((r) => this.filters.rooms.includes(r));
                    });
                }

                // 学校筛选（balloonSender模式也支持）
                if (this.filters.schools.length > 0) {
                    filtered = filtered.filter((item) => {
                        const school = (item.team.school || "").trim();
                        return school && this.filters.schools.includes(school);
                    });
                }

                // 题号筛选（balloonSender模式也支持）
                if (this.filters.problems.length > 0) {
                    filtered = filtered.filter((item) => {
                        return (
                            item.problem_alphabet &&
                            this.filters.problems.includes(item.problem_alphabet)
                        );
                    });
                }

                // 搜索文本筛选（balloonSender模式也支持）
                if (this.filters.searchText) {
                    const searchLower = this.filters.searchText.toLowerCase();
                    filtered = filtered.filter((item) => {
                        const teamId = String(item.team_id || "").toLowerCase();
                        const teamName = (item.team_name || "").toLowerCase();
                        return (
                            teamId.includes(searchLower) || teamName.includes(searchLower)
                        );
                    });
                }
            } else {
                // balloonManager模式：应用所有筛选
                if (this.filters.status.length > 0) {
                    filtered = filtered.filter((item) =>
                        this.filters.status.includes(item.bst)
                    );
                }

                if (this.filters.balloon_sender !== null) {
                    filtered = filtered.filter(
                        (item) => item.balloon_sender === this.filters.balloon_sender
                    );
                }

                if (this.filters.rooms.length > 0) {
                    filtered = filtered.filter((item) => {
                        const teamRooms = (item.team.room || "")
                            .split(",")
                            .map((r) => r.trim())
                            .filter((r) => r);
                        return teamRooms.some((r) => this.filters.rooms.includes(r));
                    });
                }

                // 学校筛选
                if (this.filters.schools.length > 0) {
                    filtered = filtered.filter((item) => {
                        const school = (item.team.school || "").trim();
                        return school && this.filters.schools.includes(school);
                    });
                }

                // 题号筛选
                if (this.filters.problems.length > 0) {
                    filtered = filtered.filter((item) => {
                        return (
                            item.problem_alphabet &&
                            this.filters.problems.includes(item.problem_alphabet)
                        );
                    });
                }

                // 搜索文本筛选（队伍ID、队名模糊匹配）
                if (this.filters.searchText) {
                    const searchLower = this.filters.searchText.toLowerCase();
                    filtered = filtered.filter((item) => {
                        const teamId = String(item.team_id || "").toLowerCase();
                        const teamName = (item.team_name || "").toLowerCase();
                        return (
                            teamId.includes(searchLower) || teamName.includes(searchLower)
                        );
                    });
                }
            }

            return filtered;
        }

        /**
         * 渲染队列列表（使用bootstrap-table）
         */
        RenderQueueList() {
            const table = $("#balloon-queue-table");
            if (!table.length) return;

            // 获取筛选后的数据（数据已经在 BuildBalloonList 中计算好所有字段）
            const tableData = this.GetFilteredBalloonList();

            // 如果表格已经初始化，直接加载数据
            this.TableLoadData(tableData);

            // 绑定表格点击事件（统一管理所有列的点击）
            this.BindTableClickEvents();
        }

        /**
         * 绑定表格点击事件（统一管理所有列的点击）
         */
        BindTableClickEvents() {
            const table = $("#balloon-queue-table");

            // 防止重复绑定
            table.off("click-cell.bs.table");
            table.off("dbl-click-cell.bs.table");

            table.on("click-cell.bs.table", (e, field, td, row) => {
                if (field === "team_id") {
                    // 点击队伍ID：显示队伍详情
                    this.ShowTeamDetails(row);
                } else if (field === "bst") {
                    // 点击状态列
                    if (this.isBalloonManager) {
                        // 管理员模式：弹出模态框编辑
                        this.ShowManagerModal(row);
                    } else {
                        // 配送员模式：显示状态解释
                        this.ShowStatusExplanation(row);
                    }
                }
            });

            // 双击事件处理
            table.on("dbl-click-cell.bs.table", (e, field, td, row) => {
                if (field === "bst") {
                    // 配送员模式：双击状态列转变状态
                    if (!this.isBalloonManager) {
                        this.HandleStatusChange(row);
                    }
                } else if (field === "idx") {
                    // 双击序号列：预览打印
                    this.PreviewSingleBalloon(row);
                } else if (field === "problem") {
                    // 双击题号列：实际打印并更新状态
                    this.PrintSingleBalloonWithStatusUpdate(row);
                }
            });
        }

        /**
         * 显示状态解释（配送员模式）
         */
        ShowStatusExplanation(row) {
            const statusInfo =
                this.balloonStatusMap[row.bst] || this.balloonStatusMap[0];
            const problemAlphabet = row.problem_alphabet;
            const teamId = row.team_id;

            // 根据当前状态和标签页，显示可以转变的状态
            let nextStatusText = "";
            if (this.currentTab === "queue") {
                nextStatusText = "双击可抢取此气球任务（状态变为：已分配）";
            } else {
                if (row.bst === 20) {
                    nextStatusText = "双击可发出此气球（状态变为：已发放）";
                } else if (row.bst === 30) {
                    nextStatusText = "双击可撤回此气球（状态变为：已分配）";
                } else if (row.bst !== 0) {
                    nextStatusText = "双击可退回此气球（状态变为：未发气球）";
                }
            }

            alerty.info({
                message: `队伍 ${teamId} 的题目 ${problemAlphabet}\n当前状态：${statusInfo.cn}\n${nextStatusText}`,
                message_en: `Team ${teamId} Problem ${problemAlphabet}\nCurrent Status: ${statusInfo.en}\nDouble-click to change status`,
            });
        }

        /**
         * 处理状态转变（配送员模式）
         */
        HandleStatusChange(row) {
            if (this.currentTab === "queue") {
                // 气球队列：抢
                this.HandleGrab(row);
            } else {
                // 我的气球：根据状态转变
                if (row.bst === 20) {
                    this.HandleDeliver(row);
                } else if (row.bst === 30) {
                    this.HandleWithdraw(row);
                } else if (row.bst !== 0) {
                    this.HandleReturn(row);
                }
            }
        }

        /**
         * 生成队伍信息HTML（水平布局：label在左，内容在右，每条一行）
         * @param {Object} team - 队伍数据对象
         * @param {string} teamId - 队伍ID
         * @param {string} problemAlphabet - 题号（可选）
         * @returns {string} HTML字符串
         */
        GenerateTeamInfoHTML(team, teamId, problemAlphabet = null) {
            const teamIdHtml = RankToolEscapeHtml(String(teamId));
            const teamName = RankToolEscapeHtml(team.name || teamId);
            const teamNameEn = team.name_en ? RankToolEscapeHtml(team.name_en) : null;
            const schoolName = RankToolEscapeHtml(team.school || "-");
            const tmember = RankToolEscapeHtml(team.tmember || "-");
            const room = RankToolEscapeHtml(team.room || "-");
            const coach = team.coach ? RankToolEscapeHtml(team.coach) : null;

            // 水平布局：label在左，内容在右，每条一行
            let html = '<div class="team-info-template">';

            // 队伍ID - 非常突出
            html +=
                '<div class="team-info-item team-info-highlight"><i class="bi bi-hash text-primary"></i><span class="label-text">队伍ID <span class="en-text">Team ID</span></span><span class="main-text">' +
                teamIdHtml +
                "</span></div>";

            // 区域 - 非常突出
            if (room && room !== "-") {
                html +=
                    '<div class="team-info-item team-info-highlight"><i class="bi bi-geo-alt text-warning"></i><span class="label-text">房间/区域 <span class="en-text">Room</span></span><span class="main-text">' +
                    room +
                    "</span></div>";
            }

            // 题号（如果有）
            if (problemAlphabet) {
                html +=
                    '<div class="team-info-item"><i class="bi bi-file-earmark-text text-info"></i><span class="label-text">题号 <span class="en-text">Problem</span></span><span class="main-text"><span class="badge bg-primary">' +
                    problemAlphabet +
                    "</span></span></div>";
            }

            // 队名
            if (teamName && teamName !== "-") {
                html +=
                    '<div class="team-info-item"><i class="bi bi-flag-fill text-success"></i><span class="label-text">队名 <span class="en-text">Team Name</span></span><span class="main-text">' +
                    teamName;
                if (teamNameEn) {
                    html += ' <span class="sub-text">(' + teamNameEn + ")</span>";
                }
                html += "</span></div>";
            }

            // 学校
            if (schoolName && schoolName !== "-") {
                html +=
                    '<div class="team-info-item"><i class="bi bi-building text-info"></i><span class="label-text">学校 <span class="en-text">School</span></span><span class="main-text">' +
                    schoolName +
                    "</span></div>";
            }

            // 选手
            if (tmember && tmember !== "-") {
                html +=
                    '<div class="team-info-item"><i class="bi bi-people text-secondary"></i><span class="label-text">选手 <span class="en-text">Members</span></span><span class="main-text">' +
                    tmember +
                    "</span></div>";
            }

            // 教练（如果有）
            if (coach) {
                html +=
                    '<div class="team-info-item"><i class="bi bi-person-badge text-secondary"></i><span class="label-text">教练 <span class="en-text">Coach</span></span><span class="main-text">' +
                    coach +
                    "</span></div>";
            }

            html += "</div>";
            return html;
        }

        /**
         * 预览单个气球小票
         */
        PreviewSingleBalloon(row) {
            if (!row) return;

            // 调用打印模块的预览功能
            if (
                window.BalloonPrint &&
                typeof window.BalloonPrint.previewBalloon === "function"
            ) {
                window.BalloonPrint.previewBalloon(row);
            } else {
                alerty.info({
                    message: "预览功能未初始化",
                    message_en: "Preview function not initialized",
                });
            }
        }

        /**
         * 打印单个气球并更新状态
         */
        PrintSingleBalloonWithStatusUpdate(row) {
            if (!row) return;

            // 调用打印模块的打印并更新状态功能
            if (
                window.BalloonPrint &&
                typeof window.BalloonPrint.printSingleBalloonWithStatusUpdate ===
                "function"
            ) {
                window.BalloonPrint.printSingleBalloonWithStatusUpdate(row, this);
            } else {
                alerty.info({
                    message: "打印功能未初始化",
                    message_en: "Print function not initialized",
                });
            }
        }

        /**
         * 显示队伍详细信息
         */
        ShowTeamDetails(row) {
            const teamInfoHTML = this.GenerateTeamInfoHTML(
                row.team,
                row.team_id,
                row.problem_alphabet
            );

            const content = `
                <div class="card border">
                    <div class="card-body" style="padding: 0.75rem;">
                        ${teamInfoHTML}
                    </div>
                </div>
            `;

            alerty.notify({
                title: '队伍信息<span class="en-text">Team Information</span>',
                message: content,
                message_en: "",
                width: 400,
            });
        }

        /**
         * 显示管理员模态框（编辑气球状态和选择配送员）
         */
        async ShowManagerModal(row) {
            const problemAlphabet = row.problem_alphabet;
            const teamId = RankToolEscapeHtml(String(row.team_id));
            const schoolName = RankToolEscapeHtml(row.school);
            const currentStatus = row.bst;
            const currentSender = row.balloon_sender || "";

            // 获取balloon_sender列表
            let senderOptions =
                '<option value="">（留空）<en-text>(Empty)</en-text></option>';
            try {
                const contestId = this.config.cid_list || "";
                const result = await $.ajax({
                    url: `/cpcsys/admin/team_list_ajax?cid=${contestId}&ttype=1`,
                    method: "GET",
                    dataType: "json",
                });
                if (result.code === 1 && result.data && result.data.team_list) {
                    result.data.team_list.forEach((sender) => {
                        const selected = sender.team_id === currentSender ? "selected" : "";
                        const name = RankToolEscapeHtml(sender.name || sender.team_id);
                        const room = RankToolEscapeHtml(sender.room || "");
                        const displayText = `${sender.team_id} - ${name}${room ? ` (${room})` : ""
                            }`;
                        senderOptions += `<option value="${RankToolEscapeHtml(
                            sender.team_id
                        )}" ${selected}>${displayText}</option>`;
                    });
                }
            } catch (error) {
                // 加载配送员列表失败，静默处理
            }

            // 生成状态按钮（四个按钮，点击直接提交）
            let statusButtonsHTML = "";
            const btnClassMap = {
                0: "btn-danger", // 未处理 - 红色
                10: "btn-warning", // 已通知 - 黄色
                20: "btn-info", // 已分配 - 青色
                30: "btn-success", // 已发放 - 绿色
            };
            const iconMap = {
                0: "bi-x-circle-fill",
                10: "bi-printer-fill",
                20: "bi-person-check-fill",
                30: "bi-check-circle-fill",
            };

            Object.keys(this.balloonStatusMap).forEach((status) => {
                const statusNum = parseInt(status);
                const info = this.balloonStatusMap[statusNum];
                const btnClass = btnClassMap[statusNum] || "btn-secondary";
                const icon = iconMap[statusNum] || "bi-question-circle-fill";
                const isActive = currentStatus === statusNum ? "active" : "";
                const bilingualText = `<span><i class="bi ${icon}"></i>${info.cn}</span><span class="en-text">${info.en}</span>`;

                statusButtonsHTML += `
                    <button type="button" title="点击设为此状态 / Click to set this status"
                            class="btn btn-sm ${btnClass} status-btn ${isActive}" 
                            data-status="${statusNum}"
                            style="flex: 1; min-width: 120px;">
                         ${bilingualText}
                    </button>
                `;
            });

            // 使用统一的队伍信息生成函数
            const teamInfoHTML = this.GenerateTeamInfoHTML(
                row.team,
                row.team_id,
                problemAlphabet
            );

            // 创建模态框HTML（符合系统UI风格）
            const modalHtml = `
                <div class="modal fade" id="balloon-manager-modal" tabindex="-1" aria-labelledby="balloonManagerModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title bilingual-inline" id="balloonManagerModalLabel">
                                    编辑气球状态<span class="en-text">Edit Balloon Status</span>
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <!-- 队伍信息卡片 -->
                                <div class="mb-4">
                                    <label class="form-label fw-bold mb-2 bilingual-inline">
                                        队伍信息<span class="en-text">Team Info</span>
                                    </label>
                                    <div class="card border">
                                        <div class="card-body" style="padding: 0.75rem;">
                                            ${teamInfoHTML}
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- 气球状态选择（四个按钮，点击直接提交） -->
                                <div class="mb-3">
                                    <label class="form-label bilingual-inline mb-2">
                                        气球状态<span class="en-text">Balloon Status</span>
                                    </label>
                                    <div class="d-flex gap-2 flex-wrap" id="modal-balloon-status-buttons">
                                        ${statusButtonsHTML}
                                    </div>
                                </div>
                                
                                <!-- 配送员选择 -->
                                <div class="mb-3">
                                    <label class="form-label bilingual-inline" for="modal-balloon-sender">
                                        配送员<span class="en-text">Balloon Sender</span>
                                    </label>
                                    <select class="form-select" id="modal-balloon-sender">
                                        ${senderOptions}
                                    </select>
                                    <div class="form-text">
                                        选择配送员或留空 <span class="en-text">Select sender or leave empty</span>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    取消<span class="en-text">Cancel</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // 移除旧的模态框（如果存在）
            const oldModal = document.getElementById("balloon-manager-modal");
            if (oldModal) {
                oldModal.remove();
            }

            // 添加新模态框到body
            document.body.insertAdjacentHTML("beforeend", modalHtml);

            // 初始化Bootstrap模态框
            const modalElement = document.getElementById("balloon-manager-modal");
            const modal = new bootstrap.Modal(modalElement);

            // 绑定状态按钮事件（点击直接提交）
            const statusButtons = modalElement.querySelectorAll(".status-btn");
            const handleStatusChange = (newStatus) => {
                const newSender =
                    document.getElementById("modal-balloon-sender").value.trim() || null;

                // 只有管理员将状态设为20时，op才是'set_sender'，其他情况留空
                const op = newStatus == 20 ? "set_sender" : "";

                // 调用API更新状态
                this.ChangeBalloonStatus(row, newStatus, newSender, op);

                // 关闭模态框
                modal.hide();
            };

            statusButtons.forEach((btn) => {
                btn.addEventListener("click", () => {
                    const newStatus = parseInt(btn.getAttribute("data-status"));
                    handleStatusChange(newStatus);
                });
            });

            // 快捷键映射：N->0(未处理), P->10(已通知), A->20(已分配), D->30(已发放)
            const keyStatusMap = {
                n: 0, // 未处理
                p: 10, // 已通知
                a: 20, // 已分配
                d: 30, // 已发放
            };

            // 快捷键处理函数
            const handleKeydown = (e) => {
                // 检查模态框是否存在且显示
                if (!modalElement || !document.body.contains(modalElement)) {
                    return;
                }

                // 检查模态框是否显示（Bootstrap 5 模态框显示时会有 show class）
                if (!modalElement.classList.contains("show")) {
                    return;
                }

                // 如果焦点在输入框、文本框或下拉框中，不处理快捷键
                const activeElement = document.activeElement;
                if (
                    activeElement &&
                    (activeElement.tagName === "INPUT" ||
                        activeElement.tagName === "TEXTAREA" ||
                        activeElement.tagName === "SELECT" ||
                        activeElement.isContentEditable)
                ) {
                    return;
                }

                // 检查按键是否匹配（不区分大小写）
                const key = e.key.toLowerCase();
                if (keyStatusMap.hasOwnProperty(key)) {
                    e.preventDefault();
                    e.stopPropagation();
                    const newStatus = keyStatusMap[key];
                    handleStatusChange(newStatus);
                }
            };

            // 绑定快捷键事件（在模态框打开时）
            document.addEventListener("keydown", handleKeydown);

            // 模态框关闭时移除快捷键事件和DOM
            modalElement.addEventListener("hidden.bs.modal", () => {
                // 移除快捷键事件
                document.removeEventListener("keydown", handleKeydown);
                // 移除DOM
                modalElement.remove();
            });

            // 显示模态框
            modal.show();
        }

        /**
         * 处理"抢"操作
         */
        HandleGrab(row) {
            const message = `确定要抢队伍 ${row.team_id} 的题目 ${row.problem_alphabet} 的气球任务吗？\nAre you sure you want to grab the balloon task for team ${row.team_id} problem ${row.problem_alphabet}?`;

            alerty.confirm({
                message: message,
                message_en: "",
                callback: () => {
                    this.ChangeBalloonStatus(row, 20, null, "grab");
                },
            });
        }

        /**
         * 处理"退回"操作
         */
        HandleReturn(row) {
            if (row.bst === 0 || row.bst === 30) return;

            const message = `确定要将队伍 ${row.team_id} 的题目 ${row.problem_alphabet} 的气球退回吗？\nAre you sure you want to return the balloon for team ${row.team_id} problem ${row.problem_alphabet}?`;

            alerty.confirm({
                message: message,
                message_en: "",
                callback: () => {
                    this.ChangeBalloonStatus(row, 0, null, "");
                },
            });
        }

        /**
         * 处理"撤回"操作
         */
        HandleWithdraw(row) {
            if (row.bst !== 30) return;

            const message = `确定要撤回队伍 ${row.team_id} 的题目 ${row.problem_alphabet} 的气球吗？\nAre you sure you want to withdraw the balloon for team ${row.team_id} problem ${row.problem_alphabet}?`;

            alerty.confirm({
                message: message,
                message_en: "",
                callback: () => {
                    this.ChangeBalloonStatus(row, 20, row.balloon_sender, "");
                },
            });
        }

        /**
         * 处理"发出"操作
         */
        HandleDeliver(row) {
            if (row.bst !== 20) return;

            const message = `确定要发出队伍 ${row.team_id} 的题目 ${row.problem_alphabet} 的气球吗？\nAre you sure you want to deliver the balloon for team ${row.team_id} problem ${row.problem_alphabet}?`;

            alerty.confirm({
                message: message,
                message_en: "",
                callback: () => {
                    this.ChangeBalloonStatus(row, 30, row.balloon_sender, "");
                },
            });
        }

        /**
         * 调用API改变气球状态
         */
        /**
         * 统一的数据更新方法（核心方法）
         * 更新单个气球项（统一更新数据源和视图）
         * @param {String} solutionId - solution_id
         * @param {Object} updates - 要更新的字段 { bst, pst, balloon_sender, ... }
         * @param {Boolean} updateView - 是否更新视图（默认true）
         */
        UpdateBalloonItem(solutionId, updates, updateView = true) {
            // 1. 更新 balloonList（数据源）
            const balloonItem = this.balloonList.find(item => item.solution_id === solutionId);
            if (!balloonItem) return;
            
            // 🔥 记录更新前的 bst 值（用于统计可打印数量）
            const oldBst = Number(balloonItem.bst) || 0;
            const wasPrintable = (oldBst === 0);
            
            // 更新数据
            Object.assign(balloonItem, updates);
            
            // 🔥 更新可打印数量统计（如果 bst 发生变化）
            if (updates.bst !== undefined) {
                const newBst = Number(updates.bst) || 0;
                const isPrintable = (newBst === 0);
                
                if (wasPrintable && !isPrintable) {
                    // 从可打印变为不可打印，计数 -1
                    this.autoPrintPrintableCount--;
                    if (this.autoPrintPrintableCount < 0) {
                        this.autoPrintPrintableCount = 0;
                    }
                } else if (!wasPrintable && isPrintable) {
                    // 从不可打印变为可打印，计数 +1
                    this.autoPrintPrintableCount++;
                }
            }
            
            // 2. 更新 balloonMap（用于统计）
            if (this.balloonMap) {
                const key = `${balloonItem.team_id}_${balloonItem.problem_id}`;
                const balloon = this.balloonMap.get(key);
                if (balloon) {
                    Object.assign(balloon, updates);
                } else {
                    // 如果不存在，创建新记录
                    this.balloonMap.set(key, {
                        team_id: balloonItem.team_id,
                        problem_id: balloonItem.problem_id,
                        ...updates
                    });
                }
            }
            
            // 3. 更新 bootstrap-table 视图
            if (updateView) {
                const table = $("#balloon-queue-table");
                if (table.length && table.data("bootstrap.table")) {
                    // 从 balloonList 获取最新的完整数据（包含所有字段）
                    const updatedItem = this.balloonList.find(item => item.solution_id === solutionId);
                    if (updatedItem) {
                        // 获取筛选后的数据，找到对应的项
                        const filteredList = this.GetFilteredBalloonList();
                        const filteredItem = filteredList.find(item => item.solution_id === solutionId);
                        if (filteredItem) {
                            // 如果项在筛选结果中，更新表格
                            table.bootstrapTable("updateByUniqueId", {
                                id: solutionId,
                                row: updatedItem
                            });
                        } else {
                            // 如果项不在筛选结果中（被筛选掉了），移除表格行
                            table.bootstrapTable("removeByUniqueId", solutionId);
                        }
                    }
                }
            }
        }

        ChangeBalloonStatus(row, bst, balloonSender, op, flg_show_success=true) {
            const self = this;
            
            // 🔥 频率控制：每连续调用 10 次，等待 3 秒
            this.statusChangeCallCount++;
            if (this.statusChangeCallCount >= 10) {
                // 重置计数器
                this.statusChangeCallCount = 0;
                // 显示等待倒计时
                this.statusChangeWaitCountdown = 3;
                this.ShowStatusChangeWaitCountdown();
                
                // 等待 3 秒后再执行
                return new Promise((resolve) => {
                    if (this.statusChangeWaitTimer) {
                        clearTimeout(this.statusChangeWaitTimer);
                    }
                    this.statusChangeWaitTimer = setTimeout(() => {
                        this.statusChangeWaitTimer = null;
                        this.HideStatusChangeWaitCountdown();
                        // 递归调用，继续执行
                        this.ChangeBalloonStatus(row, bst, balloonSender, op, flg_show_success).then(resolve);
                    }, 3000);
                });
            }
            
            const contestId = this.config.cid_list || "";
            const params = {
                cid: contestId,
                solution_id: row.solution_id,
                pst: row.is_global_fb ? 20 : row.is_regular_fb ? 10 : 0,
                bst: bst,
                op: op,
            };

            if (balloonSender) {
                params.balloon_sender = balloonSender;
            }

            return new Promise((resolve, reject) => {
                $.ajax({
                    url: this.changeStatusUrl,
                    method: "POST",
                    data: params,
                    dataType: "json",
                    success: function (rep) {
                        if (rep.code === 1) {
                            const statusInfo =
                                self.balloonStatusMap[rep.data.bst] || self.balloonStatusMap[0];
                            if(flg_show_success) {
                                alerty.success(
                                    `队伍：${rep.data.team_id}  题目: ${row.problem_alphabet} \n成功更新为 ${statusInfo.cn}`,
                                    `Team ${rep.data.team_id}   Problem ${row.problem_alphabet} \nUpdated to ${statusInfo.en} successfully`
                                );
                            }

                            // 🔥 使用统一更新方法
                            const updates = {
                                bst: parseInt(rep.data.bst),
                                pst: parseInt(rep.data.pst)
                            };
                            if (rep.data.balloon_sender !== undefined) {
                                updates.balloon_sender = rep.data.balloon_sender || "";
                            }
                            
                            // 统一更新数据源和视图
                            self.UpdateBalloonItem(row.solution_id, updates, true);
                            
                            // 同时更新 row 对象（保持兼容性）
                            Object.assign(row, updates);

                            // 更新统计框
                            self.CalculateBalloonStats();
                            self.UpdateGlobalStats();
                            resolve(rep);
                        } else {
                            alerty.alert({
                                message: rep.msg || "操作失败",
                                message_en: "",
                            });
                            reject(new Error(rep.msg || "操作失败"));
                        }
                    },
                    error: function (xhr, status, error) {
                        alerty.alert({
                            message: "操作失败，请重试",
                            message_en: "Operation failed, please try again",
                        });
                        reject(new Error(error || "操作失败"));
                    }
                });
            });
        }

        /**
         * 显示状态更新等待倒计时
         */
        ShowStatusChangeWaitCountdown() {
            const countdownTextEl = document.getElementById("balloon-print-countdown-text");
            if (countdownTextEl) {
                countdownTextEl.textContent = "等待 " + this.statusChangeWaitCountdown + " 秒";
                countdownTextEl.setAttribute("data-wait-type", "status-change");
            }
            
            // 启动倒计时显示
            if (this.statusChangeWaitCountdownTimer) {
                clearInterval(this.statusChangeWaitCountdownTimer);
            }
            this.statusChangeWaitCountdownTimer = setInterval(() => {
                this.statusChangeWaitCountdown--;
                const countdownTextEl = document.getElementById("balloon-print-countdown-text");
                if (countdownTextEl) {
                    countdownTextEl.textContent = "等待 " + this.statusChangeWaitCountdown + " 秒";
                }
                if (this.statusChangeWaitCountdown <= 0) {
                    clearInterval(this.statusChangeWaitCountdownTimer);
                    this.statusChangeWaitCountdownTimer = null;
                }
            }, 1000);
        }

        /**
         * 隐藏状态更新等待倒计时
         */
        HideStatusChangeWaitCountdown() {
            if (this.statusChangeWaitCountdownTimer) {
                clearInterval(this.statusChangeWaitCountdownTimer);
                this.statusChangeWaitCountdownTimer = null;
            }
            this.statusChangeWaitCountdown = 0;
            const countdownTextEl = document.getElementById("balloon-print-countdown-text");
            if (countdownTextEl) {
                countdownTextEl.removeAttribute("data-wait-type");
            }
        }

        /**
         * 显示排序筛选等待倒计时
         */
        ShowSortWaitCountdown() {
            const countdownTextEl = document.getElementById("balloon-print-countdown-text");
            if (countdownTextEl) {
                countdownTextEl.textContent = "排序等待 " + this.sortWaitCountdown + " 秒";
                countdownTextEl.setAttribute("data-wait-type", "sort");
            }
            
            // 启动倒计时显示
            if (this.sortWaitCountdownTimer) {
                clearInterval(this.sortWaitCountdownTimer);
            }
            this.sortWaitCountdown = 3;
            this.sortWaitCountdownTimer = setInterval(() => {
                this.sortWaitCountdown--;
                const countdownTextEl = document.getElementById("balloon-print-countdown-text");
                if (countdownTextEl) {
                    countdownTextEl.textContent = "排序等待 " + this.sortWaitCountdown + " 秒";
                }
                if (this.sortWaitCountdown <= 0) {
                    clearInterval(this.sortWaitCountdownTimer);
                    this.sortWaitCountdownTimer = null;
                    this.HideSortWaitCountdown();
                }
            }, 1000);
        }

        /**
         * 隐藏排序筛选等待倒计时
         */
        HideSortWaitCountdown() {
            if (this.sortWaitCountdownTimer) {
                clearInterval(this.sortWaitCountdownTimer);
                this.sortWaitCountdownTimer = null;
            }
            this.sortWaitCountdown = 0;
            const countdownTextEl = document.getElementById("balloon-print-countdown-text");
            if (countdownTextEl) {
                countdownTextEl.removeAttribute("data-wait-type");
            }
        }

        /**
         * 更新队列统计（更新DOM，不重新生成HTML）
         */
        UpdateQueueStats() {
            // 使用父类的统计方法（统计所有AC，而不是只统计balloonList）
            this.CalculateBalloonStats();

            // 更新DOM中的统计数值（不重新生成HTML）
            const statValue0 = document.getElementById("balloon-stat-value-0");
            const statValue10 = document.getElementById("balloon-stat-value-10");
            const statValue20 = document.getElementById("balloon-stat-value-20");
            const statValue30 = document.getElementById("balloon-stat-value-30");

            if (statValue0) statValue0.textContent = this.balloonStats[0] || 0;
            if (statValue10) statValue10.textContent = this.balloonStats[10] || 0;
            if (statValue20) statValue20.textContent = this.balloonStats[20] || 0;
            if (statValue30) statValue30.textContent = this.balloonStats[30] || 0;

            // 绑定点击事件（只需要绑定一次，但每次更新时检查）
            this.BindGlobalStatsClickEvents();

            // 更新选中状态（支持多选）
            const statItems = document.querySelectorAll(
                "#balloon-queue-stats .balloon-stat-item"
            );
            statItems.forEach((statItem) => {
                const status = parseInt(statItem.getAttribute("data-status"));
                const isSelected = this.filters.status.includes(status);
                if (isSelected) {
                    statItem.classList.add("active", "stat-selected");
                } else {
                    statItem.classList.remove("active", "stat-selected");
                }
            });
        }

        /**
         * 统计项点击事件
         */
        OnStatClick(status) {
            // 支持多选：如果已选中则移除，否则添加
            const index = this.filters.status.indexOf(status);
            if (index > -1) {
                // 已选中，移除
                this.filters.status.splice(index, 1);
            } else {
                // 未选中，添加
                this.filters.status.push(status);
            }

            // 保存到 localStorage
            this.SaveFiltersToStorage();

            // 更新统计显示
            this.UpdateQueueStats();

            // 重新渲染列表
            this.RenderQueueList();
        }

        /**
         * 清理所有筛选条件
         */
        ClearAllFilters() {
            this.filters = this.getDefaultFilters();

            // 清空所有筛选器UI
            const senderSelect = document.getElementById("filter-sender");
            if (senderSelect) {
                senderSelect.value = "";
                const $senderSelect = $(senderSelect);
                if ($senderSelect.data("multipleSelect")) {
                    $senderSelect.multipleSelect("setSelects", []);
                }
            }

            const roomSelect = document.getElementById("filter-rooms");
            if (roomSelect) {
                const $roomSelect = $(roomSelect);
                if ($roomSelect.data("multipleSelect")) {
                    $roomSelect.multipleSelect("setSelects", []);
                } else {
                    Array.from(roomSelect.options).forEach(
                        (opt) => (opt.selected = false)
                    );
                }
            }

            const schoolSelect = document.getElementById("filter-schools");
            if (schoolSelect) {
                const $schoolSelect = $(schoolSelect);
                if ($schoolSelect.data("multipleSelect")) {
                    $schoolSelect.multipleSelect("setSelects", []);
                } else {
                    Array.from(schoolSelect.options).forEach(
                        (opt) => (opt.selected = false)
                    );
                }
            }

            const problemSelect = document.getElementById("filter-problems");
            if (problemSelect) {
                const $problemSelect = $(problemSelect);
                if ($problemSelect.data("multipleSelect")) {
                    $problemSelect.multipleSelect("setSelects", []);
                } else {
                    Array.from(problemSelect.options).forEach(
                        (opt) => (opt.selected = false)
                    );
                }
            }

            const searchInput = document.getElementById("filter-search");
            if (searchInput) {
                searchInput.value = "";
            }

            // 保存到 localStorage（清空状态）
            this.SaveFiltersToStorage();

            // 更新统计显示
            this.UpdateQueueStats();

            // 重新渲染列表
            this.RenderQueueList();
        }

        /**
         * 重写 UpdateGlobalStats，使用队列统计
         */
        UpdateGlobalStats() {
            // 检查是否有全局统计容器（balloon全览页面）
            const globalStatsContainer = document.getElementById(
                "balloon-global-stats"
            );
            if (globalStatsContainer) {
                // 如果有全局统计容器，使用父类的统计方法（统计所有AC）
                super.UpdateGlobalStats();
            }

            // 更新队列统计（队列页面自己的统计）
            this.UpdateQueueStats();
        }

        /**
         * 重写 RefreshData 方法，跳过父类中需要容器的部分（external mode）
         */
        async RefreshData() {
            try {
                // 刷新时不是初始加载
                this.isInitialLoad = false;
                // external mode 下不需要显示刷新按钮加载状态
                // 直接加载数据
                await this.LoadData();
            } catch (error) {
                // 刷新数据失败，静默处理
            }
        }
    }

    // 导出到全局
    window.BalloonManagerSystem = BalloonManagerSystem;
    window.BalloonQueueSystem = BalloonQueueSystem;
}

// ========================================
// 气球队列系统 Formatter
// ========================================
// 根据封榜情况处理行样式
function RowFormatterBalloonQueue(row, index) {
    let ret = {};
    if (row.flg_frozen) {
        // ret.css = {'color': 'white'};
        ret.classes = "bg-info-subtle";
    }
    return ret;
}

// 气球队列 - 队伍ID formatter（可点击显示详情）
function FormatterBalloonTeamId(value, row, index, field) {
    return `<a href="#" class="text-primary">${RankToolEscapeHtml(
        String(value)
    )}</a>`;
}

// 气球队列 - 题号 formatter（醒目显示，双击可打印小票）
function FormatterBalloonProblem(value, row, index, field) {
    const alphabet = row.problem_alphabet || "";
    return `<span class="badge bg-primary fs-5 fw-bold" 
             style="cursor: pointer;" 
             title="双击打印小票 / Double-click to print ticket">
             ${RankToolEscapeHtml(alphabet)}
           </span>`;
}

// 气球队列 - 首答 formatter（图标显示）
function FormatterBalloonFirstBlood(value, row, index, field) {
    let html = "";
    if (row.is_global_fb) {
        html +=
            '<i class="bi bi-star-fill text-warning" title="全场首答 / Global First Blood"></i> ';
        row.pst = 20;
    }
    if (row.is_regular_fb) {
        html +=
            '<i class="bi bi-check-circle-fill text-info" title="正式队首答 / Regular First Blood"></i>';
        row.pst = 10;
    }
    if (!html) {
        html = '<span class="text-muted">—</span>';
    }
    return html;
}

// 气球队列 - 状态 formatter（中英双语风格，参考系统统一样式）
function FormatterBalloonStatus(value, row, index, field) {
    if (!window.balloonQueueSystem) return value;

    const statusMap = window.balloonQueueSystem.balloonStatusMap || {};
    const statusInfo = statusMap[row.bst] ||
        statusMap[0] || { cn: "未知", en: "Unknown", color: "#6c757d" };

    // 图标映射（参考系统统一风格）
    const iconMap = {
        0: "bi bi-x-circle-fill", // 未发气球 - 红色叉号
        10: "bi bi-printer-fill", // 已通知 - 打印机
        20: "bi bi-person-check-fill", // 已分配 - 人员确认
        30: "bi bi-check-circle-fill", // 已发放 - 绿色对勾
    };
    const icon = iconMap[row.bst] || "bi bi-question-circle-fill";

    // 按钮样式类映射（参考系统统一风格）
    const btnClassMap = {
        0: "btn-danger", // 未处理 - 红色
        10: "btn-warning", // 已通知 - 黄色
        20: "btn-info", // 已分配 - 青色
        30: "btn-success", // 已发放 - 绿色
    };
    const btnClass = btnClassMap[row.bst ?? 0] || "btn-secondary";

    // 统一使用 click-cell 事件管理，不需要 onclick
    const titleText = window.balloonQueueSystem?.isBalloonManager
        ? `点击管理气球状态 / Click to manage balloon status`
        : `单击查看详情，双击转变状态 / Click for details, double-click to change status`;

    // 中英双语显示（参考系统统一风格：中文主文本，英文用en-text包裹）
    const bilingualText = `<span><i class="${icon}"></i>${statusInfo.cn}</span><span class="en-text">${statusInfo.en}</span>`;

    return `<button type="button" class="btn btn-sm ${btnClass}" 
             style="cursor: pointer; min-width: 80px;" 
             title="${titleText}">
             ${bilingualText}
           </button>`;
}

// 气球队列 - 配送员 formatter
function FormatterBalloonSender(value, row, index, field) {
    return RankToolEscapeHtml(row.balloon_sender || "-");
}

// 气球队列 - 房间 formatter
function FormatterBalloonRoom(value, row, index, field) {
    const room = row.team?.room || "";
    if (!room || room.trim() === "") {
        return '<span class="text-muted">—</span>';
    }
    return RankToolEscapeHtml(room);
}
