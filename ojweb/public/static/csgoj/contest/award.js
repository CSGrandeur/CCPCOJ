class AwardSystem {
    constructor(containerId, config = {}) {
        this.containerId = containerId;
        this.config = config;
        this.rankSystem = null;
        this.awardData = [];
        this.mapAward = {};
        this.awardToExport = [];
        this.contestTitle = "";

        this.init();
    }

    async init() {
        this.createContainer();
        this.bindEvents();
        this.initSwitches();
        this.loadAwardData();
    }

    createContainer() {
        // 表格已经在模板中定义，不需要创建容器
        // 只需要隐藏表格直到数据加载完成
        const table = document.getElementById("award_table");
        if (table) {
            table.style.display = "none";
        }
    }

    initSwitches() {
        // 初始化 csg-switch 组件
        if (window.csgSwitch) {
            const switches = document.querySelectorAll(".csg-switch-input");
            switches.forEach((switchEl) => {
                window.csgSwitch.initSwitch(switchEl);
            });
        }
    }

    bindEvents() {
        // 绑定开关事件
        const switchWithStarTeam = document.getElementById("switch_with_star_team");
        const switchAllTeamBased = document.getElementById("switch_all_team_based");
        const switchOneTwoThree = document.getElementById("switch_one_two_three");

        if (switchWithStarTeam) {
            switchWithStarTeam.addEventListener("change", () => {
                this.refreshDataShow();
            });
        }

        if (switchAllTeamBased) {
            switchAllTeamBased.addEventListener("change", () => {
                this.refreshDataShow();
            });
        }

        if (switchOneTwoThree) {
            switchOneTwoThree.addEventListener("change", () => {
                this.refreshDataShow();
            });
        }

        // 绑定导出按钮
        const exportCsvBtn = document.getElementById("export_award_csv_btn");
        const exportXlsxBtn = document.getElementById("export_award_xlsx_btn");

        if (exportCsvBtn) {
            exportCsvBtn.addEventListener("click", () => {
                this.exportCSV();
            });
        }

        if (exportXlsxBtn) {
            exportXlsxBtn.addEventListener("click", () => {
                this.exportXLSX();
            });
        }
    }

    async loadAwardData() {
        try {
            // 创建 RankSystem 实例，使用不存在的容器ID触发外部模式
            // 使用 window.RANK_CONFIG 作为配置
            this.rankSystem = new RankSystem(
                "award_external_mode",
                window.RANK_CONFIG || {
                    flg_rank_cache: false,
                }
            );

            // rank模块 加载数据
            await this.rankSystem.LoadData();

            // 处理数据
            this.processAwardData();
        } catch (error) {
            console.error("加载获奖数据失败:", error);
            this.showError("加载获奖数据失败: " + error.message);
        }
    }

    processAwardData() {
        // 处理数据并加载到table
        // 检查 rank 模块是否加载完毕
        if (!this.rankSystem.OuterIsDataLoaded()) {
            throw new Error("RankSystem data not loaded");
        }

        const contest = this.rankSystem.OuterGetContest();
        this.contestTitle = contest?.title || "";

        this.calculateAwards();
        this.updateBootstrapTable();
    }

    calculateAwards() {
        // 计算全部获奖信息
        // 根据开关状态设置 rankSystem 的打星模式
        const flg_include_star = this.getSwitchWithStarTeam();
        const flg_ac_team_base = this.getSwitchAcTeamBased();

        this.awardData = this.getAwardData();

        // 使用 rankSystem 的动态获奖线计算
        const awardRanks = this.rankSystem.GetAwardRanks({
            flg_ac_team_base: flg_ac_team_base,
            starMode: flg_include_star ? 2 : 0,
        });

        const rankGold = awardRanks.rankGold;
        const rankSilver = awardRanks.rankSilver;
        const rankBronze = awardRanks.rankBronze;

        // 计算获奖信息
        this.mapAward = {};
        this.awardToExport = this.getSwitchOneTwoThree()
            ? ["一等奖", "二等奖", "三等奖"]
            : ["金奖", "银奖", "铜奖"];
        this.awardToExport.push("最快解题奖");
        this.awardToExport.push("最佳女队/女生奖");
        this.awardToExport.push("顽强拼搏奖");
        this.awardToExport.push("冠亚季军");

        for (let i = 0; i < this.awardToExport.length; i++) {
            this.mapAward[this.awardToExport[i]] = [];
        }

        const SchoolAwardSet = new Set();
        // 处理每个队伍的获奖信息 - 使用 rankSystem 处理后的数据
        for (let i = 0; i < this.awardData.length; i++) {
            const item = this.awardData[i]; // 修正变量名，避免混淆
            const team = item.team; // item.team 才是真正的 team 信息
            if (item.solved <= 0) continue; // 无论是否为基数 0 题都默认不发奖，如有需求可另外手动处理

            // 初始化获奖数组
            item.awards = [];

            // 金银铜奖
            if (item.displayRank <= rankGold) {
                const awardName = this.getSwitchOneTwoThree() ? "一等奖" : "金奖";
                item.awards.push(awardName);
                item.flg_award = 1;
                this.mapAward[awardName].push(item);
            } else if (item.displayRank <= rankSilver) {
                const awardName = this.getSwitchOneTwoThree() ? "二等奖" : "银奖";
                item.awards.push(awardName);
                item.flg_award = 2;
                this.mapAward[awardName].push(item);
            } else if (item.displayRank <= rankBronze) {
                const awardName = this.getSwitchOneTwoThree() ? "三等奖" : "铜奖";
                item.awards.push(awardName);
                item.flg_award = 3;
                this.mapAward[awardName].push(item);
            } else {
                item.flg_award = 0;
            }

            // 冠亚季军
            if (item?.displaySchoolCntNow === 1 && !SchoolAwardSet.has(1)) {
                SchoolAwardSet.add(1);
                item.awards.push("冠军学校");
                this.mapAward["冠亚季军"].push({ remark: "冠军学校", item: item });
            } else if (item?.displaySchoolCntNow === 2 && !SchoolAwardSet.has(2)) {
                SchoolAwardSet.add(2);
                item.awards.push("亚军学校");
                this.mapAward["冠亚季军"].push({ remark: "亚军学校", item: item });
            } else if (item?.displaySchoolCntNow === 3 && !SchoolAwardSet.has(3)) {
                SchoolAwardSet.add(3);
                item.awards.push("季军学校");
                this.mapAward["冠亚季军"].push({ remark: "季军学校", item: item });
            }

            // 最佳女队/女生奖
            if (team && team.tkind === 1 && item.displayRank <= rankBronze) {
                const isTeam =
                    team.tmember &&
                    (team.tmember.includes("、") ||
                        team.tmember.includes(",") ||
                        team.tmember.includes("，"));
                const awardName = isTeam ? "最佳女队奖" : "最佳女生奖";
                item.awards.push(awardName);
                this.mapAward["最佳女队/女生奖"].push(item);
            }

            // 最快解题奖 - 使用 rankSystem 的 map_fb 数据
            const includeStar = this.getSwitchWithStarTeam();
            const fbData = includeStar
                ? this.rankSystem.map_fb.global
                : this.rankSystem.map_fb.regular;

            // 检查该队伍是否获得任何题目的最快解题奖
            for (const problemId in fbData) {
                const fbTeam = fbData[problemId];
                if (fbTeam && fbTeam.team_id === team.team_id) {
                    // 获取题目编号
                    const problem = this.rankSystem.problemMap[problemId];
                    const problemIndex = problem
                        ? RankToolGetProblemAlphabetIdx(problem.num)
                        : problemId;

                    const awardName = `最快解题奖(${problemIndex})`;
                    item.awards.push(awardName);
                    this.mapAward["最快解题奖"].push({
                        remark: problemIndex,
                        item: item,
                    });
                }
            }
        }

        // 顽强拼搏奖 - 使用 rankSystem 的数据
        this.calculateStruggleAward(rankBronze);
    }

    calculateStruggleAward(rankBronze) {
        // 从 rankSystem 获取 AC 顺序数据
        const solutions = this.rankSystem.OuterGetSolutions();
        if (solutions && solutions.length > 0) {
            const acOrder = solutions
                .filter((s) => s.result === 4) // AC
                .sort((a, b) => a.in_date.localeCompare(b.in_date));

            for (let i = 0; i < acOrder.length; i++) {
                const team_id = acOrder[i].team_id;
                // 使用映射快速查找，O(1) 时间复杂度
                const item = this.teamIdMap[team_id];
                if (item && item.displayRank > rankBronze) {
                    item.awards.push("顽强拼搏奖");
                    this.mapAward["顽强拼搏奖"].push(item);
                    break;
                }
            }
        }
    }

    getSwitchWithStarTeam() {
        const switchEl = document.getElementById("switch_with_star_team");
        return switchEl ? switchEl.checked : false;
    }

    getSwitchAcTeamBased() {
        const switchEl = document.getElementById("switch_all_team_based");
        return switchEl ? !switchEl.checked : false;
    }

    getSwitchOneTwoThree() {
        const switchEl = document.getElementById("switch_one_two_three");
        return switchEl ? switchEl.checked : false;
    }

    getAwardData() {
        this.awardData = this.rankSystem.OuterGetRankList(
            this.getSwitchWithStarTeam() ? 2 : 0
        );
        // 更新 team_id 映射
        this.teamIdMap = {};
        this.awardData.forEach((item) => {
            if (item.team && item.team.team_id) {
                this.teamIdMap[item.team.team_id] = item;
            }
        });
        return this.awardData;
    }

    refreshDataShow() {
        // 重新获取处理后的数据

        this.calculateAwards(); // 会获取awardData
        this.updateBootstrapTable();
    }

    updateBootstrapTable() {
        // 转换数据格式以适配 bootstrap-table
        const tableData = this.awardData.map((item, index) => {
            const team = item.team;
            const rowData = {
                rank: item.displayRank,
                rank_order: item.rank_order,
                awards: item.awards || [], // 获奖信息数组
                flg_award: item.flg_award || 0, // 金银铜标志
                name: team?.name || "",
                name_en: team?.name_en || "",
                tkind: team?.tkind || 0, // 直接使用 tkind 原值
                solved: item.solved || 0,
                penalty: this.validatePenalty(item.penalty),
                school: team?.school || "-",
                members: team?.tmember || "-",
                coach: team?.coach || "-",
                team_id: team?.team_id || "-",
            };
            return rowData;
        });

        // 显示表格并更新数据
        const table = document.getElementById("award_table");
        if (table) {
            table.style.display = "table";
        }

        // 更新 bootstrap-table 数据
        $("#award_table").bootstrapTable("load", tableData);
    }

    exportCSV() {
        const confirmInfo = `
            <strong>务必确认：</strong><br/>
            <span class="text-danger">1. 比赛是否已结束</span><br/>
            <span class="text-danger">2. 评测队列是否已全部完成</span><br/>
            <span class="text-danger">3. <strong class="text-info">确认后，刷新本页</strong>确保获取最新数据，再导出最终结果</span><br/><br/>
            确认现在导出？
        `;

        window.alerty.confirm(confirmInfo, () => {
            this.doExportCSV();
        });
    }

    doExportCSV() {
        const res = [];
        res.push(`获奖名单-${this.contestTitle}`);

        const awardTitle = ["排名", "学校", "队名", "选手", "教练", "账号", "备注"];

        for (let i = 0; i < this.awardToExport.length; i++) {
            const awardName = this.awardToExport[i];
            res.push(`\n${awardName}\n`);
            res.push(awardTitle.join(",") + "\n");

            for (let j = 0; j < this.mapAward[awardName].length; j++) {
                const item = this.mapAward[awardName][j];
                const line = this.makeLineTeam(item);
                res.push(line.join(",") + "\n");
            }
        }

        const blob = new Blob(["\uFEFF" + res.join("")], {
            type: "text/plain;charset=utf-8",
        });
        const downloadLink = document.createElement("a");
        downloadLink.href = URL.createObjectURL(blob);
        downloadLink.download = this.generateExportFilename('csv');
        downloadLink.click();
    }

    exportXLSX() {
        const confirmInfo = `
            <strong>务必确认：</strong><br/>
            <span class="text-danger">1. 比赛是否已结束</span><br/>
            <span class="text-danger">2. 评测队列是否已全部完成</span><br/>
            <span class="text-danger">3. <strong class="text-info">确认后，刷新本页</strong>确保获取最新数据，再导出最终结果</span><br/><br/>
            确认现在导出？
        `;

        window.alerty.confirm(confirmInfo, () => {
            this.doExportXLSX();
        });
    }

    async doExportXLSX() {
        try {
            const alignmentTitle = {
                vertical: "middle",
                horizontal: "center",
                wrapText: true,
            };

            const columnsWidth = [
                { width: 5 },
                { width: 16 },
                { width: 16 },
                { width: 25 },
                { width: 8 },
                { width: 8 },
                { width: 9 },
                { width: 22 },
            ];

            const titleRowStyle = {
                font: { bold: true, size: 16 },
                alignment: { horizontal: "center" },
            };

            const headerRowStyle = { font: { bold: true } };

            const wb = new ExcelJS.Workbook();
            wb.creator = "Me";
            wb.lastModifiedBy = "Me";
            wb.created = new Date();
            wb.modified = new Date();

            // 准备数据
            const allAwardsSplitSheetData = [];
            const allAwardsMergedSheetData = [];
            const wsDataList = [];
            const awardTitle = [
                "排名",
                "学校",
                "队名",
                "选手",
                "教练",
                "账号",
                "备注",
            ];
            
            // 清洗比赛标题用于sheet名称
            const cleanTitle = csg.sanitizeFilename(this.contestTitle);

            for (let i = 0; i < this.awardToExport.length; i++) {
                const awardName = this.awardToExport[i];
                const wsData = [];
                wsData.push([`获奖名单-${cleanTitle}`]);
                wsData.push([awardName]);
                wsData.push(awardTitle);

                for (let j = 0; j < this.mapAward[awardName].length; j++) {
                    const teamLine = this.makeLineTeam(this.mapAward[awardName][j]);
                    wsData.push(teamLine);
                }

                allAwardsSplitSheetData.push(wsData);

                // 合并数据
                for (let k = i === 0 ? 2 : 3; k < wsData.length; k++) {
                    allAwardsMergedSheetData.push(
                        wsData[k].concat([k === 2 ? "奖项" : awardName])
                    );
                }
                wsDataList.push(wsData);
            }

            // 添加所有奖项-分项 sheet
            const allAwardsSplitSheet = wb.addWorksheet("所有奖项-分项", {
                views: [{ xSplit: 1, ySplit: 1 }],
            });
            allAwardsSplitSheet.columns = columnsWidth;

            const allAwardsSplitSheetTitle = allAwardsSplitSheet.addRow([
                `获奖名单-${cleanTitle}`,
            ]);
            allAwardsSplitSheetTitle.height = 40;
            allAwardsSplitSheetTitle.eachCell((cell) => {
                Object.assign(cell.style, titleRowStyle);
            });
            allAwardsSplitSheetTitle.alignment = alignmentTitle;

            allAwardsSplitSheetData.forEach((wsData, index) => {
                for (let i = 1; i < wsData.length; i++) {
                    const row = wsData[i];
                    const excelRow = allAwardsSplitSheet.addRow(row);
                    if (i === 1) {
                        excelRow.eachCell((cell) => {
                            Object.assign(cell.style, titleRowStyle);
                        });
                        excelRow.alignment = alignmentTitle;
                        const rowNumber = excelRow.number;
                        const mergeRange = `A${rowNumber}:G${rowNumber}`;
                        allAwardsSplitSheet.mergeCells(mergeRange);
                        excelRow.height = 30;
                    } else {
                        if (i === 2) {
                            excelRow.eachCell((cell) => {
                                Object.assign(cell.style, headerRowStyle);
                            });
                        }
                        excelRow.eachCell((cell) => {
                            cell.border = {
                                top: { style: "thin" },
                                left: { style: "thin" },
                                bottom: { style: "thin" },
                                right: { style: "thin" },
                            };
                        });
                    }
                }
            });
            allAwardsSplitSheet.mergeCells("A1:G1");

            // 添加所有奖项-合并 sheet
            const allAwardsMergedSheet = wb.addWorksheet("所有奖项-合并", {
                views: [{ xSplit: 1, ySplit: 1 }],
            });
            allAwardsMergedSheet.columns = columnsWidth;

            const allAwardsMergedSheetTitle = allAwardsMergedSheet.addRow([
                `获奖名单-${cleanTitle}`,
            ]);
            allAwardsMergedSheetTitle.height = 40;
            allAwardsMergedSheetTitle.eachCell((cell) => {
                Object.assign(cell.style, titleRowStyle);
            });
            allAwardsMergedSheetTitle.alignment = alignmentTitle;

            allAwardsMergedSheetData.forEach((row, index) => {
                const excelRow = allAwardsMergedSheet.addRow(row);
                if (index === 0) {
                    excelRow.eachCell((cell) => {
                        Object.assign(cell.style, headerRowStyle);
                    });
                }
                excelRow.eachCell((cell) => {
                    cell.border = {
                        top: { style: "thin" },
                        left: { style: "thin" },
                        bottom: { style: "thin" },
                        right: { style: "thin" },
                    };
                    cell.alignment = "right";
                });
            });
            allAwardsMergedSheet.mergeCells("A1:H1");

            // 各奖项sheet
            for (let i = 0; i < wsDataList.length; i++) {
                const awardName = this.awardToExport[i];
                const wsData = wsDataList[i];
                const ws = wb.addWorksheet(awardName.replace(/[:\\/?*[\]]/g, ""));

                wsData.forEach((row, index) => {
                    const excelRow = ws.addRow(row);
                    if (index === 0) {
                        excelRow.height = 40;
                    } else if (index === 1) {
                        excelRow.height = 30;
                    }
                    if (index < 2) {
                        excelRow.eachCell((cell) => {
                            Object.assign(cell.style, titleRowStyle);
                        });
                        excelRow.alignment = alignmentTitle;
                    } else {
                        if (index === 2) {
                            excelRow.eachCell((cell) => {
                                Object.assign(cell.style, headerRowStyle);
                            });
                        }
                        excelRow.eachCell((cell) => {
                            cell.border = {
                                top: { style: "thin" },
                                left: { style: "thin" },
                                bottom: { style: "thin" },
                                right: { style: "thin" },
                            };
                        });
                    }
                });
                ws.mergeCells("A1:G1");
                ws.mergeCells("A2:G2");
                ws.columns = columnsWidth;
            }

            // 完整排名sheet
            const allRankSheet = wb.addWorksheet("完整排名");
            allRankSheet.columns = [
                { width: 5 },
                { width: 20 },
                { width: 20 },
                { width: 25 },
                { width: 6 },
                { width: 6 },
                { width: 6 },
                { width: 6 },
                { width: 6 },
                { width: 8 },
            ];

            const allRankSheetTitle = allRankSheet.addRow([
                `完整排名-${cleanTitle}`,
            ]);
            allRankSheetTitle.height = 40;
            allRankSheetTitle.eachCell((cell) => {
                Object.assign(cell.style, titleRowStyle);
            });
            allRankSheetTitle.alignment = alignmentTitle;

            const excelRow = allRankSheet.addRow([
                "排名",
                "学校",
                "队名",
                "选手",
                "教练",
                "类型",
                "题数",
                "罚时",
                "校排",
                "队号",
                "备注",
            ]);
            excelRow.eachCell((cell) => {
                Object.assign(cell.style, headerRowStyle);
                cell.border = {
                    top: { style: "thin" },
                    left: { style: "thin" },
                    bottom: { style: "thin" },
                    right: { style: "thin" },
                };
            });

            this.awardData.forEach((row, index) => {
                const excelRow = allRankSheet.addRow([
                    row.displayRank || row.rank,
                    row.team.school,
                    row.team.name,
                    row.team.tmember,
                    row.team.coach,
                    this.getTeamTypeText(row.team?.tkind || row.team.tkind),
                    row.solved,
                    row.penalty,
                    isNaN(parseInt(row.team?.school_rank || row.team.school_rank))
                        ? ""
                        : row.team?.school_rank || row.team.school_rank,
                    row.team?.team_id,
                    "",
                ]);
                excelRow.eachCell((cell) => {
                    cell.border = {
                        top: { style: "thin" },
                        left: { style: "thin" },
                        bottom: { style: "thin" },
                        right: { style: "thin" },
                    };
                });
            });
            allRankSheet.mergeCells("A1:K1");

            // 导出文件
            const buffer = await wb.xlsx.writeBuffer();
            const blob = new Blob([buffer], {
                type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
            });
            const url = URL.createObjectURL(blob);
            const a = document.createElement("a");
            a.href = url;
            a.download = this.generateExportFilename('xlsx');
            a.click();
        } catch (error) {
            console.error("导出Excel失败:", error);
            alert("导出Excel失败，请检查数据");
        }
    }

    makeLineTeam(item) {
        let remark = "";
        if ("remark" in item) {
            remark = item.remark;
            item = item.item;
        }
        const team = item.team;
        return [
            item.displayRank,
            team.school,
            team.name,
            team.tmember,
            team.coach,
            team.team_id,
            remark,
        ];
    }

    validatePenalty(penalty) {
        if (!penalty) return 0;

        // 如果已经是数字（秒数），直接返回
        if (typeof penalty === "number") {
            return Math.floor(penalty);
        }

        // 如果是字符串，解析为秒数
        if (typeof penalty === "string") {
            // 处理 "HH:MM:SS" 格式
            if (penalty.includes(':')) {
                const parts = penalty.split(':');
                if (parts.length === 3) {
                    const hours = parseInt(parts[0]) || 0;
                    const minutes = parseInt(parts[1]) || 0;
                    const seconds = parseInt(parts[2]) || 0;
                    return hours * 3600 + minutes * 60 + seconds;
                } else if (parts.length === 2) {
                    // 处理 "MM:SS" 格式
                    const minutes = parseInt(parts[0]) || 0;
                    const seconds = parseInt(parts[1]) || 0;
                    return minutes * 60 + seconds;
                }
            }
            // 处理纯数字字符串
            const numValue = parseInt(penalty);
            return isNaN(numValue) ? 0 : numValue;
        }

        // 其他情况返回0
        return 0;
    }

    getTeamTypeText(tkind) {
        const typeMap = {
            0: "常规队",
            1: "女队",
            2: "打星队",
        };
        return typeMap[tkind] || "未知";
    }

    generateExportFilename(extension) {
        // 获取当前设置状态
        const isOneTwoThree = this.getSwitchOneTwoThree();
        const includeStar = this.getSwitchWithStarTeam();
        const acTeamBased = this.getSwitchAcTeamBased();
        
        // 生成状态描述
        const modeText = isOneTwoThree ? '一二三' : '金银铜';
        const starText = includeStar ? '含打星' : '不含打星';
        const baseText = acTeamBased ? '过题基数' : '总数基数';
        
        // 生成时间戳 (YYYYMMDDHHMMSS)
        const now = new Date();
        const timestamp = now.getFullYear().toString() +
            (now.getMonth() + 1).toString().padStart(2, '0') +
            now.getDate().toString().padStart(2, '0') +
            now.getHours().toString().padStart(2, '0') +
            now.getMinutes().toString().padStart(2, '0') +
            now.getSeconds().toString().padStart(2, '0');
        
        // 清洗比赛标题
        const cleanTitle = csg.sanitizeFilename(this.contestTitle);
        
        // 组装文件名
        const filename = `获奖名单-${cleanTitle}-${modeText}-${starText}-${baseText}-${timestamp}.${extension}`;
        
        return filename;
    }

    showError(message) {
        // 显示错误信息，隐藏表格
        const table = document.getElementById("award_table");
        if (table) {
            table.style.display = "none";
        }

        // 在表格位置显示错误信息
        const tableContainer = table?.parentElement;
        if (tableContainer) {
            tableContainer.innerHTML = `
                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>${message}
                </div>
            `;
        }
    }
}

