/**
 * ============================================================================
 * ============================================================================
 * Lodop 打印控制模块
 * ============================================================================
 * ============================================================================
 * 
 * 本模块包含所有 Lodop 打印相关的核心功能：
 * - Lodop 初始化
 * - 代码高亮处理
 * - 打印页面创建
 * - 打印/预览执行
 */

// Lodop 全局变量
var LODOP;

$(function(){
    setTimeout("InitLodop()", 500);
});

// 初始化 Lodop
function InitLodop() {
    if (document.readyState !== "complete") {
        setTimeout("InitLodop()", 500);
    } else {
        var lodopObj = document.getElementById('LODOP_OB');
        var lodopEmbed = document.getElementById('LODOP_EM');
        if (lodopObj && lodopEmbed && typeof getLodop === 'function') {
            LODOP = getLodop(lodopObj, lodopEmbed);
        }
    }
}

// 获取颜色模式设置
function getColorMode() {
    try {
        var colorMode = localStorage.getItem('print_color_mode');
        return colorMode !== 'false'; // 默认为彩色模式
    } catch(e) {
        return true; // 默认彩色模式
    }
}

// 获取 hljs 样式（彩色模式）
function getHljsColorStyles() {
    return `
/* GitHub theme for Highlight.js */
.hljs {
	display: block;
	overflow-x: auto;
	padding: 0.5em;
	color: #24292e;
	background: #ffffff;
}
.hljs-comment, .hljs-quote {
	color: #6a737d;
	font-style: italic;
}
.hljs-keyword, .hljs-selector-tag, .hljs-type {
	color: #d73a49;
}
.hljs-string, .hljs-number {
	color: #032f62;
}
.hljs-title, .hljs-section, .hljs-attribute, .hljs-literal, .hljs-template-tag, .hljs-template-variable, .hljs-type, .hljs-addition {
	color: #005cc5;
}
.hljs-selector-id, .hljs-selector-class {
	color: #6f42c1;
}
.hljs-meta, .hljs-meta-string {
	color: #032f62;
}
.hljs-emphasis {
	font-style: italic;
}
.hljs-strong {
	font-weight: bold;
}
.hljs-built_in, .hljs-builtin-name {
	color: #e36209;
}
.hljs-variable {
	color: #e36209;
}
.hljs-symbol, .hljs-bullet, .hljs-link {
	color: #032f62;
}
.hljs-regexp, .hljs-deletion {
	color: #d73a49;
}
.hljs-selector-attr, .hljs-selector-pseudo {
	color: #032f62;
}
.hljs-attr {
	color: #005cc5;
}
.hljs-function {
	color: #6f42c1;
}
.hljs-class .hljs-title {
	color: #6f42c1;
}
.hljs-tag {
	color: #22863a;
}
.hljs-name {
	color: #22863a;
}
.hljs-params {
	color: #24292e;
}
`;
}

