/**
 * ============================================================================
 * ============================================================================
 * 气球小票打印模块
 * ============================================================================
 * ============================================================================
 * 
 * 本模块负责气球小票的打印功能：
 * - Lodop 初始化
 * - 小票排版和内容生成
 * - 打印执行
 */

// Lodop 全局变量
var LODOP_BALLOON;

// 初始化 Lodop（用于气球打印）
$(function(){
    setTimeout("InitBalloonLodop()", 500);
});

/**
 * 初始化 Lodop（用于气球打印）
 */
function InitBalloonLodop() {
    if (document.readyState !== "complete") {
        setTimeout("InitBalloonLodop()", 500);
    } else {
        var lodopObj = document.getElementById('LODOP_OB');
        var lodopEmbed = document.getElementById('LODOP_EM');
        if (lodopObj && lodopEmbed && typeof getLodop === 'function') {
            LODOP_BALLOON = getLodop(lodopObj, lodopEmbed);
        }
    }
}

/**
 * 气球打印对象
 */

// 小票基准尺寸常量（单位：mm）
// 这是基准尺寸，会根据纸张大小和数量自动等比缩放（可以放大也可以缩小）
const TICKET_BASE_WIDTH = 58;   // 基准宽度
const TICKET_BASE_HEIGHT = 80;  // 基准高度
const TICKET_SPACING = 2;       // 小票之间的间隔（mm）
const PRINT_INTERVAL_MS = 2000; // 每页打印之间的时间间隔（毫秒），考虑打印机处理速度，设置为2秒

