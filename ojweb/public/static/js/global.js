﻿var g_submitDelayInfo = 500; //提交后通知延迟跳转
var g_submitDelayOper = 5; //提交后延迟下次操作

function button_delay(button, delay, ori, tips) {
    button.attr('disabled', true);
    if (ori !== null) {
        // button.text(tips ? (tips + "(" + delay + "s)") : ('you can submit again after ' + delay + 's'));
        button.text(tips ? (tips + "(" + delay + "s)") : `${delay} 秒后可再次提交`);
    }
        
    var timer = setInterval(
        function() {
            delay--;
            if (delay <= 0) {
                if (ori !== null)
                    button.text(ori);
                button.removeAttr('disabled');
                clearInterval(timer);
                return;
            }
            if (ori !== null) {
                button.text(tips ? (tips + "(" + delay + "s)") : `${delay} 秒后可再次提交`);
            }
        },
        1000
    );
}


jQuery.validator.addMethod("user_id_validate", function(value, element)
{
    return this.optional( element ) || /^[a-zA-Z0-9_]+$/.test($.trim(value));
}, "Only number, letters and underlines are allowed");


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
    // Navigator clipboard api需要一个安全的上下文（https）
    if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(st);
    } else {
        // 使用“视口外隐藏文本区域”的技巧
        const textArea = document.createElement ("textarea");
        textArea.value = st;
        // 将文本区域移出视口，使其不可见
        textArea.style.position = "absolute";
        textArea.style.left = "-999999px";
        // textArea.style.display = "none";
        document.body.prepend (textArea);
        textArea.select();
        try {
            document.execCommand ('copy');
        } catch (error) {
            console.error (error);
            return false;
        } finally {
            textArea.remove ();
        }
    }
    return true;
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
function FormatterDate(value, row, index, field) {
    return value.replace(' ', '<br/>');
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
$(document).ready(function(){
    $('.bootstrap_table_table').on('post-body.bs.table', function(){
        //处理table宽度，不出现横向scrollbar
        var bootstrap_table_div = $('.bootstrap_table_div');
        if(this.scrollWidth > bootstrap_table_div.width())
            bootstrap_table_div.width(this.scrollWidth + 20);
    });
});
$(document).on('dblclick', '.dblclick_fullscreen', (e) => {
    // 自定义双击全屏的对象
    let target = $(e.target).closest('.dblclick_fullscreen')[0];
    $(target).css('overflow', "scroll");
    ToggleFullScreen(null, $(e.target).closest('.dblclick_fullscreen')[0]);
});