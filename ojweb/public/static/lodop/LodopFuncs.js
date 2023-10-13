﻿var CreatedOKLodopObject, CLodopIsLocal, CLodopJsState;

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
    var strHtmInstall="<br><span class='alert alert-warning'>打印控件未安装!点击这里<a href='/static/lodop/install_lodop32.exe' target='_self'>执行安装</a>,安装后请刷新页面或重新进入。</span>";
    var strHtmUpdate="<br><span class='alert alert-warning'>打印控件需要升级!点击这里<a href='/static/lodop/install_lodop32.exe' target='_self'>执行升级</a>,升级后请重新进入。</span>";
    var strHtm64_Install="<br><span class='alert alert-warning'>打印控件未安装!点击这里<a href='/static/lodop/install_lodop64.exe' target='_self'>执行安装</a>,安装后请刷新页面或重新进入。</span>";
    var strHtm64_Update="<br><span class='alert alert-warning'>打印控件需要升级!点击这里<a href='/static/lodop/install_lodop64.exe' target='_self'>执行升级</a>,升级后请重新进入。</span>";
    var strHtmFireFox="<br><br><span class='alert alert-warning'>（注意：如曾安装过Lodop旧版附件npActiveXPLugin,请在【工具】->【附加组件】->【扩展】中先卸它）</span>";
    var strHtmChrome="<br><br><span class='alert alert-warning'>(如果此前正常，仅因浏览器升级或重安装而出问题，需重新执行以上安装）</span>";
    var strCLodopInstall_1 = "<br/><span class='alert alert-warning'>Web打印服务CLodop未安装启动，点击这里<a href='/static/lodop/CLodop_Setup_for_Win32NT.exe' target='_self'>下载执行安装</a>";
    var strCLodopInstall_2 = "（若此前已安装过，可<a href='CLodop.protocol:setup' target='_self'>点这里直接再次启动</a>）";
    var strCLodopInstall_3 = "，成功后请刷新或重启浏览器。</span>";
    var strCLodopUpdate = "<br><span class='alert alert-warning'>Web打印服务CLodop需升级!点击这里<a href='/static/lodop/CLodop_Setup_for_Win32NT.exe' target='_self'>执行升级</a>,升级后请刷新或重启浏览器。</span>";
    var LODOP;
    try {
        var ua = navigator.userAgent;
        var isIE = !!(ua.match(/MSIE/i)) || !!(ua.match(/Trident/i));
        if (needCLodop()) {
            try {
                LODOP = getCLodop();
            } catch (err) {}
            if (!LODOP && CLodopJsState !== "complete") {
                if (CLodopJsState == "loading") alert("网页还没下载完毕，请稍等一下再操作."); else alert("没有加载CLodop的主js，请先调用loadCLodop过程.");
                return;
            }
            if (!LODOP) {
                documentMain.innerHTML = strCLodopInstall_1 + (CLodopIsLocal ? strCLodopInstall_2 : "") + strCLodopInstall_3 + documentMain.innerHTML;
                return;
            } else {
                if (CLODOP.CVERSION < "4.1.4.5") {
                    documentMain.innerHTML = strCLodopUpdate + documentMain.innerHTML;
                }
                if (oEMBED && oEMBED.parentNode)
                    oEMBED.parentNode.removeChild(oEMBED); //清理旧版无效元素
                if (oOBJECT && oOBJECT.parentNode)
                    oOBJECT.parentNode.removeChild(oOBJECT);
            }
        } else {
            var is64IE = isIE && !!(ua.match(/x64/i));
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
                if (ua.indexOf('Chrome') >= 0)
                    documentMain.innerHTML = strHtmChrome + documentMain.innerHTML;
                if (ua.indexOf('Firefox') >= 0)
                    documentMain.innerHTML = strHtmFireFox + documentMain.innerHTML;
                documentMain.innerHTML = (is64IE ? strHtm64_Install : strHtmInstall) + documentMain.innerHTML;
                return LODOP;
            }
        }
        if (LODOP.VERSION < "6.2.2.6") {
            if (!needCLodop())
                documentMain.innerHTML = (is64IE ? strHtm64_Update : strHtmUpdate) + documentMain.innerHTML;
        }
        //===如下空白位置适合调用统一功能(如注册语句、语言选择等):==


        //=======================================================
        return LODOP;
    } catch (err) {
        alert("getLodop出错:" + err);
    }
}
