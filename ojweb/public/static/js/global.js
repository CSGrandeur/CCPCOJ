var g_submitDelayInfo = 500; //提交后通知延迟跳转
var g_submitDelayOper = 5; //提交后延迟下次操作

function button_delay(button, delay, ori, tips, enText) {
    button.attr('disabled', true);
    
    // 保存原始的中文和英文内容
    var originalText = ori;
    var originalEnText = enText;
    
    if (ori !== null) {
        // 检查是否存在en-text元素
        var enTextElement = button.find('.en-text');
        if (enTextElement.length === 0 && enText !== null) {
            // 如果不存在en-text元素且提供了英文内容，则创建
            button.html(ori + '<span class="en-text">' + enText + '</span>');
        } else if (enTextElement.length > 0 && enText !== null) {
            // 如果存在en-text元素且提供了英文内容，则更新
            enTextElement.text(enText);
        }
        
        // 设置延迟提示文本
        var delayText = tips ? (tips + "(" + delay + "s)") : `${delay} 秒`;
        var delayEnText = tips ? (tips + "(" + delay + "s)") : `${delay}s`;
        
        // 更新按钮内容
        if (enTextElement.length > 0 || enText !== null) {
            button.html(delayText + '<span class="en-text">' + delayEnText + '</span>');
        } else {
            button.text(delayText);
        }
    }
        
    var timer = setInterval(
        function() {
            delay--;
            if (delay <= 0) {
                if (ori !== null) {
                    // 恢复原始内容
                    if (enTextElement.length > 0 || originalEnText !== null) {
                        button.html(originalText + '<span class="en-text">' + originalEnText + '</span>');
                    } else {
                        button.text(originalText);
                    }
                }
                button.removeAttr('disabled');
                clearInterval(timer);
                return;
            }
            if (ori !== null) {
                // 更新倒计时
                var delayText = tips ? (tips + "(" + delay + "s)") : `${delay} 秒`;
                var delayEnText = tips ? (tips + "(" + delay + "s)") : `${delay}s`;
                
                if (enTextElement.length > 0 || enText !== null) {
                    button.html(delayText + '<span class="en-text">' + delayEnText + '</span>');
                } else {
                    button.text(delayText);
                }
            }
        },
        1000
    );
}

/**
 * 按钮延迟处理函数（自动保存和恢复DOM内容）
 * @param {jQuery|HTMLElement} button - 按钮DOM对象
 * @param {number} delay - 延迟时长（秒）
 * @param {string} flg_status - 状态标志："before"（禁用但不倒计时）或 "start"（开始倒计时）
 * @param {string} [tip] - 提示文本（可选，默认为"提交中"/"Submitting"）
 */
function button_delay_auto(button, delay, flg_status, tip) {
    var $button = $(button);
    
    // 保存原始HTML内容（包括图标、双语结构等）
    if (!$button.data('original-html')) {
        $button.data('original-html', $button.html());
    }
    var originalHtml = $button.data('original-html');
    
    // 检查是否存在双语结构（基于原始HTML检查）
    var tempDiv = $('<div>').html(originalHtml);
    var hasBilingual = tempDiv.find('.en-text').length > 0;
    
    // 禁用按钮
    $button.attr('disabled', true);
    
    // 如果tip为空，使用默认的中英双语文本
    var defaultTip = '提交中';
    var defaultTipEn = 'Submitting';
    var actualTip = (tip && tip.trim() !== '') ? tip : defaultTip;
    var actualTipEn = (tip && tip.trim() !== '') ? tip : defaultTipEn;
    
    // 更新按钮内容为提示文本
    function updateButtonText(currentDelay) {
        var delayText = actualTip + "(" + currentDelay + "s)";
        var delayEnText = actualTipEn + "(" + currentDelay + "s)";
        
        if (hasBilingual) {
            $button.html(delayText + '<span class="en-text">' + delayEnText + '</span>');
        } else {
            $button.text(delayText);
        }
    }
    
    if (flg_status === 'before') {
        // before状态：只显示提示，不开始倒计时
        // 清除可能存在的旧timer
        var oldTimer = $button.data('delay-timer');
        if (oldTimer) {
            clearInterval(oldTimer);
            $button.removeData('delay-timer');
        }
        updateButtonText(delay);
    } else if (flg_status === 'start') {
        // start状态：开始倒计时
        // 清除可能存在的旧timer
        var oldTimer = $button.data('delay-timer');
        if (oldTimer) {
            clearInterval(oldTimer);
        }
        
        updateButtonText(delay);
        
        // 使用局部变量保存倒计时值
        var currentDelay = delay;
        
        var timer = setInterval(function() {
            currentDelay--;
            if (currentDelay <= 0) {
                // 倒计时结束，恢复原始HTML内容
                $button.html(originalHtml);
                $button.removeAttr('disabled');
                $button.removeData('original-html');
                $button.removeData('delay-timer');
                clearInterval(timer);
                return;
            }
            // 更新倒计时显示
            updateButtonText(currentDelay);
        }, 1000);
        
        // 将timer保存到button上，以便外部可以清除
        $button.data('delay-timer', timer);
    }
}


