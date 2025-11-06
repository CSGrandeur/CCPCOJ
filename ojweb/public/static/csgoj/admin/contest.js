/**
 * 比赛编辑页面 JavaScript
 * Contest Edit Page JavaScript
 * 
 * 功能模块：
 * 1. 气球颜色管理 - BalloonColorManager 类
 * 2. 比赛类型选择 - 公开/加密/私有/标准
 * 3. 题目选择器集成 - 支持新的题号输入组件
 * 4. 表单验证 - 集成 FormValidationTip
 */

$(document).ready(function() {
    
    // ========================================
    // 气球颜色管理类
    // ========================================
    class BalloonColorManager {
        constructor() {
            this.currentEditingIndex = -1; // 当前编辑的颜色索引
            this.colorModal = null; // 颜色编辑模态框实例
            this.problemColorMap = new Map(); // 题号到颜色的映射，确保颜色跟随题号
            this.init();
        }
        
        init() {
            this.setupEventListeners();
            this.initColorModal();
            this.updatePreview();
        }
        
        setupEventListeners() {
            // 颜色输入框事件 - 实时更新预览，失焦时校验
            $('#balloon_colors').on('input', (e) => {
                this.updatePreviewFromInput();
            });
            
            $('#balloon_colors').on('blur', (e) => {
                this.validateAndUpdateColors();
            });
            
            // 重新随机颜色按钮
            $('#randomizeColorsBtn').on('click', () => {
                this.randomizeAllColors();
            });
            
            // 清空颜色按钮
            $('#clearColorsBtn').on('click', () => {
                this.clearAllColors();
            });
            
            // 颜色块点击事件
            $(document).on('click', '.balloon-color-item', (e) => {
                const index = $(e.currentTarget).data('index');
                this.openColorModal(index);
            });
        }
        
        initColorModal() {
            this.colorModal = new bootstrap.Modal('#balloonColorModal');
            
            // 颜色选择器变化
            $('#colorPicker').on('input', (e) => {
                const color = e.target.value;
                $('#colorInput').val(color);
                this.updateColorPreview(color);
                this.updateColorComparisonFromInput();
            });
            
            // 颜色选择器键盘事件
            $('#colorPicker').on('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.applyColor();
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    this.colorModal.hide();
                }
            });
            
            // 颜色输入框变化
            $('#colorInput').on('input', (e) => {
                const color = e.target.value;
                if (this.isValidColor(color)) {
                    $('#colorPicker').val(this.normalizeColorToHex(color));
                    this.updateColorPreview(color);
                }
                this.updateColorComparisonFromInput();
            });
            
            // 键盘事件处理
            $('#colorInput').on('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.applyColor();
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    this.colorModal.hide();
                }
            });
            
            // 应用颜色
            $('#applyColorBtn').on('click', () => {
                this.applyColor();
            });
            
            // 预设颜色点击
            this.initPresetColors();
        }
        
        openColorModal(index) {
            this.currentEditingIndex = index;
            const problems = this.getProblemsArray();
            const problemId = problems[index];
            const letter = String.fromCharCode('A'.charCodeAt(0) + index);
            const currentColor = this.getColorAtIndex(index);
            
            // 更新模态框标题中的题号信息
            $('#modalProblemLetter').text(letter);
            $('#modalProblemId').text(problemId);
            
            // 设置颜色选择器和输入框
            $('#colorPicker').val(this.normalizeColorToHex(currentColor));
            $('#colorInput').val(currentColor);
            
            // 更新颜色预览对比
            this.updateColorComparison(currentColor, currentColor);
            
            this.colorModal.show();
            
            // 模态框打开后，聚焦到输入框
            setTimeout(() => {
                $('#colorInput').focus().select();
            }, 300);
        }
        
        applyColor() {
            const color = $('#colorInput').val().trim();
            if (this.isValidColor(color)) {
                this.setColorAtIndex(this.currentEditingIndex, color);
                this.updatePreview();
                this.colorModal.hide();
            } else {
                alerty.warning({
                    message: '请输入有效的颜色值',
                    message_en: 'Please enter a valid color value'
                });
            }
        }
        
        validateAndUpdateColors() {
            const input = $('#balloon_colors').val().trim();
            const problems = this.getProblemsArray();
            
            if (!input) {
                this.updatePreview();
                return;
            }
            
            const colors = input.split(',').map(c => c.trim()).filter(c => c);
            const validColors = [];
            const invalidColors = [];
            let hasCorrections = false;
            let hasRestorations = false;
            
            // 处理每个颜色
            problems.forEach((problemId, index) => {
                const color = colors[index] || '';
                const originalColor = this.problemColorMap.get(problemId);
                
                if (color) {
                    if (this.isValidColor(color)) {
                        const normalizedColor = this.normalizeColorFormat(color);
                        validColors.push(normalizedColor);
                        // 检查是否有格式修正（如大小写转换）
                        if (normalizedColor !== color) {
                            hasCorrections = true;
                        }
                    } else {
                        // 尝试自动修正常见错误
                        const correctedColor = this.attemptColorCorrection(color);
                        if (correctedColor) {
                            validColors.push(correctedColor);
                            hasCorrections = true;
                        } else {
                            // 无效颜色，恢复原颜色
                            if (originalColor) {
                                validColors.push(originalColor);
                                hasRestorations = true;
                            } else {
                                invalidColors.push(color);
                            }
                        }
                    }
                } else {
                    // 没有颜色，使用原颜色或空值
                    if (originalColor) {
                        validColors.push(originalColor);
                    } else {
                        validColors.push('');
                    }
                }
            });
            
            // 更新输入框为修正后的颜色
            const correctedInput = validColors.join(',');
            if (correctedInput !== input) {
                $('#balloon_colors').val(correctedInput);
            }
            
            // 显示相应的提示信息
            if (invalidColors.length > 0) {
                alerty.warning({
                    message: `检测到无效颜色：${invalidColors.join(', ')}，已自动移除`,
                    message_en: `Invalid colors detected: ${invalidColors.join(', ')}, automatically removed`
                });
            } else if (hasRestorations) {
                alerty.info({
                    message: '无效颜色已恢复为原颜色',
                    message_en: 'Invalid colors restored to original colors'
                });
            } else if (hasCorrections) {
                // 静默修正格式，不显示提示
            }
            
            // 更新映射以反映修正后的颜色
            problems.forEach((problemId, index) => {
                if (validColors[index]) {
                    this.problemColorMap.set(problemId, validColors[index]);
                }
            });
            this.updatePreview();
        }
        
        attemptColorCorrection(color) {
            // 完全使用统一的规范化函数，所有修正逻辑已在其中实现
            return NormalizeColorForInput(color);   // global.js
        }
        
        updatePreview() {
            const problems = this.getProblemsArray();
            
            // 如果映射为空，先尝试从输入框加载现有颜色
            if (this.problemColorMap.size === 0) {
                this.buildProblemColorMap(problems);
            }
            
            // 检查是否有新题目需要生成颜色
            const existingColors = this.getColorsArray();
            const needsNewColors = existingColors.length === 0 || problems.length > existingColors.length;
            
            const previewHtml = problems.map((problemId, index) => {
                // 从映射中获取颜色，确保颜色跟随题号
                let color = this.problemColorMap.get(problemId);
                if (!color) {
                    if (needsNewColors) {
                        // 需要新颜色时，生成随机颜色
                        color = this.generateRandomColor();
                        this.problemColorMap.set(problemId, color);
                    } else {
                        // 不需要新颜色时，使用空值
                        color = '';
                    }
                }
                
                const normalizedColor = this.normalizeColorFormat(color) || color || '#CCCCCC';
                const letter = String.fromCharCode('A'.charCodeAt(0) + index);
                
                return `
                    <div class="balloon-color-item" data-index="${index}" title="题号: ${problemId} | 颜色: ${normalizedColor}">
                        <div class="balloon-color-block" 
                             style="background-color: ${normalizedColor};" 
                             data-letter="${letter}"></div>
                        <div class="balloon-color-info">
                            <div class="balloon-color-problem-info">${problemId}</div>
                            <div class="balloon-color-value" title="${normalizedColor}">${normalizedColor}</div>
                    </div>
                    </div>
                `;
            }).join('');
            
            // 更新输入框为当前题号顺序的颜色数组
            this.updateInputFromMap(problems);
            $('#balloon_color_preview').html(previewHtml);
        }
        
        // 构建题号到颜色的映射
        buildProblemColorMap(problems) {
            const colors = this.getColorsArray();
            
            // 如果输入框有内容，按位置映射（支持用户直接修改输入框）
            if (colors.length > 0) {
                problems.forEach((problemId, index) => {
                    if (colors[index]) {
                        // 检查颜色是否有效，如果无效则生成随机颜色
                        if (this.isValidColor(colors[index])) {
                            this.problemColorMap.set(problemId, colors[index]);
                        } else {
                            this.problemColorMap.set(problemId, this.generateRandomColor());
                        }
                    }
                });
            }
            
            // 为没有颜色的题号生成随机颜色
            // 1. 如果没有现有颜色数据，为所有题目生成随机颜色
            // 2. 如果有现有颜色数据但题目数量增加了，为新题目生成随机颜色
            const needsRandomColors = colors.length === 0 || problems.length > colors.length;
            if (needsRandomColors) {
                problems.forEach(problemId => {
                    if (!this.problemColorMap.has(problemId)) {
                        this.problemColorMap.set(problemId, this.generateRandomColor());
                    }
                });
            }
        }
        
        // 根据当前题号顺序更新输入框
        updateInputFromMap(problems) {
            const colors = problems.map(problemId => this.problemColorMap.get(problemId) || '');
            $('#balloon_colors').val(colors.join(','));
        }
        
        // 实时预览更新（不校验，允许用户自由输入）
        updatePreviewFromInput() {
            const input = $('#balloon_colors').val().trim();
            const problems = this.getProblemsArray();
            
            if (!input) {
                // 输入为空时，显示默认随机颜色
                this.updatePreview();
                return;
            }
            
            const colors = input.split(',').map(c => c.trim()).filter(c => c);
            
            const previewHtml = problems.map((problemId, index) => {
                let color = colors[index] || '';
                let displayColor = color;
                let originalColor = this.problemColorMap.get(problemId);
                
                // 尝试显示颜色，即使格式不正确
                if (color) {
                    const normalizedColor = this.normalizeColorFormat(color);
                    if (normalizedColor) {
                        displayColor = normalizedColor;
                        // 实时预览时不更新映射，只在失焦校验时更新
                    } else {
                        // 尝试自动修正用于显示
                        const correctedColor = this.attemptColorCorrection(color);
                        if (correctedColor) {
                            displayColor = correctedColor;
                        } else {
                            // 无效颜色，显示原颜色或灰色
                            if (originalColor) {
                                displayColor = this.normalizeColorFormat(originalColor) || originalColor;
                                color = originalColor; // 显示原颜色值
                            } else {
                                displayColor = '#CCCCCC';
                                color = '无效颜色';
                            }
                        }
                    }
                } else {
                    // 没有颜色，使用原颜色或生成随机颜色
                    if (originalColor) {
                        displayColor = this.normalizeColorFormat(originalColor) || originalColor;
                        color = originalColor;
                    } else {
                        displayColor = this.generateRandomColor();
                        color = '随机';
                    }
                }
                
                const letter = String.fromCharCode('A'.charCodeAt(0) + index);
                
                return `
                    <div class="balloon-color-item" data-index="${index}" title="题号: ${problemId} | 颜色: ${color}">
                        <div class="balloon-color-block" 
                             style="background-color: ${displayColor};" 
                             data-letter="${letter}"></div>
                        <div class="balloon-color-info">
                            <div class="balloon-color-problem-info">${problemId}</div>
                            <div class="balloon-color-value" title="${color}">${color}</div>
                        </div>
                    </div>
                `;
            }).join('');
            
            $('#balloon_color_preview').html(previewHtml);
        }
        
        initPresetColors() {
            // 前两行：16个单词颜色（常见且区分度高，气球常用色）
            const wordColors = [
                { color: 'red', label: '红色 / Red' },
                { color: 'blue', label: '蓝色 / Blue' },
                { color: 'green', label: '绿色 / Green' },
                { color: 'yellow', label: '黄色 / Yellow' },
                { color: 'orange', label: '橙色 / Orange' },
                { color: 'purple', label: '紫色 / Purple' },
                { color: 'pink', label: '粉色 / Pink' },
                { color: 'cyan', label: '青色 / Cyan' },
                { color: 'magenta', label: '品红 / Magenta' },
                { color: 'lime', label: '酸橙绿 / Lime' },
                { color: 'navy', label: '海军蓝 / Navy' },
                { color: 'teal', label: '青绿色 / Teal' },
                { color: 'gold', label: '金色 / Gold' },
                { color: 'silver', label: '银色 / Silver' },
                { color: 'white', label: '白色 / White' },
                { color: 'black', label: '黑色 / Black' },
            ];
            
            // 后两行：16个气球常用配色（十六进制，高区分度）
            const macaronColors = [
                { color: '#FF6B6B', label: '粉红 / Pink' },      // 粉红
                { color: '#4ECDC4', label: '青绿 / Turquoise' },  // 青绿
                { color: '#45B7D1', label: '天蓝 / Sky Blue' },   // 天蓝
                { color: '#96CEB4', label: '薄荷绿 / Mint' },     // 薄荷绿
                { color: '#FFEAA7', label: '淡黄 / Light Yellow' }, // 淡黄
                { color: '#DDA0DD', label: '淡紫 / Lavender' },   // 淡紫
                { color: '#F7DC6F', label: '金黄 / Golden Yellow' }, // 金黄
                { color: '#85C1E9', label: '浅蓝 / Light Blue' },  // 浅蓝
                { color: '#F1948A', label: '玫瑰粉 / Rose Pink' }, // 玫瑰粉
                { color: '#82E0AA', label: '浅绿 / Light Green' }, // 浅绿
                { color: '#BB8FCE', label: '紫罗兰 / Violet' },    // 紫罗兰
                { color: '#F8C471', label: '橙色 / Orange' },      // 橙色
                { color: '#AED6F1', label: '天蓝色 / Sky Blue' },  // 天蓝色
                { color: '#A9DFBF', label: '春绿色 / Spring Green' }, // 春绿色
                { color: '#FAD7A0', label: '桃色 / Peach' },       // 桃色
                { color: '#D7BDE2', label: '淡紫色 / Pale Purple' }  // 淡紫色
            ];
            
            // 生成单词颜色的HTML（分为两行，每行8个）
            const generateWordColorItem = (item) => {
                // white需要特殊处理，添加阴影和边框以便显示
                const isWhite = item.color.toLowerCase() === 'white';
                const borderStyle = isWhite ? 'border: 1px solid #ccc;' : '';
                const shadowStyle = isWhite ? 'box-shadow: 0 1px 3px rgba(0,0,0,0.2);' : '';
                return `
                    <div class="preset-color-item">
                        <button type="button" class="btn preset-color-btn" 
                                style="background-color: ${item.color}; ${borderStyle} ${shadowStyle}" 
                                data-color="${item.color}"
                                title="${item.label}">
                        </button>
                        <div class="preset-color-label">${item.color}</div>
                    </div>
                `;
            };
            
            // 第一行：前8个单词颜色
            const wordColorsRow1 = wordColors.slice(0, 8).map(generateWordColorItem).join('');
            // 第二行：后8个单词颜色
            const wordColorsRow2 = wordColors.slice(8, 16).map(generateWordColorItem).join('');
            
            // 生成马卡龙颜色的HTML（分为两行，每行8个）
            const generateMacaronColorItem = (item) => `
                <div class="preset-color-item">
                    <button type="button" class="btn preset-color-btn" 
                            style="background-color: ${item.color};" 
                            data-color="${item.color}"
                            title="${item.label}">
                    </button>
                    <div class="preset-color-label">${item.color}</div>
                </div>
            `;
            
            // 第三行：前8个马卡龙颜色
            const macaronColorsRow1 = macaronColors.slice(0, 8).map(generateMacaronColorItem).join('');
            // 第四行：后8个马卡龙颜色
            const macaronColorsRow2 = macaronColors.slice(8, 16).map(generateMacaronColorItem).join('');
            
            // 组合所有颜色HTML（4行，每行8个）
            const presetHtml = `
                <div class="preset-colors-row">
                    ${wordColorsRow1}
                </div>
                <div class="preset-colors-row">
                    ${wordColorsRow2}
                </div>
                <div class="preset-colors-row">
                    ${macaronColorsRow1}
                </div>
                <div class="preset-colors-row">
                    ${macaronColorsRow2}
                </div>
            `;
            
            $('#presetColors').html(presetHtml);
            
            // 预设颜色点击事件
            $('#presetColors .preset-color-btn').on('click', (e) => {
                const color = $(e.currentTarget).data('color');
                $('#colorPicker').val(NormalizeColorForDisplay(color) || color);
                $('#colorInput').val(color);
                this.updateColorPreview(color);
            });
        }
        
        updateColorPreview(color) {
            // 用于显示预览，使用显示模式（更宽松）
            const normalizedColor = NormalizeColorForDisplay(color) || this.normalizeColorFormat(color) || color || '#CCCCCC';
            $('#colorPreviewCircle').css('background-color', normalizedColor);
            $('#colorPreviewText').text(normalizedColor);
        }
        
        updateColorComparison(originalColor, newColor) {
            // 用于显示对比，使用显示模式（更宽松）
            const normalizedOriginal = NormalizeColorForDisplay(originalColor) || this.normalizeColorFormat(originalColor) || originalColor || '#CCCCCC';
            const normalizedNew = NormalizeColorForDisplay(newColor) || this.normalizeColorFormat(newColor) || newColor || '#CCCCCC';
            
            // 更新原颜色
            $('#originalColorCircle').css('background-color', normalizedOriginal);
            $('#originalColorText').text(normalizedOriginal);
            
            // 更新新颜色
            $('#colorPreviewCircle').css('background-color', normalizedNew);
            $('#colorPreviewText').text(normalizedNew);
        }
        
        updateColorComparisonFromInput() {
            const problems = this.getProblemsArray();
            const problemId = problems[this.currentEditingIndex];
            const originalColor = this.problemColorMap.get(problemId) || this.generateRandomColor();
            const newColor = $('#colorInput').val().trim() || originalColor;
            
            this.updateColorComparison(originalColor, newColor);
        }
        
        getColorsArray() {
            const input = $('#balloon_colors').val().trim();
            return input ? input.split(',').map(c => c.trim()).filter(c => c) : [];
        }
        
        getProblemsArray() {
            // 从新的题号输入组件中读取隐藏输入框
            const hidden = document.querySelector('#contest_problem_input input[name="problems"]') || document.querySelector('input[name="problems"]');
            const val = hidden && typeof hidden.value === 'string' ? hidden.value.trim() : '';
            return val ? val.split(',').map(p => p.trim()).filter(p => p) : [];
        }
        
        getColorAtIndex(index) {
            const colors = this.getColorsArray();
            return colors[index] || this.generateRandomColor();
        }
        
        setColorAtIndex(index, color) {
            const problems = this.getProblemsArray();
            
            if (index >= 0 && index < problems.length) {
                const problemId = problems[index];
                const normalizedColor = this.normalizeColorFormat(color);
                this.problemColorMap.set(problemId, normalizedColor);
                this.updateInputFromMap(problems);
            }
        }
        
        randomizeAllColors() {
            const problems = this.getProblemsArray();
            
            // 清空现有映射，为每个题号生成新的随机颜色
            this.problemColorMap.clear();
            problems.forEach(problemId => {
                this.problemColorMap.set(problemId, this.generateRandomColor());
            });
            
            // 更新输入框为新的随机颜色
            this.updateInputFromMap(problems);
            this.updatePreview();
            
            alerty.success({
                message: '已重新生成随机颜色',
                message_en: 'Random colors generated successfully'
            });
        }
        
        clearAllColors() {
            const problems = this.getProblemsArray();
            
            // 清空所有题号的颜色映射
            this.problemColorMap.clear();
            
            $('#balloon_colors').val('');
            this.updatePreview();
            
            alerty.success({
                message: '已清空所有颜色',
                message_en: 'All colors cleared successfully'
            });
        }

        
        // 生成随机颜色
        generateRandomColor() {
            return '#' + Math.floor(Math.random() * 16777216).toString(16).toUpperCase().padStart(6, '0');
        }
        
        // 验证颜色是否有效（使用统一的颜色规范化函数）
        isValidColor(color) {
            return NormalizeColorForInput(color) !== null;
        }
    
        // 规范化颜色格式（用于输入验证，保持严格的验证逻辑）
        normalizeColorFormat(color) {
            return NormalizeColorForInput(color);
        }
        
        // 将颜色转换为十六进制格式（用于显示）
        normalizeColorToHex(color) {
            // 优先使用输入验证模式，如果失败则使用显示模式
            const normalized = NormalizeColorForInput(color) || NormalizeColorForDisplay(color);
            return (normalized && normalized.startsWith('#')) ? normalized : '#FF6B6B';
        }
    }
    
    // ========================================
    // 气球颜色管理实例和全局函数
    // ========================================
    let balloonColorManager = null;
    
    // 气球颜色预览初始化
    function InitBalloonColorPreview() {
        if (!balloonColorManager) {
            balloonColorManager = new BalloonColorManager();
                } else {
            balloonColorManager.updatePreview();
        }
    }
    
    // 题目列表更新处理
    function updateProblemListDisplay() {
        InitBalloonColorPreview();
    }
    
    // ========================================
    // 题目选择器集成
    // ========================================
    // 题目选择器回调函数
    window.onProblemSelectionConfirm = function(selectedProblemIds) {
        // 优先使用新的题号输入组件
        const problemInputComponent = window.ProblemInputComponents?.get('contest_problem_input');
        if (problemInputComponent) {
            problemInputComponent.addProblems(selectedProblemIds);
            
            // 更新气球颜色管理器，为新添加的题目生成随机颜色
            if (balloonColorManager) {
                balloonColorManager.updatePreview();
            }
            
            alerty.success({
                message: `已添加 ${selectedProblemIds.length} 个题目到比赛`,
                message_en: `Added ${selectedProblemIds.length} problems to contest`
            });
            return;
        }
        
        // 组件未初始化，直接报错以便调试定位
        alerty.warning({
            message: '题目输入组件未初始化，请检查页面引入顺序与初始化逻辑',
            message_en: 'Problem input component not initialized. Check script order and init logic'
        });
        return;
    };
    
    
    // 监听新的题号输入组件的隐藏输入框变化
    $(document).on('input change', 'input[name="problems"]', function() {
        updateProblemListDisplay();
    });
    
    // ========================================
    // 比赛类型选择处理
    // ========================================
    // 比赛类型选择处理
    $('.contest-type-btn').on('click', function() {
        // 更新按钮状态
        $('.contest-type-btn').removeClass('active').attr('aria-pressed', 'false');
        $(this).addClass('active').attr('aria-pressed', 'true');
        
        // 获取配置
        const privateValue = $(this).data('private');
        const isEncrypted = $(this).data('encrypted');
        
        // 更新表单状态
        $('#private_value').val(isEncrypted ? 0 : privateValue);
        
        if (isEncrypted) {
            $('#password_group').show();
            $('#password').prop('disabled', false);
        } else {
            $('#password_group').hide();
            $('#password').val('').prop('disabled', true);
        }
        
        // 更新布局
        updateUsersColumnVisibility(privateValue, isEncrypted);
        $(this).blur();
    });
    
    // 更新参赛用户列显示状态
    function updateUsersColumnVisibility(privateValue, isEncrypted) {
        const isStandard = (privateValue == 2);
        $('#users_column').toggle(!isStandard);
        $('#main_content_column').toggleClass('col-12', isStandard).toggleClass('col-md-9', !isStandard);
    }
    
    // ========================================
    // 全局函数暴露和初始化
    // ========================================
    // 暴露全局函数
    window.InitBalloonColorPreview = InitBalloonColorPreview;
    window.updateProblemListDisplay = updateProblemListDisplay;
    
    // 初始化气球颜色管理
    InitBalloonColorPreview();
    updateProblemListDisplay();
    
    // 初始化比赛类型选择
    const currentPrivate = $('#private_value').val();
    const hasPassword = $('#password').val().trim() !== '';
    
    $('.contest-type-btn').removeClass('active').attr('aria-pressed', 'false');
    
    if (hasPassword) {
        $('.contest-type-btn[data-encrypted="true"]').addClass('active').attr('aria-pressed', 'true');
        $('#password_group').show();
        updateUsersColumnVisibility(0, true);
    } else {
        $('.contest-type-btn[data-private="' + currentPrivate + '"]:not([data-encrypted])').addClass('active').attr('aria-pressed', 'true');
        $('#password_group').hide();
        updateUsersColumnVisibility(currentPrivate, false);
    }
    
});
