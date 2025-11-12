var CreatedOKLodopObject, CLodopIsLocal, CLodopJsState;

// ========================================
// Lodop 通知系统 - 固定顶部显示
// ========================================
var LodopNotification = {
    container: null,
    currentAlert: null,
    testTimeout: null,

    // 初始化通知容器
    init: function() {
        if (this.container) return;
        
        this.container = document.createElement('div');
        this.container.className = 'position-fixed top-0 start-0 end-0 pt-3 d-flex justify-content-center';
        this.container.style.cssText = 'z-index: 9999; pointer-events: none;';
        document.body.appendChild(this.container);
    },

    // 显示通知
    show: function(type, message, messageEn, options) {
        this.init();
        this.hide();
        
        if (this.testTimeout) {
            clearTimeout(this.testTimeout);
            this.testTimeout = null;
        }
        
        options = options || {};
        var alertClass = 'alert-' + (type || 'warning');
        var icon = this.getIcon(type);
        
        var alert = document.createElement('div');
        alert.className = 'alert ' + alertClass + ' alert-dismissible fade show mb-0 w-100';
        alert.style.cssText = 'max-width: 800px; pointer-events: auto;';
        
        var content = message;
        if (messageEn) {
            content += ' <span class="en-text">' + messageEn + '</span>';
        }
        
        alert.innerHTML = '<div class="d-flex align-items-center">' +
            '<i class="' + icon + ' me-2"></i>' +
            '<div class="flex-grow-1">' + content + '</div>' +
            '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
            '</div>';
        
        this.container.appendChild(alert);
        this.currentAlert = alert;
        
        // 自动关闭
        if (options.autoClose !== false && (options.delay || type === 'success')) {
            var delay = options.delay || (type === 'success' ? 3000 : 5000);
            var self = this;
            this.testTimeout = setTimeout(function() {
                if (self.currentAlert === alert) {
                    self.hide();
                }
            }, delay);
        }
    },

    // 显示测试状态
    showTesting: function() {
        this.show('info', 
            '正在测试打印控件...', 
            'Testing print control...',
            { autoClose: false }
        );
    },

    // 显示测试成功
    showTestSuccess: function() {
        this.show('success',
            '打印控件测试成功！', 
            'Print control test successful!',
            { delay: 2000 }
        );
    },

    // 隐藏通知
    hide: function() {
        if (this.currentAlert) {
            if (typeof bootstrap !== 'undefined' && bootstrap.Alert) {
                var bsAlert = new bootstrap.Alert(this.currentAlert);
                bsAlert.close();
            } else {
                this.currentAlert.remove();
            }
            this.currentAlert = null;
        }
        if (this.testTimeout) {
            clearTimeout(this.testTimeout);
            this.testTimeout = null;
        }
    },

    // 获取图标
    getIcon: function(type) {
        var icons = {
            'warning': 'bi bi-exclamation-triangle-fill',
            'danger': 'bi bi-x-circle-fill',
            'info': 'bi bi-info-circle-fill',
            'success': 'bi bi-check-circle-fill'
        };
        return icons[type] || icons['warning'];
    }
};

//==判断是否需要CLodop(那些不支持插件的浏览器):==
function needCLodop() {
    try {
        var ua = navigator.userAgent;
        if (ua.match(/Windows\sPhone/i))
            return true;
        if (ua.match(/iPhone|iPod|iPad/i))
            return true;
        if (ua.match(/Android/i))
            return true;
        if (ua.match(/Edge\D?\d+/i))
            return true;

        var verTrident = ua.match(/Trident\D?\d+/i);
        var verIE = ua.match(/MSIE\D?\d+/i);
        var verOPR = ua.match(/OPR\D?\d+/i);
        var verFF = ua.match(/Firefox\D?\d+/i);
        var x64 = ua.match(/x64/i);
        if ((!verTrident) && (!verIE) && (x64))
            return true;
        else if (verFF) {
            verFF = verFF[0].match(/\d+/);
            if ((verFF[0] >= 41) || (x64))
                return true;
        } else if (verOPR) {
            verOPR = verOPR[0].match(/\d+/);
            if (verOPR[0] >= 32)
                return true;
        } else if ((!verTrident) && (!verIE)) {
            var verChrome = ua.match(/Chrome\D?\d+/i);
            if (verChrome) {
                verChrome = verChrome[0].match(/\d+/);
                if (verChrome[0] >= 41)
                    return true;
            }
        }
        return false;
    } catch (err) {
        return true;
    }
}