// 获取 hljs 样式（黑白模式）
// 最佳实践：使用字体样式（粗体、斜体、下划线）和灰度值来区分代码元素
// 参考：黑白打印代码高亮最佳实践
// - 关键字：粗体
// - 常量：下划线
// - 字符串：斜体
// - 注释：斜体 + 浅灰色
// - 函数名：粗体
// - 变量：正常字体
function getHljsBWStyles() {
    return `
/* Black and white theme for Highlight.js - Print-friendly monochrome */
.hljs {
	display: block;
	overflow-x: auto;
	padding: 0.5em;
	color: #000000;
	background: #ffffff;
}
/* 注释：使用斜体和较浅的灰色，降低视觉优先级但仍保持可读性 */
.hljs-comment, .hljs-quote {
	color: #666666;
	font-style: italic;
}
/* 关键字：使用粗体黑色，突出重要语法元素 */
.hljs-keyword, .hljs-selector-tag, .hljs-type {
	color: #000000;
	font-weight: bold;
}
/* 内置函数和类型：使用粗体，与关键字保持一致 */
.hljs-built_in, .hljs-builtin-name {
	color: #000000;
	font-weight: bold;
}
/* 字符串：使用斜体和中等灰色，与关键字区分 */
.hljs-string, .hljs-template-tag, .hljs-template-variable {
	color: #333333;
	font-style: italic;
}
/* 常量字面量：使用下划线标识，增强在黑白打印中的辨识度 */
.hljs-literal {
	color: #000000;
	text-decoration: underline;
	font-weight: normal;
}
/* 数字常量：使用下划线标识，黑白打印最佳实践 */
.hljs-number {
	color: #000000;
	text-decoration: underline;
	font-weight: normal;
}
/* 函数名、类名、标题：使用粗体，与关键字区分但保持重要性 */
.hljs-title, .hljs-section, .hljs-function, .hljs-class .hljs-title {
	color: #000000;
	font-weight: bold;
}
/* 变量：使用正常字体，与函数名区分 */
.hljs-variable {
	color: #000000;
	font-weight: normal;
}
/* 属性、参数：使用正常字体 */
.hljs-attribute, .hljs-attr, .hljs-params {
	color: #000000;
	font-weight: normal;
}
/* 选择器、类名：使用正常字体 */
.hljs-selector-id, .hljs-selector-class {
	color: #000000;
	font-weight: normal;
}
/* 符号、链接等：使用正常字体 */
.hljs-symbol, .hljs-bullet, .hljs-link, .hljs-regexp, .hljs-deletion, .hljs-selector-attr, .hljs-selector-pseudo {
	color: #000000;
	font-weight: normal;
}
/* 元数据：使用中等灰色 */
.hljs-meta, .hljs-meta-string {
	color: #333333;
	font-weight: normal;
}
/* 强调：使用斜体 */
.hljs-emphasis {
	font-style: italic;
	color: #000000;
}
/* 加粗：使用粗体 */
.hljs-strong {
	font-weight: bold;
	color: #000000;
}
/* 标签和名称：使用正常字体 */
.hljs-tag, .hljs-name {
	color: #000000;
	font-weight: normal;
}
/* 添加的内容：使用正常字体 */
.hljs-addition {
	color: #000000;
	font-weight: normal;
}
`;
}

