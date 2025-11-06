/**
 * 队伍信息面板控制器
 * 实现展开/收起功能和状态持久化
 */
class TeamInfoPanel {
    constructor() {
        this.contest_id = null;
        this.toggleBtn = null;
        this.panel = null;
        this.logoutBtn = null;
        this.isExpanded = false;
        this.module = null;
        this.api_url = null;
        this.rank_mode = null;
        this.flg_rank_cache = true;
        this.team_id = null;
        
        this.init();
    }
    
    init() {
        // 获取比赛ID
        this.contest_id = window.TEAM_INFO_PANEL_CONFIG.contest_id;
        this.module = window.TEAM_INFO_PANEL_CONFIG.module;
        this.api_url = window.TEAM_INFO_PANEL_CONFIG.api_url;
        this.rank_mode = window.TEAM_INFO_PANEL_CONFIG.rank_mode;
        this.flg_rank_cache = window.TEAM_INFO_PANEL_CONFIG.flg_rank_cache;
        this.team_id = window.TEAM_INFO_PANEL_CONFIG.team_id;
        
        // 成绩相关属性
        this.updateInterval = null;
        this.rankSystem = null;
        
        // 获取DOM元素
        this.toggleBtn = document.getElementById('team_info_toggle');
        this.panel = document.getElementById('team_info_panel');
        this.logoutBtn = document.getElementById('contest_logout_button');
        
        if (!this.toggleBtn || !this.panel || !this.logoutBtn) {
            console.warn('TeamInfoPanel: 必要的DOM元素未找到');
            return;
        }
        
        // 绑定事件
        this.bindEvents();
        
        // 恢复状态
        this.restoreState();
        
        // 初始化成绩系统
        this.initScoreSystem();
    }
    