//加载CLodop时用双端口(http是8000/18000,而https是8443/8444)以防其中某端口被占,
//主JS文件“CLodopfuncs.js”是固定文件名，其内容是动态的，与当前打印环境有关:
function loadCLodop() {
    if (CLodopJsState == "loading" || CLodopJsState == "complete") return;
    CLodopJsState = "loading";
    var head = document.head || document.getElementsByTagName("head")[0] || document.documentElement;
    var JS1 = document.createElement("script");
    var JS2 = document.createElement("script");

    if (window.location.protocol=='https:') {
      JS1.src = "https://localhost.lodop.net:8443/CLodopfuncs.js";
      JS2.src = "https://localhost.lodop.net:8444/CLodopfuncs.js";
    } else {
      JS1.src = "http://localhost:8000/CLodopfuncs.js";
      JS2.src = "http://localhost:18000/CLodopfuncs.js";
    }
    JS1.onload  = JS2.onload  = function()    {CLodopJsState = "complete";}
    JS1.onerror = JS2.onerror = function(evt) {CLodopJsState = "complete";}
    head.insertBefore(JS1, head.firstChild);
    head.insertBefore(JS2, head.firstChild);
    CLodopIsLocal = !!((JS1.src + JS2.src).match(/\/\/localho|\/\/127.0.0./i));
}

if (needCLodop()){loadCLodop();}//开始加载

var documentMain = document.getElementsByTagName('main')[0];