// 使用 hljs 高亮代码并按行分割（最佳实践：先高亮，再按行分割）
// 参考：https://github.com/wcoder/highlightjs-line-numbers.js
// 关键点：必须在 hljs.highlightAuto() 完成后再按行分割，否则行号计算会错误
function highlightCodeByLines(source) {
    // 先按行分割原始代码（用于确定行数）
    var sourceLines = source.split('\n');
    var totalLines = sourceLines.length;
    
    // 检查 hljs 是否可用
    if (typeof hljs === 'undefined') {
        // 如果没有 hljs，返回转义后的代码行数组
        return sourceLines.map(function(line) {
            return line.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        });
    }
    
    // 步骤1：先使用 hljs 自动检测语言并高亮整个代码块
    // 这样可以保持语法高亮的准确性（某些语法需要上下文才能正确高亮）
    // 重要：必须先完成高亮，再按行分割，否则行号会计算错误
    var highlighted = '';
    try {
        var autoResult = hljs.highlightAuto(source);
        highlighted = autoResult.value;
    } catch(e) {
        // 如果高亮失败，使用转义
        highlighted = source.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
    
    // 步骤2：按行分割高亮后的内容
    // highlight.js 的高亮结果按行分割，每行之间用 \n 分隔
    // 重要：必须在高亮完成后才能按行分割，否则行号会计算错误
    var highlightedLines = [];
    var tempLines = highlighted.split('\n');
    
    // 确保行数匹配原始代码行数
    // 这样可以保证行号与代码内容一一对应
    for (var i = 0; i < totalLines; i++) {
        if (i < tempLines.length) {
            // 获取当前行的 HTML 内容
            var lineHtml = tempLines[i] || ' ';
            // 如果行内容为空，至少保留一个空格，避免行号列错位
            if (lineHtml.trim() === '') {
                lineHtml = ' ';
            }
            highlightedLines.push(lineHtml);
        } else {
            // 如果高亮后的行数少于原始行数，补充空行
            // 这种情况通常不会发生，但为了安全起见还是处理一下
            highlightedLines.push(' ');
        }
    }
    return highlightedLines;
}

// 创建打印页面（完整的数据处理逻辑）
function CreatePrintPage(data) {
    // 获取颜色模式
    var isColorMode = getColorMode();
    var hljsStyles = isColorMode ? getHljsColorStyles() : getHljsBWStyles();
    
    // 从传入数据中获取所有信息（不再依赖全局变量）
    var school = DomSantize(data['school'] || '');
    var name = DomSantize(data['name'] || '');
    var room = DomSantize(data['room'] || '');
    var print_id = data['print_id'] || '';
    var team_id = data['team_id'] || '';
    var in_date = data.in_date || '';
    var contest_title = DomSantize(data['contest_title'] || '');
    
    // 获取当前时间
    var now = new Date();
    var printTime = now.getFullYear() + '-' + 
        pad0left(now.getMonth() + 1, 2, '0') + '-' + 
        pad0left(now.getDate(), 2, '0') + ' ' +
        pad0left(now.getHours(), 2, '0') + ':' + 
        pad0left(now.getMinutes(), 2, '0') + ':' + 
        pad0left(now.getSeconds(), 2, '0');
    
    // 处理代码
    var source = data['source'] || '';
    source = source.replace(/\t/g, '    '); // 替换制表符
    
    // 高亮代码并按行分割（使用自动检测）
    var highlightedLines = highlightCodeByLines(source);
    
    // 使用 table 布局生成带行号的代码 HTML（最佳实践）
    // table 布局可以确保行号列固定，代码列可以换行而不影响行号
    var codeHtml = '<table class="code-table" cellspacing="0" cellpadding="0" border="0">';
    for(var i = 0; i < highlightedLines.length; i++) {
        var lineNum = pad0left(i + 1, 4, ' ');
        var lineContent = highlightedLines[i] || ' ';
        codeHtml += '<tr class="code-line">';
        codeHtml += '<td class="line-number-cell">' + lineNum + '</td>';
        codeHtml += '<td class="line-content-cell">' + lineContent + '</td>';
        codeHtml += '</tr>';
    }
    codeHtml += '</table>';
    
    // 构建正文 HTML（只包含代码部分）
    var htmlContent = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>' +
        '* { margin: 0; padding: 0; box-sizing: border-box; }' +
        'body { font-family: "Microsoft YaHei", -apple-system, BlinkMacSystemFont, "Segoe UI", "PingFang SC", "Hiragino Sans GB", "Helvetica Neue", Helvetica, Arial, sans-serif; font-size: 10pt; }' +
        '.code-section { margin-top: 4mm; border-top: 1px solid #000; padding-top: 4mm; }' +
        // 使用 table-layout: fixed 确保表格宽度固定，代码列自动换行
        '.code-table { width: 100%; table-layout: fixed; border-collapse: collapse; font-family: "Consolas", "Courier New", "Monaco", "Menlo", monospace; font-size: 9pt; line-height: 1.4; background: #ffffff; }' +
        '.code-line { }' +
        '.line-number-cell { width: 50px; min-width: 50px; max-width: 50px; text-align: right; padding-right: 8px; padding-left: 4px; color: #666; background: #f5f5f5; vertical-align: top; user-select: none; border-right: 1px solid #e0e0e0; }' +
        // 代码内容单元格：设置宽度为剩余空间，强制换行
        // 关键：使用 table-layout: fixed 后，代码列会自动占用剩余宽度，配合 word-wrap 和 overflow-wrap 实现自动换行
        '.line-content-cell { width: auto; padding-left: 8px; white-space: pre-wrap; word-wrap: break-word; overflow-wrap: break-word; word-break: break-all; vertical-align: top; }' +
        // 确保 hljs 高亮的代码内容也能正确换行
        '.line-content-cell * { word-wrap: break-word; overflow-wrap: break-word; word-break: break-all; }' +
        // 添加 hljs 样式（必须在 .line-content-cell 之后，确保样式正确应用）
        // 注意：已移除会覆盖 hljs 样式的 .line-content-cell * { color: inherit !important; } 规则
        hljsStyles +
        '</style></head><body>' +
        '<div class="code-section">' +
            codeHtml +
        '</div>' +
        '</body></html>';
    
    // 初始化打印
    LODOP.PRINT_INITA(0, 0, 0, 0, "Contest Code " + print_id);
    LODOP.SET_PRINT_PAGESIZE(1, 0, 0, "A4"); // A4 纵向
    
    // 构建页头内容（标题 + 时间 + 信息行）
    // 使用兼容多系统的字体栈，优化中英文显示
    // 英文优先：系统字体 -> 通用字体，中文优先：PingFang SC -> Hiragino Sans GB -> Microsoft YaHei
    var fontFamily = '"Microsoft YaHei", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "PingFang SC", "Hiragino Sans GB", "WenQuanYi Micro Hei", "Noto Sans SC", sans-serif';
    
    var headerContent = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>' +
        '* { margin: 0; padding: 0; box-sizing: border-box; }' +
        'body { font-family: ' + fontFamily + '; }' +
        '.header-top { display: flex; justify-content: space-between; align-items: flex-start; width: 100%; margin-bottom: 3mm; }' +
        '.contest-title { flex: 1; font-size: 13pt; font-weight: 600; color: #1a1a1a; word-wrap: break-word; overflow-wrap: break-word; margin-right: 10mm; line-height: 1.4; font-family: ' + fontFamily + '; }' +
        '.print-time { font-size: 8.5pt; color: #666; white-space: nowrap; font-weight: normal; font-family: ' + fontFamily + '; }' +
        '.info-row { display: flex; align-items: center; width: 100%; gap: 6mm; font-size: 8pt; border-top: 1px solid #ddd; padding-top: 2mm; margin-top: 2mm; }' +
        '.info-item { display: flex; align-items: center; white-space: nowrap; }' +
        '.info-label { font-size: 7pt; color: #666; margin-right: 1.5mm; line-height: 1.2; font-family: ' + fontFamily + '; }' +
        '.info-value { font-weight: 600; color: #333; font-family: ' + fontFamily + '; }' +
        '.info-value-const { margin: 0 2mm 0 1mm; font-weight: 600; color: #333; font-family: ' + fontFamily + '; font-family: "Microsoft YaHei"; }' +
        '.info-item-fixed { flex-shrink: 0; }' +
        '.info-item-ellipsis { min-width: 35mm; flex: 1 1 auto; white-space: normal; }' +
        '.info-value-ellipsis { word-wrap: break-word; overflow-wrap: break-word; white-space: normal; }' +
        '</style></head><body>' +
        '<div class="header-top">' +
            '<div class="contest-title">' + (contest_title || '') + '</div>' +
            '<div class="print-time">' + in_date + '</div>' +
        '</div>' +
        '<div class="info-row">' +
            '<div class="info-item info-item-fixed">' +
                '<span class="info-label">队伍ID<br/>Team ID</span>' +
                '<span class="info-value info-value-const">' + (team_id || '') + '</span>' +
            '</div>' +
            '<div class="info-item info-item-fixed">' +
                '<span class="info-label">房间/区域<br/>Room/Area</span>' +
                '<span class="info-value info-value-const">' + (room || '') + '</span>' +
            '</div>' +
            '<div class="info-item info-item-fixed">' +
                '<span class="info-label">打印ID<br/>Print ID</span>' +
                '<span class="info-value info-value-const">' + pad0left(print_id, 8, '0') + '</span>' +
            '</div>' +
            '<div class="info-item info-item-ellipsis">' +
                '<span class="info-label">学校/组织<br/>School/Org</span>' +
                '<span class="info-value info-value-ellipsis" title="' + (school || '') + '">' + (school || '') + '</span>' +
            '</div>' +
            '<div class="info-item info-item-ellipsis">' +
                '<span class="info-label">队名<br/>Team Name</span>' +
                '<span class="info-value info-value-ellipsis" title="' + (name || '') + '">' + (name || '') + '</span>' +
            '</div>' +
        '</div>' +
        '</body></html>';
    
    // 添加页头（每页顶部显示）
    // 使用足够的高度以确保信息行完整显示：标题行约 8-10mm + 信息行约 6-8mm + 间距 = 约 22mm
    // 如果内容超出高度，Lodop 会自动分页，所以设置一个较大的值确保内容完整显示
    LODOP.ADD_PRINT_HTM("6mm", "10mm", "RightMargin:10mm", "22mm", headerContent);
    // 设置页头为页眉页脚项（ItemType=1），每页都显示
    LODOP.SET_PRINT_STYLEA(0, "ItemType", 1);
    
    // 添加页脚中间页码（使用页号项和页数项）
    // 根据 Lodop 文档：ADD_PRINT_TEXT(Top, Left, Width, Height, strContent)
    // A4纸尺寸：210mm x 297mm
    // - Top: 区域上边缘距离顶部，计算为 297mm - 15mm(底部边距) - 6mm(文本高度) = 276mm
    // - Left: 居中位置 = (210mm - 50mm) / 2 = 80mm（手动计算居中，避免与 HOrient 冲突）
    // - Width: 文本区域宽度 50mm（足够容纳 "999 / 999" 格式）
    // - Height: 使用 BottomMargin:15mm 表示区域下边缘距离底部 15mm，确保在可打印区域内
    //   注意：使用 BottomMargin 时，不需要再设置 VOrient=1，否则会冲突
    // - strContent: "# / &" 表示当前页/总页数（# 为当前页，& 为总页数）
    LODOP.ADD_PRINT_TEXT("283mm", "80mm", "50mm", "BottomMargin:3mm", "# / &");
    // 使用 SET_PRINT_STYLEA 设置单个打印项样式（三个参数：varItemNameID, strStyleName, varStyleValue）
    // 第一个参数 0 表示当前（最后加入的那个）数据项，即上面 ADD_PRINT_TEXT 添加的页码
    LODOP.SET_PRINT_STYLEA(0, "ItemType", 2); // 页号项（包含 # 和 &）
    LODOP.SET_PRINT_STYLEA(0, "FontName", "Microsoft YaHei");
    LODOP.SET_PRINT_STYLEA(0, "FontSize", 9);
    LODOP.SET_PRINT_STYLEA(0, "Alignment", 2); // 文本内容在区域内居中对齐（1-左 2-居中 3-右）
    // 注意：不要设置 HOrient 和 VOrient，因为：
    // 1. Height 参数已使用 BottomMargin:15mm，实现了下边距锁定，设置 VOrient=1 会冲突
    // 2. Left 参数已手动计算居中位置(80mm)，设置 HOrient=2 会冲突
    
    // 添加正文 HTML 内容
    // 上边距：32mm（避开页头：5mm上边距 + 22mm高度 + 预留空间）
    // 下边距：22mm（避开页脚：页脚顶部距离底部 = 15mm + 6mm = 21mm，正文底部距离底部 22mm 确保有 1mm 间距）
    // 这样既减少了下方空白，又确保页脚可见且与正文有合理间距
    LODOP.ADD_PRINT_HTM("25mm", "10mm", "RightMargin:10mm", "BottomMargin:15mm", htmlContent);
}

// 执行打印或预览
function PrintCode(data, callback, preview) {
    if (!LODOP) {
        if (callback && typeof callback === 'function') {
            callback(new Error('Lodop 未初始化'), null);
        }
        return;
    }
    
    CreatePrintPage(data);
    var result;
    if (preview === true) {
        // 预览模式：内嵌到浏览器中
        LODOP.SET_SHOW_MODE("PREVIEW_IN_BROWSE", 1);
        result = LODOP.PREVIEW();
    } else {
        // 打印模式
        result = LODOP.PRINT();
    }
    if (callback && typeof callback === 'function') {
        callback(null, result);
    }
}