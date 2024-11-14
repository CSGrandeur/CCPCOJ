var csg = {
    ajax: async function(method, url, data={}, dtype='json') {
        let fetchBody = {
            method: method,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json',
            },
        }
        if(method.toLowerCase() == 'get') {
            let tmp = this.Json2Url(data);
            if(tmp !== undefined) {
                if(url.indexOf("?") != -1) {
                    url += "&"
                } else {
                    url += "?"
                }
                url += tmp;
            }
        } else {
            fetchBody['body'] = JSON.stringify(data);
        }
        return fetch(url, fetchBody);
    },
    get: function(url, data={}, dtype='json') {
        return this.ajax('get', url, data, dtype);
    },
    post: function(url, data={}, dtype='json') {
        return this.ajax('post', url, data, dtype);
    },
    Json2Url(data) {
        return new URLSearchParams(data).toString();
    },
    Url2Json() {
        return Object.fromEntries(new URLSearchParams(location.search));
    },
    async_ajax: async function(method, url, data={}, headers={}, dtype='json') {
        let req_headers = Object.assign({}, {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}, headers);
        let fetchBody = {
            method: method,
            headers: req_headers,
        }
        if(method.toLowerCase() == 'get') {
            let tmp = this.Json2Url(data);
            if(tmp !== undefined) {
                if(url.indexOf("?") != -1) {
                    url += "&"
                } else {
                    url += "?"
                }
                url += tmp;
            }
        } else {
            fetchBody['body'] = JSON.stringify(data);
        }
        const response = await fetch(url, fetchBody);
        return response.json();
    },
    async_get: async function(url, data={}, headers={}, dtype='json') {
        return this.async_ajax('get', url, data, headers, dtype);
    },
    async_post: async function(url, data={}, headers={}, dtype='json') {
        return this.async_ajax('post', url, data, headers, dtype);
    },
    async_put: async function(url, data={}, headers={}, dtype='json') {
        return this.async_ajax('put', url, data, headers, dtype);
    },
    docready: function(readyfunc) {
        // 会比 $(document).ready() 触发时机更早，慎用
        document.addEventListener("DOMContentLoaded", readyfunc); 
    },
    getdom: function(domstr) {
        let ret = document.querySelectorAll(domstr);
        if(domstr[0] == '#') {
            return ret[0];
        } else if(domstr[0] == '.') {
            // return document.getElementsByClassName(domstr.slice(1));
            return ret;
        } else if(domstr[0] == '/') {
            return document.getElementsByName(domstr.slice(1));
        } else{
            return document.getElementsByTagName(domstr);
        }
        return ret;
    },
    sleep: function(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    },
    keydown: function(func) {
        this.on('keydown', func);
    },
    keyup: function(func) {
        this.on('keyup', func);
    },
    // Event Handler
    on: function(eventName, func) {
        window.addEventListener(eventName, func);
    },
    create: function(domstr) {
        return new DOMParser().parseFromString(domstr, "text/html").body.firstElementChild;
    },
    DateFormat: function(date, fmt='yyyy-MM-dd HH:mm:ss') {
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
    },
    TimeNow: function(fmt='yyyy-MM-dd HH:mm:ss') {
        return this.DateFormat(new Date(), fmt);
    },
    GetAnchor: function(key=null) {
        let anchor_str = window.location.hash.substr(1);
        if(key === null) return anchor_str;
        var reg = new RegExp("(^|#)" + key + "=([^#]*?)(#|$)");
        var r = anchor_str.match(reg);
        if (r != null) return decodeURI(r[2]); return null;
    },
    SetAnchor: function(val, key=null) {
        let anchor_str = "";
        if(key === null) anchor_str = val;
        else {
            anchor_str = window.location.hash.substr(1);
            var reg = new RegExp("(^|#)" + key + "=([^#]*?)(#|$)");
            var r = anchor_str.match(reg);
            if(val === null || val === "") {
                if(r !== null) {
                    anchor_str = anchor_str.replace(reg, "");
                }
            } else {
                if (r != null) {
                    anchor_str = anchor_str.replace(reg, "$1" + key + "=" + val + "$3");
                }
                else {
                    if(anchor_str === "") anchor_str = key + '=' + val;
                    else anchor_str += '#' + key + '=' + val;
                }
            }
        }
        window.location.hash = '#' + anchor_str;
    },
    GetUrlParam: function() {
        const searchURL = location.search; // 获取到URL中的参数串
        const params = new URLSearchParams(searchURL); // 创建一个URLSearchParams对象
        const valueObj = Object.fromEntries(params); // 转换为普通对象
        return valueObj; // 返回对象
    },
    cookie: function(key, value=null) {
        if(value === null) {
            return this.GetCookie(key);
        }
        this.SetCookie(key, value)
    },
    cookie_json: function(key, value=null) {
        if(value === null) {
            return JSON.parse(this.GetCookie(key));
        }
        this.SetCookie(key, JSON.stringify(value));
    },
    // 读取cookie
    GetCookie: function(key, default_v=null) {
        let cookie = document.cookie;
        let cookieName = encodeURIComponent(key) + "=";
        let cookieStart = cookie.indexOf(cookieName);
        let cookieValue = null;
        if (cookieStart > -1) {
            let cookieEnd = cookie.indexOf(";", cookieStart);
            if (cookieEnd == -1) {
                cookieEnd = cookie.length;
            }
            cookieValue = decodeURIComponent(cookie.substring(cookieStart + cookieName.length, cookieEnd));
        } else {
            cookieValue = default_v;
        }
        return cookieValue;
    },
  // 设置cookie
    SetCookie: function(key, value, expires, path, domain, secure) {
        let cookieText = encodeURIComponent(key) + "=" + encodeURIComponent(value);
        if (expires instanceof Date) {
            cookieText += "; expires=" + expires.toUTCString();
        } else if(typeof expires === 'number') {
            var date = new Date();
            date.setTime(date.getTime() + (expires * 60 * 60 * 1000));  // expires表示小时
            cookieText += "; expires=" + date.toUTCString();
        }
        if (path) {
            cookieText += "; path=" + path;
        }
        if (domain) {
            cookieText += "; domain=" + domain;
        }
        if (secure) {
            cookieText += "; secure";
        }
        document.cookie = cookieText;
    },
    // 删除cookie
    DelCookie: function(key, path, domain, secure) {
        this.SetCookie(key, "", new Date(0), path, domain, secure);
    },
    store: function(key, val=null, expire=null) {
        // expire 单位毫秒
        if(val === null) {
            return this.GetStore(key);
        }
        this.SetStore(key, val, expire);
    },
    GetStore: function(key, default_val=null) {
        let val = localStorage.getItem(key);
        if (val) {
            let item = JSON.parse(val);
            if(item.expire != null && Date.now() - item.time > item.expire) {
                localStorage.removeItem(key);
                return null;
            } else {
                return item.data;
            }
        }
        return default_val;

    },
    SetStore: function(key, val, expire=null) {
        const item = {
            data: val,
            time: Date.now(),
            expire: expire
        };
        localStorage.setItem(key, JSON.stringify(item));
    },
    DelStore: function(key) {
        localStorage.removeItem(key);
    }

}