//====获取LODOP对象的主过程：====
function getLodop(oOBJECT,oEMBED){
    var LODOP;
    var ua = navigator.userAgent;
    var isIE = !!(ua.match(/MSIE/i)) || !!(ua.match(/Trident/i));
    var is64IE = isIE && !!(ua.match(/x64/i));
    
    // 显示测试中状态
    LodopNotification.showTesting();
    
    try {
        if (needCLodop()) {
            // CLodop 模式
            try {
                LODOP = getCLodop();
            } catch (err) {}
            
            if (!LODOP && CLodopJsState !== "complete") {
                LodopNotification.hide();
                if (CLodopJsState == "loading") {
                    LodopNotification.show('warning', 
                        '网页还没下载完毕，请稍等一下再操作。', 
                        'The page is still loading, please wait a moment before operating.');
                } else {
                    LodopNotification.show('warning',
                        '没有加载CLodop的主js，请先调用loadCLodop过程。',
                        'CLodop main JS not loaded, please call loadCLodop first.');
                }
                return;
            }
            
            if (!LODOP) {
                // CLodop 未安装
                var lodop_setup_url = '<a href="/static/lodop/CLodop_Setup_for_Win32NT.exe" target="_self" class="alert-link">32位Windows</a>；' +
                    '<a href="/static/lodop/CLodop_Setup_for_Win64NT.exe" target="_self" class="alert-link">64位Windows</a>；' +
                    '<a href="/static/lodop/Lodop7.064_Linux_X86_64_CN.deb" target="_self" class="alert-link">Linux</a>';
                
                var installMsg = 'Web打印服务CLodop未安装启动，点击下载安装：' + lodop_setup_url;
                var installMsgEn = 'Web print service CLodop is not installed or started. Click to download and install: ' + 
                    '<a href="/static/lodop/CLodop_Setup_for_Win32NT.exe" target="_self" class="alert-link">32-bit Windows</a>; ' +
                    '<a href="/static/lodop/CLodop_Setup_for_Win64NT.exe" target="_self" class="alert-link">64-bit Windows</a>; ' +
                    '<a href="/static/lodop/Lodop7.064_Linux_X86_64_CN.deb" target="_self" class="alert-link">Linux</a>';
                
                if (CLodopIsLocal) {
                    installMsg += '（若此前已安装过，可<a href="CLodop.protocol:setup" target="_self" class="alert-link">点这里直接再次启动</a>）';
                    installMsgEn += ' (If previously installed, you can <a href="CLodop.protocol:setup" target="_self" class="alert-link">click here to restart directly</a>)';
                }
                installMsg += '，成功后请刷新或重启浏览器。';
                installMsgEn += '. After success, please refresh or restart the browser.';
                
                LodopNotification.show('warning', installMsg, installMsgEn, { autoClose: false });
                return;
            } else {
                // CLodop 已安装，检查版本
                if (CLODOP.CVERSION < "4.1.4.5") {
                    var updateMsg = 'Web打印服务CLodop需升级！点击下载升级：' +
                        '<a href="/static/lodop/CLodop_Setup_for_Win32NT.exe" target="_self" class="alert-link">32位Windows</a>；' +
                        '<a href="/static/lodop/CLodop_Setup_for_Win64NT.exe" target="_self" class="alert-link">64位Windows</a>；' +
                        '<a href="/static/lodop/Lodop7.064_Linux_X86_64_CN.deb" target="_self" class="alert-link">Linux</a>' +
                        '，升级后请刷新或重启浏览器。';
                    var updateMsgEn = 'Web print service CLodop needs to be upgraded! Click to download and upgrade: ' +
                        '<a href="/static/lodop/CLodop_Setup_for_Win32NT.exe" target="_self" class="alert-link">32-bit Windows</a>; ' +
                        '<a href="/static/lodop/CLodop_Setup_for_Win64NT.exe" target="_self" class="alert-link">64-bit Windows</a>; ' +
                        '<a href="/static/lodop/Lodop7.064_Linux_X86_64_CN.deb" target="_self" class="alert-link">Linux</a>' +
                        '. After upgrade, please refresh or restart the browser.';
                    LodopNotification.show('warning', updateMsg, updateMsgEn, { autoClose: false });
                } else {
                    // 测试成功
                    LodopNotification.showTestSuccess();
                }
                
                // 清理旧版无效元素
                if (oEMBED && oEMBED.parentNode)
                    oEMBED.parentNode.removeChild(oEMBED);
                if (oOBJECT && oOBJECT.parentNode)
                    oOBJECT.parentNode.removeChild(oOBJECT);
            }
        } else {
            // 传统插件模式
            //==如果页面有Lodop就直接使用,否则新建:==
            if (oOBJECT || oEMBED) {
                if (isIE)
                    LODOP = oOBJECT;
                else
                    LODOP = oEMBED;
            } else if (!CreatedOKLodopObject) {
                LODOP = document.createElement("object");
                LODOP.setAttribute("width", 0);
                LODOP.setAttribute("height", 0);
                LODOP.setAttribute("style", "position:absolute;left:0px;top:-100px;width:0px;height:0px;");
                if (isIE)
                    LODOP.setAttribute("classid", "clsid:2105C259-1E0C-4534-8141-A753534CB4CA");
                else
                    LODOP.setAttribute("type", "application/x-print-lodop");
                document.documentElement.appendChild(LODOP);
                CreatedOKLodopObject = LODOP;
            } else
                LODOP = CreatedOKLodopObject;
            
            //==Lodop插件未安装时提示下载地址:==
            if ((!LODOP) || (!LODOP.VERSION)) {
                var installMsg, installMsgEn;
                var browserNote = '';
                var browserNoteEn = '';
                
                // 浏览器特定提示（合并到安装提示中）
                if (ua.indexOf('Chrome') >= 0) {
                    browserNote = '（注意：如果此前正常，仅因浏览器升级或重安装而出问题，需重新执行以上安装）';
                    browserNoteEn = ' (Note: If it was working before and only failed due to browser upgrade or reinstallation, you need to reinstall as above)';
                }
                if (ua.indexOf('Firefox') >= 0) {
                    browserNote = '（注意：如曾安装过Lodop旧版附件npActiveXPLugin,请在【工具】->【附加组件】->【扩展】中先卸它）';
                    browserNoteEn = ' (Note: If you have installed the old Lodop addon npActiveXPLugin, please uninstall it first in [Tools] -> [Add-ons] -> [Extensions])';
                }
                
                // 安装提示
                if (is64IE) {
                    installMsg = '打印控件未安装！点击这里<a href="/static/lodop/install_lodop64.exe" target="_self" class="alert-link">执行安装</a>，安装后请刷新页面或重新进入。';
                    installMsgEn = 'Print control is not installed! Click here to <a href="/static/lodop/install_lodop64.exe" target="_self" class="alert-link">install</a>. After installation, please refresh the page or re-enter.';
                } else {
                    installMsg = '打印控件未安装！点击这里<a href="/static/lodop/install_lodop32.exe" target="_self" class="alert-link">执行安装</a>，安装后请刷新页面或重新进入。';
                    installMsgEn = 'Print control is not installed! Click here to <a href="/static/lodop/install_lodop32.exe" target="_self" class="alert-link">install</a>. After installation, please refresh the page or re-enter.';
                }
                
                // 合并浏览器提示
                if (browserNote) {
                    installMsg = installMsg + '<br>' + browserNote;
                    installMsgEn = installMsgEn + '<br>' + browserNoteEn;
                }
                
                LodopNotification.show('warning', installMsg, installMsgEn, { autoClose: false });
                return LODOP;
            }
            
            // 检查版本
            if (LODOP.VERSION < "6.2.2.6") {
                var updateMsg, updateMsgEn;
                if (is64IE) {
                    updateMsg = '打印控件需要升级！点击这里<a href="/static/lodop/install_lodop64.exe" target="_self" class="alert-link">执行升级</a>，升级后请重新进入。';
                    updateMsgEn = 'Print control needs to be upgraded! Click here to <a href="/static/lodop/install_lodop64.exe" target="_self" class="alert-link">upgrade</a>. After upgrade, please re-enter.';
                } else {
                    updateMsg = '打印控件需要升级！点击这里<a href="/static/lodop/install_lodop32.exe" target="_self" class="alert-link">执行升级</a>，升级后请重新进入。';
                    updateMsgEn = 'Print control needs to be upgraded! Click here to <a href="/static/lodop/install_lodop32.exe" target="_self" class="alert-link">upgrade</a>. After upgrade, please re-enter.';
                }
                LodopNotification.show('warning', updateMsg, updateMsgEn, { autoClose: false });
            } else {
                // 测试成功
                LodopNotification.showTestSuccess();
            }
        }
        
        //===如下空白位置适合调用统一功能(如注册语句、语言选择等):==

        //=======================================================
        return LODOP;
    } catch (err) {
        LodopNotification.hide();
        LodopNotification.show('danger',
            'getLodop出错: ' + err,
            'getLodop error: ' + err,
            { delay: 5000 }
        );
    }
}