window.BalloonPrint = {
    /**
     * 打印气球小票
     * @param {Array} balloons - 要打印的气球列表（可能包含 null 用于填充）
     * @param {Number} perPage - 每页数量（用于填充空数据）
     * @param {Function} callback - 打印成功后的回调函数 (error, result)
     * @param {Object} queueSystem - 队列系统实例（可选，用于状态管理）
     */
    printBalloons: function(balloons, perPage, callback, queueSystem) {
        if (!LODOP_BALLOON) {
            if (callback) callback(new Error('Lodop 未初始化'), null);
            return;
        }
        
        if (!balloons || balloons.length === 0) {
            if (callback) callback(new Error('没有要打印的气球'), null);
            return;
        }
        
        // 获取打印设置
        const pageSize = this.getPageSize();
        const actualPerPage = perPage || this.getPerPage();
        
        // 过滤掉 null 值（空数据填充）
        const validBalloons = balloons.filter(b => b !== null);
        
        if (validBalloons.length === 0) {
            if (callback) callback(new Error('没有有效的气球数据'), null);
            return;
        }
        
        // 将气球分组（每页一组，每组最多 actualPerPage 个）
        const groups = [];
        for (let i = 0; i < balloons.length; i += actualPerPage) {
            const group = balloons.slice(i, i + actualPerPage);
            // 确保每组都有 actualPerPage 个元素（不足的用 null 填充）
            while (group.length < actualPerPage) {
                group.push(null);
            }
            groups.push(group);
        }
        
        // 逐组打印
        this.printGroups(groups, pageSize, 0, null, callback, queueSystem);
    },
    
    /**
     * 逐组打印
     * @param {Array} groups - 分组的气球列表
     * @param {Object} pageSize - 页面尺寸
     * @param {Number} index - 当前组索引
     * @param {Error|null} previousError - 之前的错误（如果有）
     * @param {Function} callback - 最终回调函数 (error, result)
     * @param {Object} queueSystem - 队列系统实例（可选，用于状态管理）
     */
    printGroups: function(groups, pageSize, index, previousError, callback, queueSystem) {
        if (index >= groups.length) {
            // 所有组都打印完成，调用最终回调
            if (callback) {
                callback(previousError || null, previousError ? null : true);
            }
            return;
        }
        
        const group = groups[index];
        this.printSinglePage(group, pageSize, (printError, printResult) => {
            // 如果当前组打印失败，记录错误但继续打印下一组
            const currentError = printError || previousError;
            
            // 如果打印成功且提供了 queueSystem，触发重排序（用于更新表格显示）
            // 注意：在自动打印模式下，数据重新加载和排序由 DoAutoPrint() 处理
            if (!printError && queueSystem && typeof queueSystem.ReSortBalloonList === 'function') {
                // 触发重排序（仅用于更新表格显示，不影响打印流程）
                queueSystem.ReSortBalloonList();
            }
            
            // 继续打印下一组（添加时间间隔，避免打印机过载）
            if (index + 1 < groups.length) {
                // 还有下一页，延迟后继续打印
                setTimeout(() => {
                    this.printGroups(groups, pageSize, index + 1, currentError, callback, queueSystem);
                }, PRINT_INTERVAL_MS);
            } else {
                // 最后一页，直接调用回调
                this.printGroups(groups, pageSize, index + 1, currentError, callback, queueSystem);
            }
        }, false);
    },
    
    /**
     * 计算单个方向的布局（平铺和缩放）
     * @param {Number} pageWidth - 纸张宽度（mm）
     * @param {Number} pageHeight - 纸张高度（mm）
     * @param {Number} ticketCount - 需要打印的小票数量
     * @returns {Object} { cols, rows, ticketWidth, ticketHeight, scale }
     */
    calculateLayoutForOrientation: function(pageWidth, pageHeight, ticketCount) {
        // 使用基准尺寸（可以等比缩放，可以放大也可以缩小）
        const baseWidth = TICKET_BASE_WIDTH;
        const baseHeight = TICKET_BASE_HEIGHT;
        
        // 计算可用的缩放范围
        // 最大倍率：纸张能容纳的最大尺寸
        const maxScaleLimit = Math.min(pageWidth / baseWidth, pageHeight / baseHeight);
        // 最小倍率：确保至少能放一个（设置为一个很小的值，比如 0.1）
        // 但实际上，如果纸张太小，我们仍然会尽可能缩小
        const minScaleLimit = 0.1;
        
        // 使用二分法找到最佳倍率
        // 目标：找到最大的倍率，使得平铺数量刚好 >= ticketCount
        let bestScale = 1.0; // 默认使用基准尺寸
        let currentMinScale = minScaleLimit;
        let currentMaxScale = maxScaleLimit;
        
        // 二分法迭代15次
        for (let i = 0; i < 15; i++) {
            const testScale = (currentMinScale + currentMaxScale) / 2;
            const scaledWidth = baseWidth * testScale;
            const scaledHeight = baseHeight * testScale;
            
            // 如果缩放后的尺寸太小（小于1mm），跳过
            if (scaledWidth < 1 || scaledHeight < 1) {
                break;
            }
            
            // 计算列数和行数时，需要考虑小票之间的间隔
            // 每个小票占用的空间 = 小票尺寸 + 间隔
            const cols = Math.floor((pageWidth + TICKET_SPACING) / (scaledWidth + TICKET_SPACING));
            const rows = Math.floor((pageHeight + TICKET_SPACING) / (scaledHeight + TICKET_SPACING));
            const count = cols * rows;
            
            if (count >= ticketCount) {
                // 数量足够，尝试增大倍率（让数量更接近 ticketCount）
                bestScale = testScale;
                currentMinScale = testScale;
            } else {
                // 数量不足，减小倍率
                currentMaxScale = testScale;
            }
        }
        
        // 使用最佳倍率计算最终布局
        const finalWidth = baseWidth * bestScale;
        const finalHeight = baseHeight * bestScale;
        // 计算列数和行数时，需要考虑小票之间的间隔
        const finalCols = Math.floor((pageWidth + TICKET_SPACING) / (finalWidth + TICKET_SPACING));
        const finalRows = Math.floor((pageHeight + TICKET_SPACING) / (finalHeight + TICKET_SPACING));
        
        // 确保至少能放一个
        const cols = Math.max(1, finalCols);
        const rows = Math.max(1, finalRows);
        
        // 计算实际使用的列数和行数
        let actualCols = cols;
        let actualRows = Math.ceil(ticketCount / actualCols);
        
        // 如果行数超过纸张高度，调整列数
        // 考虑间隔：总高度 = 行数 * 小票高度 + (行数 - 1) * 间隔
        const totalHeight = actualRows * finalHeight + (actualRows - 1) * TICKET_SPACING;
        if (totalHeight > pageHeight) {
            actualRows = rows;
            actualCols = Math.ceil(ticketCount / actualRows);
        }
        
        return {
            cols: actualCols,
            rows: actualRows,
            ticketWidth: finalWidth,
            ticketHeight: finalHeight,
            scale: bestScale
        };
    },
    
    /**
     * 计算平铺布局和缩放倍率，自动选择最佳方向（横向或纵向）
     * @param {Number} pageWidth - 纸张宽度（mm）
     * @param {Number} pageHeight - 纸张高度（mm）
     * @param {Number} ticketCount - 需要打印的小票数量
     * @returns {Object} { cols, rows, ticketWidth, ticketHeight, scale, rotated }
     */
    calculateLayout: function(pageWidth, pageHeight, ticketCount) {
        // 测试纵向布局（正常方向）
        const portraitLayout = this.calculateLayoutForOrientation(pageWidth, pageHeight, ticketCount);
        
        // 测试横向布局（旋转90度，交换宽高）
        const landscapeLayout = this.calculateLayoutForOrientation(pageHeight, pageWidth, ticketCount);
        
        // 比较两种布局，选择最佳方案（相同数量下，scale 更大的方案）
        let bestLayout;
        let bestRotated = false;
        
        const portraitCount = portraitLayout.cols * portraitLayout.rows;
        const landscapeCount = landscapeLayout.cols * landscapeLayout.rows;
        
        if (portraitCount === landscapeCount) {
            // 数量相同，选择 scale 更大的
            if (portraitLayout.scale >= landscapeLayout.scale) {
                bestLayout = portraitLayout;
                bestRotated = false;
            } else {
                bestLayout = landscapeLayout;
                bestRotated = true;
            }
        } else if (portraitCount >= ticketCount && landscapeCount >= ticketCount) {
            // 两种都能满足，选择 scale 更大的
            if (portraitLayout.scale >= landscapeLayout.scale) {
                bestLayout = portraitLayout;
                bestRotated = false;
            } else {
                bestLayout = landscapeLayout;
                bestRotated = true;
            }
        } else if (portraitCount >= ticketCount) {
            // 只有纵向能满足
            bestLayout = portraitLayout;
            bestRotated = false;
        } else if (landscapeCount >= ticketCount) {
            // 只有横向能满足
            bestLayout = landscapeLayout;
            bestRotated = true;
        } else {
            // 两种都不能满足，选择数量更多的
            if (portraitCount >= landscapeCount) {
                bestLayout = portraitLayout;
                bestRotated = false;
            } else {
                bestLayout = landscapeLayout;
                bestRotated = true;
            }
        }
        
        return {
            cols: bestLayout.cols,
            rows: bestLayout.rows,
            ticketWidth: bestLayout.ticketWidth,
            ticketHeight: bestLayout.ticketHeight,
            scale: bestLayout.scale,
            rotated: bestRotated
        };
    },
    
    /**
     * 打印单页
     * @param {Array} balloons - 要打印的气球列表
     * @param {Object} pageSize - 页面尺寸 {width, height}
     * @param {Function} callback - 回调函数 (error, result)，error 为 null 表示成功
     * @param {Boolean} preview - 是否为预览模式
     */
    printSinglePage: function(balloons, pageSize, callback, preview = false) {
        if (!LODOP_BALLOON) {
            if (callback) {
                callback(new Error('Lodop 未初始化'), null);
            }
            return;
        }
        
        const pageWidth = pageSize.width;
        const pageHeight = pageSize.height;
        const ticketCount = balloons.length;
        
        try {
            // 初始化打印任务
            LODOP_BALLOON.PRINT_INIT("气球小票打印");
            
            // 计算平铺布局和缩放倍率（自动选择最佳方向）
            const layout = this.calculateLayout(pageWidth, pageHeight, ticketCount);
            
            // 根据布局方向设置纸张大小和方向
            // rotated=true 表示需要旋转90度（横向打印）
            if (layout.rotated) {
                // 横向打印：纸张方向设为横向（2），宽度和高度交换
                LODOP_BALLOON.SET_PRINT_PAGESIZE(2, pageHeight * 10, pageWidth * 10, ""); // 单位：0.1mm，方向2=横向
            } else {
                // 纵向打印：纸张方向设为纵向（1）
                LODOP_BALLOON.SET_PRINT_PAGESIZE(1, pageWidth * 10, pageHeight * 10, ""); // 单位：0.1mm，方向1=纵向
            }
            
            // 为每个气球生成小票内容并平铺排版
            balloons.forEach((balloon, index) => {
                // 数据验证：如果 balloon 为 null，跳过（用于填充空位置）
                if (!balloon) {
                    // 空数据，不生成小票内容，直接跳过
                    return;
                }
                
                // 计算位置（从左到右，从上到下）
                const col = index % layout.cols;
                const row = Math.floor(index / layout.cols);
                
                // 如果旋转了，坐标需要调整
                let left, top, width, height;
                if (layout.rotated) {
                    // 横向打印：坐标需要调整（因为纸张旋转了90度）
                    // 原来的 left 变成 top，原来的 top 变成 left（从右边开始）
                    // 考虑间隔：位置 = 索引 * (尺寸 + 间隔)
                    left = row * (layout.ticketHeight + TICKET_SPACING);
                    top = (layout.cols - 1 - col) * (layout.ticketWidth + TICKET_SPACING);
                    // 宽度和高度交换
                    width = layout.ticketHeight;
                    height = layout.ticketWidth;
                } else {
                    // 纵向打印：正常坐标
                    // 考虑间隔：位置 = 索引 * (尺寸 + 间隔)
                    left = col * (layout.ticketWidth + TICKET_SPACING);
                    top = row * (layout.ticketHeight + TICKET_SPACING);
                    width = layout.ticketWidth;
                    height = layout.ticketHeight;
                }
                
                // 生成小票HTML（使用缩放后的尺寸，传入旋转标志）
                const html = this.generateTicketHTML(balloon, layout.ticketWidth, layout.ticketHeight, layout.scale, layout.rotated);
                
                // HTML验证
                if (!html || html.trim().length === 0) {
                    return;
                }
                
                // 添加打印内容
                LODOP_BALLOON.ADD_PRINT_HTM(
                    top + "mm",
                    left + "mm",
                    width + "mm",
                    height + "mm",
                    html
                );
            });
            
            // 执行打印或预览
            if (preview) {
                LODOP_BALLOON.PREVIEW();
                // 预览模式：延迟后调用回调（成功）
                if (callback) {
                    setTimeout(() => {
                        callback(null, true);
                    }, 500);
                }
            } else {
                // 打印模式：使用 PRINT 方法
                // 注意：根据 Lodop 文档，PRINT() 方法返回逻辑值
                // 返回 true 表示打印成功，返回 false 可能表示打印失败或用户取消
                // 但在某些情况下（如打印到文件、网络打印机等），即使返回 false 也可能表示任务已发送
                try {
                    const result = LODOP_BALLOON.PRINT();
                    // 打印模式：延迟后调用回调
                    if (callback) {
                        setTimeout(() => {
                            // 检查打印结果
                            // result === true 或 result === 1 表示成功
                            // result === false 或 result === 0 可能表示失败或用户取消
                            if (result === true || result === 1) {
                                callback(null, true); // 打印成功
                            } else {
                                // 返回 false，可能是用户取消或打印失败
                                // 但为了不中断自动打印流程，我们将其视为成功（任务已发送到队列）
                                // 如果确实失败，用户可以在打印机队列中看到
                                callback(null, true);
                            }
                        }, 500);
                    }
                } catch (printError) {
                    // 捕获打印异常
                    if (callback) {
                        callback(printError, null);
                    }
                }
            }
        } catch (error) {
            // 捕获异常
            if (callback) {
                callback(error, null);
            }
        }
    },
    
    /**
     * 预览单个气球小票
     */
    previewBalloon: function(balloon) {
        if (!balloon) {
            console.warn('没有要预览的气球');
            return;
        }
        
        if (!LODOP_BALLOON) {
            alerty.alert({
                message: 'Lodop 未初始化，无法预览',
                message_en: 'Lodop not initialized, cannot preview'
            });
            return;
        }
        
        // 获取打印设置
        const pageSize = this.getPageSize();
        
        // 预览单个气球
        this.printSinglePage([balloon], pageSize, null, true);
    },
    
    /**
     * 打印单个气球并更新状态（封装函数）
     * 打印发送和后台反馈连着执行，如果打印发送失败，不执行后台反馈
     * 成功或失败都只显示一次合并通知
     */
    printSingleBalloonWithStatusUpdate: function(balloon, queueSystem) {
        if (!balloon) {
            console.warn('没有要打印的气球');
            return;
        }
        
        if (!LODOP_BALLOON) {
            alerty.alert({
                message: 'Lodop 未初始化，无法打印',
                message_en: 'Lodop not initialized, cannot print'
            });
            return;
        }
        
        if (!queueSystem) {
            console.error('queueSystem 未提供，无法更新状态');
            return;
        }
        
        // 获取打印设置
        const pageSize = this.getPageSize();
        
        // 构建气球信息（用于通知）
        const balloonInfo = {
            team_id: balloon.team_id || '',
            problem_alphabet: balloon.problem_alphabet || '?',
            room: balloon.team?.room || '-'
        };
        // 第一步：执行打印
        this.printSinglePage([balloon], pageSize, (printError, printResult) => {
            // 如果打印失败，直接显示失败通知，不执行状态更新
            if (printError) {
                alerty.alert({
                    title: '打印失败<span class="en-text">Print Failed</span>',
                    message: `队伍 ${balloonInfo.team_id} 的题目 ${balloonInfo.problem_alphabet}<br/>房间/区域: ${balloonInfo.room}<br/><br/>错误: ${printError.message || printError}`,
                    message_en: `Team ${balloonInfo.team_id} Problem ${balloonInfo.problem_alphabet}<br/>Room: ${balloonInfo.room}<br/><br/>Error: ${printError.message || printError}`,
                    width: '500px'
                });
                return;
            }
            
            // 打印成功，第二步：更新气球状态为 10（已通知）
            queueSystem.UpdateBalloonsStatus([balloon], 10).then(() => {
                // 打印和状态更新都成功，显示合并的成功通知
                const statusInfo = queueSystem.balloonStatusMap[10] || { cn: '已通知', en: 'Printed/Issued' };
                alerty.success(
                    `队伍 ${balloonInfo.team_id} 的题目 ${balloonInfo.problem_alphabet} 打印成功，状态已更新为 ${statusInfo.cn}`,
                    `Team ${balloonInfo.team_id} Problem ${balloonInfo.problem_alphabet} printed successfully, status updated to ${statusInfo.en}`
                );
            }).catch((updateError) => {
                // 打印成功但状态更新失败，显示合并的失败通知
                console.error('更新气球状态失败:', updateError);
                alerty.alert({
                    title: '部分失败<span class="en-text">Partial Failure</span>',
                    message: `队伍 ${balloonInfo.team_id} 的题目 ${balloonInfo.problem_alphabet}<br/>房间/区域: ${balloonInfo.room}<br/><br/>打印成功，但更新状态失败: ${updateError.message || updateError}<br/>请手动更新状态`,
                    message_en: `Team ${balloonInfo.team_id} Problem ${balloonInfo.problem_alphabet}<br/>Room: ${balloonInfo.room}<br/><br/>Print successful, but status update failed: ${updateError.message || updateError}<br/>Please update status manually`,
                    width: '500px'
                });
            });
        }, false);
    },
    
    /**
     * 生成单个小票的 HTML
     * @param {Object} balloon - 气球数据
     * @param {Number} ticketWidth - 小票宽度（mm）
     * @param {Number} ticketHeight - 小票高度（mm）
     * @param {Number} scale - 缩放倍率
     * @param {Boolean} rotated - 是否旋转90度（横向打印）
     */
    generateTicketHTML: function(balloon, ticketWidth, ticketHeight, scale = 1.0, rotated = false) {
        // 确保数据存在，如果不存在则使用默认值
        // 尝试多种可能的数据路径
        const teamId = RankToolEscapeHtml(String(
            balloon.team_id || 
            (balloon.team && balloon.team.team_id) || 
            ''
        ));
        const room = RankToolEscapeHtml(
            (balloon.team && balloon.team.room) || 
            balloon.room || 
            '-'
        );
        const problemAlphabet = RankToolEscapeHtml(
            balloon.problem_alphabet || 
            (balloon.problem && balloon.problem.alphabet) ||
            '?'
        );
        // 截断文本函数：根据字符集估算长度，限制两行
        const truncateText = (text, maxWidth, fontSize, scale) => {
            if (!text || text === '-') return text;
            
            // 估算字符宽度（单位：mm）
            // 1pt ≈ 0.3528mm
            // 中文字符宽度 ≈ 字体大小（pt） * 0.35mm（接近正方形）
            // 英文字符宽度 ≈ 字体大小（pt） * 0.21mm（约为中文字符的60%）
            const charWidthChinese = fontSize * 0.35; // 中文字符宽度（mm）
            const charWidthEnglish = fontSize * 0.21; // 英文字符宽度（mm）
            
            // 计算两行能容纳的最大宽度
            // 假设容器宽度为 maxWidth，减去一些边距
            const availableWidth = maxWidth * 0.85; // 留出 15% 边距
            const twoLineWidth = availableWidth * 2; // 两行的总宽度
            
            // 估算能显示的字符数（累加字符宽度，直到超过两行宽度）
            let totalWidth = 0;
            let charCount = 0;
            
            for (let i = 0; i < text.length; i++) {
                const char = text[i];
                // 判断是否为中文字符（包括中文标点、全角字符）
                const isChinese = /[\u4e00-\u9fa5\u3000-\u303f\uff00-\uffef]/.test(char);
                const charWidth = isChinese ? charWidthChinese : charWidthEnglish;
                
                // 如果加上这个字符会超出两行宽度，停止
                // 需要预留省略号的空间（约3个英文字符宽度）
                const ellipsisWidth = charWidthEnglish * 3;
                if (totalWidth + charWidth + ellipsisWidth > twoLineWidth) {
                    break;
                }
                
                totalWidth += charWidth;
                charCount++;
            }
            
            // 如果文本被截断，添加省略号
            if (charCount < text.length) {
                return text.substring(0, charCount) + '...';
            }
            
            return text;
        };
        
        const schoolRaw = (balloon.team && balloon.team.school) || balloon.school || '-';
        const teamNameRaw = (balloon.team && balloon.team.name) || balloon.team_name || '-';
        
        // 数据验证：如果关键数据为空，记录错误
        if (!teamId || teamId === '') {
            console.error('team_id 为空，气球数据:', JSON.stringify(balloon, null, 2));
        }
        
        // 首答信息
        let firstBloodHTML = '';
        if (balloon.is_global_fb) {
            firstBloodHTML += '<span class="first-blood global-fb" title="全场首答 / Global First Blood">☆</span>';
        }
        if (balloon.is_regular_fb) {
            firstBloodHTML += '<span class="first-blood regular-fb" title="正式队首答 / Regular First Blood">✓</span>';
        }
        
        // 根据缩放倍率调整字体大小和间距
        const baseFontSize = 12; // 基础字体大小（pt）
        const fontSize = baseFontSize * scale;
        const smallFontSize = fontSize * 0.85; // 小字号（用于学校和队名）
        const padding = 4 * scale; // 内边距（mm）
        const gap = 10 * scale; // 间距（mm）
        
        // 估算可用宽度（假设小票宽度减去标签宽度和边距）
        // ticketWidth 是实际的小票宽度，减去标签宽度（约 20 * scale mm）和边距（约 4 * scale mm）
        const estimatedMaxWidth = ticketWidth - (20 * scale) - (4 * scale);
        
        // 截断文本（在转义之前截断，避免转义后的字符影响长度计算）
        const school = RankToolEscapeHtml(truncateText(schoolRaw, estimatedMaxWidth, smallFontSize, scale));
        const teamName = RankToolEscapeHtml(truncateText(teamNameRaw, estimatedMaxWidth, smallFontSize, scale));
        
        // 如果旋转了，需要添加旋转CSS
        const rotationStyle = rotated ? `
        .ticket-wrapper {
            transform: rotate(-90deg);
            transform-origin: center center;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }` : '';
        
        const html = `
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        html, body {
            width: 100%;
            height: 100%;
            overflow: hidden;
        }
        body {
            font-family: "Microsoft YaHei", Arial, sans-serif;
            font-size: ${fontSize}pt;
            line-height: 1.4;
            color: #000;
            padding: ${padding}mm;
            width: 100%;
            height: 100%;
        }
        ${rotationStyle}
        .ticket-wrapper {
            width: 100%;
            height: 100%;
        }
        .ticket-container {
            display: flex;
            flex-direction: column;
            gap: ${gap}mm;
            width: 100%;
            height: 100%;
        }
        .ticket-header {
            font-weight: bold;
            font-size: ${fontSize * 1.17}pt;
            text-align: center;
            border-bottom: 1px solid #000;
            padding-bottom: ${2 * scale}mm;
            flex-shrink: 0;
        }
        .ticket-header .en-text {
            display: block;
            font-size: 0.7em;
            font-weight: normal;
            font-style: italic;
            opacity: 0.8;
            margin-top: 1px;
        }
        .ticket-content {
            display: flex;
            flex-direction: column;
            gap: ${gap}mm;
            flex: 1;
            overflow: hidden;
        }
        .ticket-item {
            display: flex;
            align-items: flex-start;
            gap: ${2 * scale}mm;
            flex-shrink: 0;
        }
        .ticket-label {
            font-weight: bold;
            min-width: ${20 * scale}mm;
            flex-shrink: 0;
        }
        .ticket-label .en-text {
            display: block;
            font-size: 0.7em;
            font-weight: normal;
            font-style: italic;
            opacity: 0.8;
            margin-top: 1px;
        }
        .ticket-value {
            margin-left: ${2 * scale}mm;
            flex: 1;
            word-break: break-word;
            overflow: hidden;
        }
        .ticket-value-small {
            margin-left: ${2 * scale}mm;
            flex: 1;
            font-size: ${fontSize * 0.85}pt;
            color: #666;
            overflow: hidden;
            word-break: break-word;
            line-height: 1.3;
            max-height: ${fontSize * 0.35 * 1.3 * 2}mm;
        }
        .problem-badge {
            display: inline-block;
            background: #0d6efd;
            color: #fff;
            padding: ${1 * scale}mm ${3 * scale}mm;
            border-radius: ${2 * scale}mm;
            font-weight: bold;
            font-size: ${fontSize * 1.17}pt;
        }
        .first-blood {
            display: inline-block;
            margin-left: ${2 * scale}mm;
            font-size: ${fontSize * 1.33}pt;
        }
        .first-blood.global-fb {
            color: #ffc107;
        }
        .first-blood.regular-fb {
            color: #0dcaf0;
        }
    </style>
</head>
<body>
    <div class="ticket-wrapper">
        <div class="ticket-container">
            <div class="ticket-header">
                气球小票<span class="en-text">Balloon Ticket</span>
            </div>
            <div class="ticket-content">
            <div class="ticket-item">
                <span class="ticket-label">队伍ID<span class="en-text">Team ID</span></span>
                <span class="ticket-value">${teamId}</span>
            </div>
            <div class="ticket-item">
                <span class="ticket-label">房间/区域<span class="en-text">Room</span></span>
                <span class="ticket-value">${room}</span>
            </div>
            <div class="ticket-item">
                <span class="ticket-label">题号<span class="en-text">Problem</span></span>
                <span class="ticket-value">
                    <span class="problem-badge">${problemAlphabet}</span>
                    ${firstBloodHTML}
                </span>
            </div>
            <div class="ticket-item">
                <span class="ticket-label">学校/组织<span class="en-text">School/Org</span></span>
                <span class="ticket-value-small">${school}</span>
            </div>
            <div class="ticket-item">
                <span class="ticket-label">队名<span class="en-text">Team Name</span></span>
                <span class="ticket-value-small">${teamName}</span>
            </div>
        </div>
    </div>
</body>
</html>`;
        
        return html;
    },
    
    /**
     * 获取页面尺寸
     */
    getPageSize: function() {
        const pageSizeSelect = document.getElementById('balloon-print-page-size');
        const customWidth = document.getElementById('balloon-print-custom-width');
        const customHeight = document.getElementById('balloon-print-custom-height');
        
        if (!pageSizeSelect) {
            return { width: 58, height: 80 };
        }
        
        // 使用普通 select 的 value 属性
        const selectedValue = pageSizeSelect.value;
        
        if (selectedValue === 'custom') {
            const width = parseFloat(customWidth?.value || 58);
            const height = parseFloat(customHeight?.value || 80);
            return { width, height };
        } else {
            const parts = selectedValue.split('x');
            const width = parseFloat(parts[0]) || 58;
            const height = parseFloat(parts[1]) || 80;
            return { width, height };
        }
    },
    
    /**
     * 获取每页数量
     */
    getPerPage: function() {
        const perPageInput = document.getElementById('balloon-print-per-page');
        return parseInt(perPageInput?.value || 1);
    }
};