function DoUploadFile(upload_file_input, upload_file_form, upload_file_button)
{
    upload_file_form.ajaxForm({
        beforeSend: function() {
            upload_file_button.attr('disabled', true);
            var percentVal = '0%';
            upload_file_button.text('Uploading'+percentVal);
        },
        uploadProgress: function(event, position, total, percentComplete) {
            var percentVal = percentComplete + '%';
            upload_file_button.text('Uploading'+percentVal);
        },
        success: function() {
            var percentVal = '100%';
            upload_file_button.text("Uploaded");
        },
        complete: function(e) {
            ret = JSON.parse(e.responseText);
            if(ret['code'] == 1)
            {
                alertify.success("Uploaded.");
                button_delay(upload_file_button, 1, 'Upload File', 'Upload File');
                return true;
            }
            else
            {
                alertify.error(ret['msg']);
                button_delay(upload_file_button, 1, 'Upload File', 'Upload File');
                return false;
            }
        }
    });
    upload_file_form.submit();
    upload_file_input.val('');
}


function checkfile(upload_input, maxfilesize) {
    var maxsizeMB = Math.ceil(maxfilesize / 1024 / 1024);
    var errMsg = "Filesize must not exceed " + maxsizeMB + "Mb";
    var tipMsg = "Your browser does not support the size of the uploaded file before uploading. Please make sure that the uploaded file does not exceed" + maxfilesize + "Mb.";
    var browserCfg = {};
    var ua = window.navigator.userAgent;
    if (ua.indexOf("MSIE") >= 1) {
        browserCfg.ie = true;
    } else if (ua.indexOf("Firefox") >= 1) {
        browserCfg.firefox = true;
    } else if (ua.indexOf("Chrome") >= 1) {
        browserCfg.chrome = true;
    }
    try {
        var obj_file = upload_input;
        if (obj_file.value == "") {
            return [false, "Please chose a file."];
        }
        var filesize = 0;
        if (browserCfg.firefox || browserCfg.chrome) {
            filesize = obj_file.files[0].size;
        } else if (browserCfg.ie) {
            var obj_img = document.getElementById('tempimg');
            obj_img.dynsrc = obj_file.value;
            filesize = obj_img.fileSize;
        } else {
            return [true, tipMsg];
        }
        if (filesize == -1) {
            return [true, tipMsg];
        } else if (filesize > maxfilesize) {
            return [false, errMsg];
        } else {
            return [true, null];
        }
    } catch (e) {
        return [true, null];
    }
}
function pad0left(num, n, padcontent)
{
    if(padcontent == null)
        padcontent = ' ';
    return (new Array(n).join(padcontent) + num).slice(-n);
}
function GetAnchor(name=null) {
    let anchor_str = window.location.hash.substr(1);
    if(name === null) return anchor_str;
    var reg = new RegExp("(^|#)" + name + "=([^#]*?)(#|$)");
    var r = anchor_str.match(reg);
    if (r != null) return decodeURI(r[2]); return null;
}
function SetAnchor(val, name=null) {
    let anchor_str = "";
    if(name === null) anchor_str = val;
    else {
        anchor_str = window.location.hash.substr(1);
        var reg = new RegExp("(^|#)" + name + "=([^#]*?)(#|$)");
        var r = anchor_str.match(reg);
        if(val === null || val === "") {
            if(r !== null) {
                anchor_str = anchor_str.replace(reg, "");
			}
        } else {
            if (r != null) {
                anchor_str = anchor_str.replace(reg, "$1" + name + "=" + val + "$3");
            }
            else {
                if(anchor_str === "") anchor_str = name + '=' + val;
                else anchor_str += '#' + name + '=' + val;
            }
        }
    }
    window.location.hash = '#' + anchor_str;
}