    bindEvents() {
        // 队伍信息切换按钮
        this.toggleBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.toggle();
        });
        
        // 登出按钮
        this.logoutBtn.addEventListener('click', (e) => {
            e.preventDefault();
            this.handleLogout();
        });
        
        
        // ESC键关闭面板
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isExpanded) {
                this.hide();
            }
        });
    }
    
    toggle() {
        if (this.isExpanded) {
            this.hide();
        } else {
            this.show();
        }
    }
    
    show() {
        this.panel.style.display = 'block';
        this.panel.classList.add('show');
        this.toggleBtn.classList.add('active');
        this.isExpanded = true;
        this.saveState(true);
        
        // 如果成绩系统已初始化，更新成绩显示
        if (this.rankSystem && this.rankSystem.OuterIsDataLoaded()) {
            this.updateScore();
        }
    }
    
    hide() {
        this.panel.style.display = 'none';
        this.panel.classList.remove('show');
        this.toggleBtn.classList.remove('active');
        this.isExpanded = false;
        this.saveState(false);
    }
    
    saveState(isExpanded) {
        if (!this.contest_id) return;
        
        try {
            if (window.csg && window.csg.store) {
                // 使用csg.store保存状态
                window.csg.store(`flg_tinfo_show_${this.contest_id}`, isExpanded);
            } else {
                // 降级到localStorage
                localStorage.setItem(`flg_tinfo_show_${this.contest_id}`, isExpanded.toString());
            }
        } catch (e) {
            console.warn('TeamInfoPanel: 保存状态失败', e);
        }
    }
    
    restoreState() {
        if (!this.contest_id) return;
        
        try {
            let isExpanded = false;
            
            // 使用csg.store读取值（不传第二个参数表示取值）
            const stored = window.csg.store(`flg_tinfo_show_${this.contest_id}`);
            isExpanded = stored === true || stored === 'true';
          
            
            if (isExpanded) {
                this.show();
            } else {
            }
        } catch (e) {
            console.warn('TeamInfoPanel: 恢复状态失败', e);
        }
    }
    
    handleLogout() {
        if (!this.contest_id) {
            console.error('TeamInfoPanel: 无法获取比赛ID，无法执行登出');
            return;
        }
        window.alerty.confirm({
            message: '确定要登出吗？',
            message_en: 'Are you sure you want to logout?',
            title: '确认登出',
            titleEn: 'Confirm Logout',
            okText: '确定',
            okTextEn: 'Confirm',
            cancelText: '取消',
            cancelTextEn: 'Cancel',
            callback: () => {
                this.performLogout();
            },
        });
    }
    
    performLogout() {
        const logoutUrl = `/${this.module}/contest/contest_logout_ajax?cid=${this.contest_id}`;

        $.get(logoutUrl, {}, (rep) => {
            if (rep.code == 1) {
                alerty.success("登出成功", "Logout successful");
                // 延迟刷新页面
                setTimeout(() => {
                    location.reload();
                }, 500);
            } else {
                alerty.error(rep.msg || '登出失败', 'Logout failed');
            }
        }).fail(() => {
            alerty.error('登出请求失败，请重试', 'Logout request failed, please try again');
        });
    }
    
    async initScoreSystem() {
        // 检查是否有成绩面板
        const scorePanel = document.querySelector('.team-score-content');
        if (!scorePanel) {
            return;
        }
        
        try {
            // 初始化RankSystem
            await this.initRankSystem();
            
            // 设置定时更新
            this.setupAutoUpdate();
        } catch (error) {
            console.error('TeamInfoPanel: 初始化成绩系统失败', error);
        }
    }
    
    async initRankSystem() {
        try {
            // 创建RankSystem实例，使用外部模式
            this.rankSystem = new RankSystem('team_score_external_mode', window.TEAM_INFO_PANEL_CONFIG || {});
            
            // 加载数据
            await this.rankSystem.LoadData();
            
            // 处理成绩数据
            this.processScoreData();
            
        } catch (error) {
            console.error('TeamScorePanel: 初始化RankSystem失败', error);
            this.showError('加载成绩数据失败');
        }
    }
    
    processScoreData() {
        if (!this.rankSystem || !this.rankSystem.OuterIsDataLoaded()) {
            console.warn('TeamScorePanel: RankSystem数据未加载');
            return;
        }
        
        // 生成气球容器
        this.generateBalloonContainer();
        
        // 获取当前队伍的数据
        const rankList = this.rankSystem.OuterGetRankList(0); // 打星不排名
        const currentTeam = rankList.find(item => item?.team?.team_id === this.team_id) || null;
        
        // 计算获奖线
        const awardRanks = this.rankSystem.GetAwardRanks({
            flg_ac_team_base: false,
            starMode: 0
        });
        
        // 更新显示（无队伍数据时也渲染占位）
        this.updateScoreDisplay(currentTeam, awardRanks);
    }
    
    generateBalloonContainer() {
        const container = document.querySelector('.cteam_info_balloon_container');
        if (!container) return;
        
        // 清空容器
        container.innerHTML = '';
        
        // 获取题目列表
        const problems = this.rankSystem.OuterGetProblems();
        
        problems.forEach(problem => {
            const problemIndex = RankToolGetProblemAlphabetIdx(problem.num);
            const balloon = document.createElement('div');
            balloon.className = 'cteam_info_balloon cteam_info_outline';
            balloon.setAttribute('data-letter', problemIndex);
            balloon.setAttribute('id', `cteam_score_pro_${problemIndex}`);
            // 使用统一的颜色规范化函数处理颜色
            const normalizedColor = NormalizeColorForDisplay(problem.color) || problem.color || '#CCCCCC';
            balloon.style.setProperty('--balloon-color', normalizedColor);
            
            // 创建链接
            const link = document.createElement('a');
            link.className = 'a_noline';
            link.href = `/${this.module}/contest/problem?cid=${this.contest_id}&pid=${problemIndex}`;
            link.appendChild(balloon);
            
            container.appendChild(link);
        });
    }
    
    updateScoreDisplay(teamData, awardRanks) {
        const noData = !teamData;
        // 计算尝试数量（已尝试但未AC的题目数）
        let triedCount = 0;
        const problems = this.rankSystem.OuterGetProblems();
        if (!noData) {
            problems.forEach(problem => {
                const problemStats = teamData.problemStats && teamData.problemStats[problem.problem_id];
                if (problemStats && problemStats.status === 'wa') {
                    triedCount++;
                }
            });
        }
        
        // 更新基本统计信息（无数据用 * 占位）
        $('#cteam_info_score_solved').text(noData ? '*' : (teamData.solved || 0));
        $('#cteam_info_score_tried').text(noData ? '*' : triedCount);
        
        // 更新排名
        let rank_val = '*';
        if (!noData) {
            rank_val = teamData.displayRank == '*'  ? teamData.displayOrder : (teamData.displayRank ?? '-');
            if (teamData.isStar) {
                rank_val = `<span title="按最接近的正式队排名计算 / Based on the nearest formal team">${rank_val}*</span>`;
            }
        }
        $('#cteam_info_score_rank').html(rank_val);
        
        // 计算并更新奖区
        let temp_award = '*';
        if (!noData && awardRanks) {
            const rankGold = awardRanks.rankGold;
            const rankSilver = awardRanks.rankSilver;
            const rankBronze = awardRanks.rankBronze;
            temp_award = '';
            const temp_award_star = teamData.isStar ? '*' : '';
            const title_addition = teamData.isStar ? '按最接近的正式队排名计算 / Based on the nearest formal team' : '';
            if (rank_val === '-') {
                temp_award = '-';
            } else if (teamData.displayRank <= rankGold) {
                temp_award = `<span class="award_span_gold" title="金 / Gold ${title_addition}">金${temp_award_star}</span>`;
            } else if (teamData.displayRank <= rankSilver) {
                temp_award = `<span class="award_span_silver" title="银 / Silver ${title_addition}">银${temp_award_star}</span>`;
            } else if (teamData.displayRank <= rankBronze) {
                temp_award = `<span class="award_span_bronze" title="铜 / Bronze ${title_addition}">铜${temp_award_star}</span>`;
            } else if (teamData.solved > 0) {
                temp_award = `<span class="award_span_iron" title="铁 / Iron ${title_addition}">铁${temp_award_star}</span>`;
            } else {
                temp_award = `<span>-</span>`;
            }
        }
        $('#cteam_info_score_award').html(temp_award);
        
        // 更新题目状态气球
        this.updateProblemBalloons(teamData);
        
        // 检查封榜状态
        const contest = this.rankSystem.OuterGetContest();
        if (contest && contest.rank_frozen) {
            $('.cteam_info_frozen_mask').show();
            $('#cteam_info_valid_before_frozen').attr('title', '封榜 / Frozen');
        } else {
            $('.cteam_info_frozen_mask').hide();
        }
    }
    
    updateProblemBalloons(teamData) {
        // 获取题目列表
        const problems = this.rankSystem.OuterGetProblems();
        
        problems.forEach(problem => {
            const problemIndex = RankToolGetProblemAlphabetIdx(problem.num);
            const pro_dom = $(`#cteam_score_pro_${problemIndex}`);
            
            if (pro_dom.length === 0) return;
            
            // 重置类名
            pro_dom.attr('class', 'cteam_info_balloon');
            
            // 获取该题目的状态
            const problemStats = teamData.problemStats && teamData.problemStats[problem.problem_id];
            if (problemStats) {
                if (problemStats.status === 'ac') {
                    pro_dom.addClass('cteam_info_solved');
                    pro_dom.attr('title', `${problemIndex}: 已解决 / Solved`);
                } else if (problemStats.status === 'wa') {
                    pro_dom.addClass('cteam_info_tried');
                    pro_dom.attr('title', `${problemIndex}: 已尝试 / Tried`);
                } else {
                    pro_dom.addClass('cteam_info_outline');
                    pro_dom.attr('title', `${problemIndex}: 未尝试 / Not attempted`);
                }
            } else {
                pro_dom.addClass('cteam_info_outline');
                pro_dom.attr('title', `${problemIndex}: 未尝试 / Not attempted`);
            }
        });
    }
    
    updateScore() {
        if (!this.rankSystem || !this.rankSystem.OuterIsDataLoaded()) {
            console.warn('TeamScorePanel: RankSystem数据未加载');
            return;
        }
        
        // 重新处理数据
        this.processScoreData();
    }
    
    setupAutoUpdate() {
        // 设置定时更新
        this.updateInterval = setInterval(() => {
            if (document.visibilityState === 'visible') {
                this.updateScore();
            }
        }, 60000); // 每分钟更新一次
    }
    
    showError(message) {
        console.error('TeamScorePanel:', message);
        // 可以在这里添加错误显示逻辑
        // 例如显示错误提示或隐藏相关元素
    }
    
    destroy() {
        // 清理定时器
        if (this.updateInterval) {
            clearInterval(this.updateInterval);
        }
        
        // 清理RankSystem
        if (this.rankSystem) {
            this.rankSystem.Cleanup();
        }
    }
}

// 页面加载完成后初始化
document.addEventListener('DOMContentLoaded', function() {
    // 等待jQuery加载完成
    
    new TeamInfoPanel();
});