// 导出 AwardSystem 类供外部使用
window.AwardSystem = AwardSystem;

// 全局格式化函数
function FormatterAward(value, row, index) {
    if (!row.awards || row.awards.length === 0) return "-";

    // 将获奖数组格式化为HTML显示
    return row.awards
        .map((award) => `<span class="badge bg-primary me-1 mb-1">${award}</span>`)
        .join("");
}

function FormatterAwardTeamName(value, row, index) {
    if(row?.name_en) {
        value += `<span class="en-text">${row?.name_en}</span>`;
    }
    return `<span style="white-space: normal; word-wrap: break-word; word-break: break-all;" class="bilingual-label">${value || "-"}</span>`;
}

function FormatterAwardTkind(value, row, index) {
    // 复用 rank.js 的 GetTeamTypeIcon 方法，flg_render=false 获取值后自定义
    if (window.rankSystem && window.rankSystem.GetTeamTypeIcon) {
        const iconKeyMap = {
            0: "team-regular", // 常规队
            1: "team-girl", // 女队
            2: "team-star", // 打星队
        };
        const iconKey = iconKeyMap[value] || "team-regular";

        // 配色：regular用primary，star用warning，girl用danger
        const colorMap = {
            0: "text-primary", // 常规队 - primary
            1: "text-danger", // 女队 - danger
            2: "text-warning", // 打星队 - warning
        };
        const colorClass = colorMap[value] || "text-primary";

        const iconMap = {
            0: "bi-flag-fill", // 常规队
            1: "bi-heart", // 女队
            2: "bi-star", // 打星队
        };
        const iconClass = iconMap[value] || "bi-flag-fill";

        return `<i class="bi ${iconClass} ${colorClass}" title="${value === 0 ? "常规队伍" : value === 1 ? "女队" : "打星队伍"
            }"></i>`;
    }

    // 降级方案
    const icons = {
        0: '<i class="bi bi-flag-fill text-primary" title="常规队伍"></i>',
        1: '<i class="bi bi-heart text-danger" title="女队"></i>',
        2: '<i class="bi bi-star text-warning" title="打星队伍"></i>',
    };
    return icons[value] || '<i class="bi bi-people text-muted"></i>';
}