function FNV1aHash(str) {
    let hash = 2166136261; // FNV offset basis
    for (let i = 0; i < str.length; i++) {
        hash ^= str.charCodeAt(i);
        hash += (hash << 1) + (hash << 4) + (hash << 7) + (hash << 8) + (hash << 24);
    }
    return hash >>> 0; // Convert to 32bit unsigned integer
}

function FNV1aHash2Str(str, len=32) {
    let hash = FNV1aHash(str);
    let hashStr = hash.toString(16).toUpperCase();
    while (hashStr.length < len) {
        hashStr += FNV1aHash(hashStr).toString(16).toUpperCase();
    }
    return hashStr.slice(0, len);
}
// **************************************************
// cookie 封装，可处理中文
function SetCookie(key, value, exp={})
{
    window.localStorage.setItem(key, window.btoa(encodeURIComponent(JSON.stringify(value))));
}
function GetCookie(key)
{
    let cookiestr = window.localStorage.getItem(key);
    if(typeof(cookiestr) == "undefined" || !cookiestr)
        return false;
    let cookieobj = JSON.parse(unescape(decodeURIComponent(window.atob(cookiestr))));
    return cookieobj;
}
function DelCookie(key)
{
    window.localStorage.removeItem(key);
}
// **************************************************
// 时间日期格式相关
function DateFormat(date, fmt='yyyy-MM-dd HH:mm:ss') {
    const opt = {
        "y+": date.getFullYear().toString(),      
        "M+": (date.getMonth() + 1).toString(),   
        "d+": date.getDate().toString(),          
        "H+": date.getHours().toString(),         
        "m+": date.getMinutes().toString(),       
        "s+": date.getSeconds().toString()        
    };
    for (let k in opt) {
        ret = new RegExp("(" + k + ")").exec(fmt);
        if (ret) {
            fmt = fmt.replace(ret[1], (ret[1].length == 1) ? (opt[k]) : (opt[k].padStart(ret[1].length, "0")))
        };
    };
    return fmt;
}
function Timestamp2Time(timestamp, fmt='yyyy-MM-dd HH:mm:ss') {
    if(timestamp.toString().length < 13) {
        timestamp *= 1000;
    }
    let date = new Date(timestamp);
    return DateFormat(date, fmt);
}
function Timestr2Sec(timestr) {
    // xx:xx:xx 的时间转为秒
    let time_item = timestr.split(':');
    let res = 0;
    for(let i = 0; i < time_item.length; i ++) {
        res *= 60;
        res += parseInt(time_item[i]);
    }
    return res;
}
function Timeint2Str(sec_int) {
    let hour = Math.floor(sec_int / 3600 + 0.00000001);
    let mi = Math.floor(sec_int / 60 + 0.00000001) % 60;
    let sec = sec_int % 60;
    return `${pad0left(hour, 2, '0')}:${pad0left(mi, 2, '0')}:${pad0left(sec, 2, '0')}`;
}
function TimeLocal(timestr=null, fmt='yyyy-MM-dd HH:mm:ss') {
    let date;
    if(timestr === null) {
        date = new Date();
    } else {
        date = new Date(timestr);
    }
    return DateFormat(date, fmt);
}
Number.prototype.Pad = function(size) {
    var s = String(this);
    while (s.length < (size || 2)) {s = "0" + s;}
    return s;
}
function ItemShining(item, tm=5, to=200) {
    if(tm & 1) {
        item.hide();
    } else {
        item.show();
    }
    if(tm > 0) setTimeout(function(){ItemShining(item, tm - 1)}, to);
}
function ToggleFullScreen(id_name, target_item=null, set_full=null) {
    // dom对象全屏
    if(target_item == null) {
        target_item = document.getElementById(id_name);
    }        
    if (!document.fullscreenElement || set_full === true) {
        try {
            if (target_item.requestFullscreen) {
                target_item.requestFullscreen();
            } else if (target_item.webkitRequestFullscreen) { /* Safari */
                target_item.webkitRequestFullscreen();
            } else if (target_item.msRequestFullscreen) { /* IE11 */
                target_item.msRequestFullscreen();
            }
        } catch(e) {
            alertify.error(`Error attempting to enable full-screen mode: ${e}`);
        }
    } else {
        if (document.exitFullscreen) {
            document.exitFullscreen();
        } else if (document.webkitExitFullscreen) { /* Safari */
            document.webkitExitFullscreen();
        } else if (document.msExitFullscreen) { /* IE11 */
            document.msExitFullscreen();
        }
    }
}
function SetFrontAlertify(target_div_id) {
    // 手动初始化alertify对象位置以便在答题界面全屏时也能正常显示
    alertify.confirm().set({onshow: function(){
        $('.alertify').appendTo(`#${target_div_id}`)
    }});
    alertify.alert().set({onshow: function(){
        $('.alertify').appendTo(`#${target_div_id}`)
    }});
    $(document).on('DOMNodeInserted', '.ajs-message', function(e) {
        $('.alertify-notifier').appendTo(`#${target_div_id}`)
    });
}
function StrWidthLength(s) {
    var len = 0;
    for (var i = 0; i < s.length; i++) {
        var c = s.charCodeAt(i);
        if (c >= 0x0000 && c <= 0x00FF) {
            len += 1;
        } else {
            len += 2;
        }
    }
    return len;

}
function StrByteLength(s) {
    var len = 0;
    for (var i = 0; i < s.length; i++) {
        var c = s.charCodeAt(i);
        if (c >= 0x010000 && c <= 0x10FFFF) {
            len += 4;
        } else if (c >= 0x000800 && c <= 0x00FFFF) {
            len += 3;
        } else if (c >= 0x000080 && c <= 0x0007FF) {
            len += 2;
        } else {
            len += 1;
        }
    }
    return len;
}
function Any2Ascii(str) {
    let utf8Str = encodeURIComponent(str);
    let base64Str = btoa(utf8Str);
    let asciiStr = base64Str.replace(/[^a-zA-Z0-9]/g, '_');
    return asciiStr;
}
function OpenBlobHtml(html_str) {
    let blob = new Blob([html_str], {type: "text/html"});
    let url = URL.createObjectURL(blob);
    window.open(url, "_blank");
}
async function ClipboardWrite(st) {
    if(st == "") {
        st = " ";
    }
    
    // 方法1: 现代 Clipboard API (需要 HTTPS 或 localhost)
    if (navigator.clipboard && window.isSecureContext) {
        try {
            await navigator.clipboard.writeText(st);

            return true;
        } catch (error) {
            // console.warn('Clipboard API failed, falling back to legacy method:', error);
            // 如果 Clipboard API 失败，继续尝试传统方法
        }
    }
    
    // 方法2: 传统 document.execCommand 方法
    try {
        const textArea = document.createElement("textarea");
        textArea.value = st;
        
        // 设置样式使其不可见但可选择
        // 注意：不能设置为 display: none，否则 select() 可能失败
        textArea.style.position = "fixed";
        textArea.style.top = "0";
        textArea.style.left = "0";
        textArea.style.width = "2em";
        textArea.style.height = "2em";
        textArea.style.padding = "0";
        textArea.style.border = "none";
        textArea.style.outline = "none";
        textArea.style.boxShadow = "none";
        textArea.style.background = "transparent";
        textArea.style.opacity = "0";
        textArea.style.zIndex = "-1";
        textArea.style.pointerEvents = "none";
        
        // 添加到 DOM
        document.body.appendChild(textArea);
        
        // 尝试多种方式选择文本，确保兼容性
        // 关键：必须在用户交互事件的同步上下文中执行 select 和 execCommand
        textArea.focus();
        
        // 对于移动端，使用 setSelectionRange
        if (navigator.userAgent.match(/(ipad|iphone|ipod|android|windows phone)/i)) {
            textArea.setSelectionRange(0, st.length);
        } else {
            // 桌面端使用 select()
            textArea.select();
            // 额外确保选择（某些浏览器需要）
            if (document.activeElement !== textArea) {
                textArea.focus();
                textArea.select();
            }
        }
        
        // 执行复制
        // 注意：document.execCommand('copy') 必须在用户交互事件的同步上下文中执行
        // 不能放在 setTimeout 或 Promise 中，否则可能失败
        let successful = false;
        try {
            // 确保在同步上下文中执行
            successful = document.execCommand('copy');
            
            // 验证复制是否成功（在某些浏览器中，execCommand 可能返回 true 但实际未复制）
            // 注意：直接读取剪贴板需要权限，且可能不可用
            // 但我们可以通过检查浏览器是否支持 Clipboard API 来间接验证
            if (successful && navigator.clipboard && window.isSecureContext) {
                // 如果支持 Clipboard API，可以尝试验证（但会增加延迟）
                // 这里先不验证，因为 Clipboard API 已经在第一步尝试过了
            }
        } catch (err) {
            // 某些浏览器在非用户交互上下文中会抛出错误
            console.warn('execCommand copy failed:', err);
            successful = false;
        }
        
        // 验证复制是否成功（通过读取剪贴板内容，但这个方法在某些浏览器中需要权限）
        // 如果 execCommand 返回 true，通常意味着复制成功
        // 但我们也可以尝试读取来验证（注意：readText 需要用户授权）
        
        // 清理
        document.body.removeChild(textArea);
        
        return successful;
    } catch (error) {
        console.error('All clipboard methods failed:', error);
        return false;
    }
}

function DomSantize(st) {
    return st
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}
// **************************************************
// 颜色规范化函数 - 统一处理气球颜色格式
// **************************************************
/**
 * 规范化颜色格式
 * @param {string|number} color - 颜色值（可以是十六进制、颜色名称、数字等）
 * @param {object} options - 选项对象
 * @param {boolean} options.strict - 严格模式（用于输入验证），默认 false（用于显示）
 * @param {boolean} options.trustInput - 是否信任输入（用于显示端），默认 true
 * @returns {string|null} - 规范化后的颜色字符串，无效时返回 null
 */
function NormalizeColor(color, options = {}) {
    const { strict = false, trustInput = true } = options;
    
    // 处理 null、undefined、空字符串
    if (color === null || color === undefined) {
        return null;
    }
    
    // 处理数字类型（0-16777215）
    if (typeof color === 'number') {
        if (color >= 0 && color <= 16777215) {
            return '#' + color.toString(16).toUpperCase().padStart(6, '0');
        }
        return null;
    }
    
    // 必须是字符串类型
    if (typeof color !== 'string') {
        return null;
    }
    
    const originalColor = color.trim();
    if (originalColor === '') {
        return null;
    }
    
    // 处理十六进制颜色（6位，不带#）
    if (/^[0-9A-Fa-f]{6}$/.test(originalColor)) {
        return '#' + originalColor.toUpperCase();
    }
    
    // 处理十六进制颜色（3位，不带#）
    if (/^[0-9A-Fa-f]{3}$/.test(originalColor)) {
        const expanded = originalColor.split('').map(c => c + c).join('');
        return '#' + expanded.toUpperCase();
    }
    
    // 处理带#的十六进制颜色（6位）
    if (/^#[0-9A-Fa-f]{6}$/i.test(originalColor)) {
        return originalColor.toUpperCase();
    }
    
    // 处理带#的十六进制颜色（3位）
    if (/^#[0-9A-Fa-f]{3}$/i.test(originalColor)) {
        const hexPart = originalColor.substring(1);
        const expanded = hexPart.split('').map(c => c + c).join('');
        return '#' + expanded.toUpperCase();
    }
    
    // 处理 0x 前缀的十六进制（如 0xFF0000）
    if (/^0x[0-9A-Fa-f]{6}$/i.test(originalColor)) {
        const hexValue = originalColor.substring(2);
        return '#' + hexValue.toUpperCase();
    }
    
    // 处理 RGB/RGBA/HSL 格式（直接返回）
    if (/^(rgb|rgba|hsl|hsla)\(/i.test(originalColor)) {
        return originalColor;
    }
    
    // 处理颜色名称（如 red, blue, green）
    // 如果是严格的十六进制格式（带#但不匹配3或6位），在严格模式下返回null
    if (strict && originalColor.startsWith('#')) {
        // 严格模式下，如果带#但不是有效格式，返回null
        return null;
    }
    
    // 非严格模式或颜色名称：使用浏览器验证
    // 对于显示端（trustInput=true），更信任输入，直接返回原值或小写
    if (trustInput) {
        // 检查是否为有效的CSS颜色名称
        const tempElement = document.createElement('div');
        tempElement.style.color = originalColor;
        if (tempElement.style.color !== '') {
            // 如果是纯字母（颜色名称），返回小写
            if (/^[a-zA-Z]+$/.test(originalColor)) {
                return originalColor.toLowerCase();
            }
            // 其他有效颜色格式，返回原值
            return originalColor;
        }
    } else {
        // 输入验证模式：更严格的验证
        const tempElement = document.createElement('div');
        tempElement.style.color = originalColor;
        if (tempElement.style.color !== '') {
            if (/^[a-zA-Z]+$/.test(originalColor)) {
                return originalColor.toLowerCase();
            }
            return originalColor;
        }
    }
    
    return null;
}

/**
 * 规范化颜色用于显示（显示端使用，更信任输入）
 * @param {string|number} color - 颜色值
 * @returns {string|null} - 规范化后的颜色，无效时返回 null
 */
function NormalizeColorForDisplay(color) {
    return NormalizeColor(color, { strict: false, trustInput: true });
}

/**
 * 规范化颜色用于输入验证（管理后台使用，更严格）
 * @param {string|number} color - 颜色值
 * @returns {string|null} - 规范化后的颜色，无效时返回 null
 */
function NormalizeColorForInput(color) {
    return NormalizeColor(color, { strict: true, trustInput: false });
}
// **************************************************
// bootstrap-table常用formatter
function AutoId(value, row, index, field) {
    return index + 1;
}
function FormatterIndex(value, row, index, field) {
    return index + 1;
}
function FormatterIdx(value, row, index, field) {
    return index + 1;
}
function FormatterNoWrap(value, row, index, field) {
    return `<div style="white-space:nowrap;">${value}</div>`;
}
function FormatterDomSantize(value, row, index, field) {
    return DomSantize(value)
}
function IsNothing(vobj) {
    // 判断对象是否未定义
    return typeof(vobj) === 'undefined' || vobj === null;
}
function SetF5RefreshTable(target_table) {
    $(window).keydown(function(e) {
        if (e.keyCode == 116 && !e.ctrlKey) {
            if(window.event){
                try{e.keyCode = 0;}catch(e){}
                e.returnValue = false;
            }
            e.preventDefault();
            target_table.bootstrapTable('refresh');
        }
    });
}
function TextAllowTab(textarea_id) {
    // 允许 textarea 内 tab
    const text_dom = document.getElementById(textarea_id);
    if(text_dom) {
        text_dom.addEventListener('keydown', function(e) {
            if (e.key === 'Tab') {
                e.preventDefault();
                const start = this.selectionStart;
                const end = this.selectionEnd;
                this.value = this.value.substring(0, start) + '\t' + this.value.substring(end);
                this.selectionStart = this.selectionEnd = start + 1;
            }
        });
    }
}
// 自动将 title 属性转换为 Bootstrap 5 tooltip 的全局功能
function AutoInitBootstrapTooltips() {
    var observer = null;
    var isInitialized = false;
    
    function initTooltipsFromTitle() {
        $('[title]').filter(function() {
            // 只处理没有任何 data-bs-toggle 的元素
            return typeof $(this).attr('data-bs-toggle') === 'undefined' || $(this).attr('data-bs-toggle') === 'tooltip';
        }).each(function() {
            var $this = $(this);
            var titleText = $this.attr('title');
            
            // 如果 title 为空、null 或 undefined，跳过
            if (!titleText || titleText.trim() === '') {
                return;
            }
            
            // 移除 title 属性，避免浏览器原生 tooltip 显示
            $this.removeAttr('title');
            
            // 添加 Bootstrap 5 tooltip 属性
            $this.attr('data-bs-toggle', 'tooltip');
            $this.attr('data-bs-placement', 'top');
            $this.attr('data-bs-title', titleText);
            $this.attr('csg-target-tooltip', 'true');
        });
        
        // 初始化所有 tooltip
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[csg-target-tooltip="true"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            // 检查是否已经初始化过
            var existingTooltip = bootstrap.Tooltip.getInstance(tooltipTriggerEl);
            if (existingTooltip) {
                // 如果已存在实例且已有事件监听器标记，直接返回
                if (tooltipTriggerEl.dataset.tooltipListenersAdded === 'true') {
                    return existingTooltip;
                }
                // 如果实例存在但没有标记，说明可能是外部创建的，添加标记后返回
                tooltipTriggerEl.dataset.tooltipListenersAdded = 'true';
                return existingTooltip;
            }
            
            // 获取 title 文本
            var titleText = tooltipTriggerEl.getAttribute('data-bs-title') || tooltipTriggerEl.getAttribute('title');
            
            // 如果 title 为空，跳过创建
            if (!titleText || titleText.trim() === '') {
                return null;
            }
            
            // 创建新的 tooltip 实例
            var tooltip = new bootstrap.Tooltip(tooltipTriggerEl, {
                title: titleText,
                trigger: 'hover focus',
                delay: { show: 100, hide: 100 },
                boundary: 'viewport',
                fallbackPlacements: ['top', 'bottom', 'left', 'right']
            });
            
            // 标记已添加事件监听器，避免重复绑定
            if (tooltipTriggerEl.dataset.tooltipListenersAdded !== 'true') {
                // 定义事件处理函数（命名函数，便于后续移除）
                var mouseleaveHandler = function() {
                    setTimeout(function() {
                        var currentTooltip = bootstrap.Tooltip.getInstance(tooltipTriggerEl);
                        if (currentTooltip && currentTooltip._isEnabled) {
                            currentTooltip.hide();
                        }
                    }, 150);
                };
                
                var clickHandler = function() {
                    var currentTooltip = bootstrap.Tooltip.getInstance(tooltipTriggerEl);
                    if (currentTooltip && currentTooltip._isEnabled) {
                        currentTooltip.hide();
                    }
                };
                
                // 将处理函数保存到元素上，便于后续清理
                tooltipTriggerEl._tooltipMouseleaveHandler = mouseleaveHandler;
                tooltipTriggerEl._tooltipClickHandler = clickHandler;
                
                // 添加强制隐藏的事件监听
                tooltipTriggerEl.addEventListener('mouseleave', mouseleaveHandler);
                
                // 添加点击隐藏
                tooltipTriggerEl.addEventListener('click', clickHandler);
                
                // 标记已添加监听器
                tooltipTriggerEl.dataset.tooltipListenersAdded = 'true';
            }
            
            return tooltip;
        });
    }
    
    // 监听动态添加的元素
    function startObserver() {
        observer = new MutationObserver(function(mutations) {
            var shouldInit = false;
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList') {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1) { // Element node
                            var $node = $(node);
                            if ($node.find('[title]:not([data-bs-toggle="tooltip"])').length > 0 || 
                                $node.is('[title]:not([data-bs-toggle="tooltip"])')) {
                                shouldInit = true;
                            }
                        }
                    });
                }
            });
            if (shouldInit) {
                setTimeout(initTooltipsFromTitle, 100); // 延迟执行，确保 DOM 完全渲染
            }
        });
        
        // 开始观察
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }
    
    // 停止观察
    function stopObserver() {
        if (observer) {
            observer.disconnect();
            observer = null;
        }
    }
    
    // 清理所有 tooltip
    function cleanupTooltips() {
        var tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        tooltips.forEach(function(element) {
            // 移除事件监听器
            if (element._tooltipMouseleaveHandler) {
                element.removeEventListener('mouseleave', element._tooltipMouseleaveHandler);
                delete element._tooltipMouseleaveHandler;
            }
            if (element._tooltipClickHandler) {
                element.removeEventListener('click', element._tooltipClickHandler);
                delete element._tooltipClickHandler;
            }
            
            // 移除标记
            if (element.dataset.tooltipListenersAdded) {
                delete element.dataset.tooltipListenersAdded;
            }
            
            // 清理 tooltip 实例
            var tooltip = bootstrap.Tooltip.getInstance(element);
            if (tooltip) {
                tooltip.hide();
                tooltip.dispose();
            }
        });
    }
    
    // 强制隐藏所有 tooltip
    function forceHideAllTooltips() {
        var tooltips = document.querySelectorAll('.tooltip');
        tooltips.forEach(function(tooltip) {
            tooltip.remove();
        });
    }
    
    // 公共接口
    return {
        init: function() {
            // 防止重复初始化
            if (isInitialized) {
                return;
            }
            isInitialized = true;
            
            // 页面加载时初始化
            initTooltipsFromTitle();
            // 开始监听动态元素
            startObserver();
        },
        stop: function() {
            stopObserver();
        },
        refresh: function() {
            // 手动刷新tooltip
            setTimeout(initTooltipsFromTitle, 100);
        },
        cleanup: function() {
            cleanupTooltips();
        },
        forceHide: function() {
            forceHideAllTooltips();
        }
    };
}
function DoInitTooltip() {
    
    // 防止重复执行整个初始化过程
    if (window.flg_bootstrap_tooltip_init) {
        return;
    }
    window.flg_bootstrap_tooltip_init = true;
    
    // 启用自动tooltip转换功能
    // 如需禁用，请注释下面这行
    if (!window.autoTooltips) {
        window.autoTooltips = AutoInitBootstrapTooltips();
        window.autoTooltips.init();
    }
    
    // 添加全局事件监听，防止 tooltip 卡住
    $(document).on('mouseleave', '[data-bs-toggle="tooltip"]', function() {
        var tooltip = bootstrap.Tooltip.getInstance(this);
        if (tooltip) {
            setTimeout(function() {
                tooltip.hide();
            }, 100);
        }
    });
    
    // 页面失去焦点时隐藏所有 tooltip
    $(window).on('blur', function() {
        if (window.autoTooltips) {
            window.autoTooltips.forceHide();
        }
    });
    
    // 滚动时隐藏所有 tooltip
    $(window).on('scroll', function() {
        if (window.autoTooltips) {
            window.autoTooltips.forceHide();
        }
    });
    
    // 窗口大小改变时清理 tooltip
    $(window).on('resize', function() {
        if (window.autoTooltips) {
            window.autoTooltips.cleanup();
        }
    });
}
$(function(){
    DoInitTooltip();
    $('.bootstrap_table_table').on('post-body.bs.table', function(){
        //处理table宽度，不出现横向scrollbar
        var bootstrap_table_div = $('.bootstrap_table_div');
        if(this.scrollWidth > bootstrap_table_div.width())
            bootstrap_table_div.width(this.scrollWidth + 20);
            
        // 表格刷新后重新初始化 tooltip
        if (window.autoTooltips) {
            window.autoTooltips.refresh();
        }
    });
});
$(document).on('dblclick', '.dblclick_fullscreen', (e) => {
    // 自定义双击全屏的对象
    let target = $(e.target).closest('.dblclick_fullscreen')[0];
    $(target).css('overflow', "scroll");
    ToggleFullScreen(null, $(e.target).closest('.dblclick_fullscreen')[0]);
});