function FormatterAwardPenalty(value, row, index) {
    const displayTime = RankToolFormatSecondsToMinutes(value);
    const fullTime = RankToolFormatSecondsToHMS(value);
    
    return `<span class="fw-bold" title="完整时间: ${fullTime}">${displayTime}</span>`;
}

function FormatterAwardSchool(value, row, index) {
    return `<div style="white-space: normal; word-wrap: break-word; word-break: break-all;">${value || "-"}</div>`;
}

function FormatterAwardMembers(value, row, index) {
    return `<div style="white-space: normal; word-wrap: break-word; word-break: break-all;">${value || "-"}</div>`;
}

function FormatterAwardCoach(value, row, index) {
    return `<div style="white-space: normal; word-wrap: break-word; word-break: break-all;">${value || "-"}</div>`;
}

function FormatterAwardTeamId(value, row, index) {
    return value || "-";
}

function FormatterAwardRank(value, row, index) {
    // 根据获奖情况

    let rankClass = "";
    if (row.flg_award == 1) {
        rankClass = "bg-warning text-dark"; // 金色
    } else if (row.flg_award == 2) {
        rankClass = "bg-secondary text-white"; // 银色
    } else if (row.flg_award == 3) {
        rankClass = "bg-danger text-white"; // 铜色
    } else {
        rankClass = "bg-light text-dark"; // 其他 - 灰色
    }
    return `<span class="badge ${rankClass}" style="min-width: 2.5rem; display: inline-block; text-align: center;">${row.rank}</span>`;
}

function FormatterAwardSolved(value, row, index) {
    return `<span class="fw-bold">${value}</span>`;
}
