/**
 * 榜单系统工具函数库
 * 提供独立于业务逻辑的纯功能函数
 */

// 全局变量（以 ranktool_ 开头）

var ranktool_iconMap = {
    // 教练选手
    'coach': 'bi-person',
    'player': 'bi-people',
    // 队伍类型
    'team-regular': 'bi-flag-fill',
    'team-girl': 'bi-heart-fill',
    'team-star': 'bi-star-fill',
    // 打星模式图标
    'star-half': 'bi-star-half',
    'moon-stars-fill': 'bi-moon-stars-fill',
    'star-fill': 'bi-star-fill',
    // 控制按钮
    'refresh': 'bi-arrow-clockwise',
    'fullscreen': 'bi-fullscreen',
    'exit-fullscreen': 'bi-fullscreen-exit',
    'roll': 'bi-play-circle',
    'pause': 'bi-pause-circle',
    'stop': 'bi-stop-circle',
    'settings': 'bi-gear',
    'school': 'bi-building-fill-gear',
    'filter': 'bi-funnel',
    'export': 'bi-download',
    'print': 'bi-printer',
    'help': 'bi-question-circle',
    'close': 'bi-x-circle',
    'check': 'bi-check-circle',
    'warning': 'bi-exclamation-triangle',
    'info': 'bi-info-circle'
};

var ranktool_labelToIconMap = {
    '打星不排名': 'star-half',
    '不含打星': 'moon-stars-fill',
    '打星参与排名': 'star-fill'
};

// #########################################
//  字符串和文本处理函数
// #########################################

/**
 * HTML转义
 */
function RankToolEscapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * 检查字符串是否为空（包括null、undefined、空字符串、纯空格）
 */
function RankToolIsEmptyString(str) {
    return !str || str === null || str === undefined || (typeof(str) === 'string' && str.trim() === '');
}

/**
 * 生成双语文本HTML
 */
function RankToolGenerateBilingualText(label, label_en) {
    return `${label}<en-text>${label_en}</en-text>`;
}

/**
 * 生成双语HTML属性
 */
function RankToolGenerateBilingualAttributes(titlecn, titleen) {
    if (!titlecn && !titleen) return '';
    return `title-cn="${titlecn || ''}" title-en="${titleen || ''}"`;
}

/**
 * 生成双语title属性
 */
function RankToolGenerateBilingualTitle(titlecn, titleen) {
    if (!titlecn && !titleen) return '';
    let title = '';
    if (titlecn && titleen) {
        title = `${titlecn}\n${titleen}`;
    } else if (titlecn) {
        title = titlecn;
    } else if (titleen) {
        title = titleen;
    }
    return title;
}

// #########################################
//  图标相关函数
// #########################################

/**
 * 获取图标类名
 */
function RankToolGetIconClass(key) {
    return ranktool_iconMap[key] || 'bi-question-circle';
}

/**
 * 生成图标HTML（带双语tooltip）
 */
function RankToolGenerateIcon(key, cn = '', en = '') {
    const iconClass = RankToolGetIconClass(key);
    const iconHtml = `<i class="bi ${iconClass}"></i>`;
    // 如果有中英双语标签，添加tooltip属性
    if (cn && en) {
        return `<span ${RankToolGenerateBilingualAttributes(cn, en)}>${iconHtml}</span>`;
    }
    return iconHtml;
}

/**
 * 生成纯图标HTML（用于按钮）
 */
function RankToolGenerateIconOnly(key) {
    const iconClass = RankToolGetIconClass(key);
    return `<i class="bi ${iconClass}"></i>`;
}

/**
 * 从option中提取icon key（用于custom select）
 */
function RankToolGetIconKeyFromOption(option) {
    if (typeof option === 'string') {
        return ranktool_labelToIconMap[option] || 'star-half';
    }
    // 如果option是对象，检查label属性
    const label = option.label || option.label_cn || '';
    return ranktool_labelToIconMap[label] || 'star-half';
}

// #########################################
//  格式化和解析函数
// #########################################

/**
 * 将秒数转换为 HH:MM:SS 格式
 */
function RankToolFormatSecondsToHMS(seconds) {
    if (seconds == null || isNaN(seconds)) return '';
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const remainingSeconds = seconds % 60;
    return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${remainingSeconds.toString().padStart(2, '0')}`;
}

/**
 * 将秒数转换为分钟数（字符串）
 */
function RankToolFormatSecondsToMinutes(seconds) {
    if (seconds == null || isNaN(seconds)) return '0';
    return Math.floor(seconds / 60).toString();
}

/**
 * 格式化持续时间（毫秒转 HH:MM:SS）
 */
function RankToolFormatDuration(milliseconds) {
    const totalSeconds = Math.floor(milliseconds / 1000);
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;
    return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
}

/**
 * 将题目编号转换为字母标识（A, B, C, ...）
 */
function RankToolGetProblemAlphabetIdx(problemNum) {
    if (problemNum == null || isNaN(problemNum)) return '?';
    return String.fromCharCode('A'.charCodeAt(0) + problemNum);
}

/**
 * 解析颜色字符串
 */
function RankToolParseColor(colorString) {
    if (!colorString) return '#6b7280'; // 默认灰色
    // 如果是十六进制颜色（如 840004）
    if (/^[0-9A-F]{6}$/i.test(colorString)) {
        return '#' + colorString;
    }
    // 如果是十六进制颜色（如 #840004）
    if (/^#[0-9A-F]{6}$/i.test(colorString)) {
        return colorString;
    }
    // 如果是CSS颜色名，直接返回
    return colorString.toLowerCase();
}

// #########################################
//  配置处理函数
// #########################################

/**
 * 深度合并配置对象
 */
function RankToolMergeConfig(baseConfig, overrideConfig) {
    const result = { ...baseConfig };
    for (const key in overrideConfig) {
        if (overrideConfig.hasOwnProperty(key)) {
            if (typeof overrideConfig[key] === 'object' && 
                overrideConfig[key] !== null && 
                !Array.isArray(overrideConfig[key]) &&
                typeof result[key] === 'object' && 
                result[key] !== null && 
                !Array.isArray(result[key])) {
                // 递归合并对象
                result[key] = RankToolMergeConfig(result[key], overrideConfig[key]);
            } else {
                // 直接覆盖
                result[key] = overrideConfig[key];
            }
        }
    }
    return result;
}

// #########################################
//  获奖相关计算函数
// #########################################

/**
 * 解析奖牌比例数据
 * 格式：一个数字，千位表示铜牌比例，万位表示银牌比例，十万位表示金奖比例
 * 例如：10102030 表示 金奖10%，银牌10%，铜牌20%，总数30%
 */
function RankToolParseAwardRatio(awardRatio) {
    if (!awardRatio) return { gold: 10, silver: 20, bronze: 30 };
    let ratio = awardRatio;
    const gold = ratio % 1000;        
    ratio = Math.floor(ratio / 1000); 
    const silver = ratio % 1000;      
    ratio = Math.floor(ratio / 1000); 
    const bronze = ratio % 1000;      
    return { gold, silver, bronze };
}

/**
 * 验证奖牌比例（处理百分比和绝对数）
 */
function RankToolValidateAwardRatio(ratio) {
    if (ratio < 100 && ratio >= 0) {
        return ratio / 100.0; // 转换为小数（百分比）
    }
    return ratio; // 绝对数
}

/**
 * 计算奖牌名次线
 */
function RankToolGetAwardRank(cntBase, ratioGold, ratioSilver, ratioBronze) {
    ratioGold = RankToolValidateAwardRatio(ratioGold);
    ratioSilver = RankToolValidateAwardRatio(ratioSilver);
    ratioBronze = RankToolValidateAwardRatio(ratioBronze);
    
    let rankGold = ratioGold >= 100 ? ratioGold - 100 : Math.ceil(cntBase * ratioGold);
    let tmpRatioGold = ratioGold >= 100 ? rankGold / cntBase : ratioGold;
    
    let rankSilver = ratioSilver >= 100 ? rankGold + ratioSilver - 100 : Math.ceil(cntBase * (tmpRatioGold + ratioSilver));
    if (ratioSilver === 0) {
        rankSilver = rankGold;
    }
    
    let tmpRatioSilver = ratioSilver >= 100 ? rankSilver / cntBase : tmpRatioGold + ratioSilver;
    let rankBronze = ratioBronze >= 100 ? rankSilver + ratioBronze - 100 : Math.ceil(cntBase * (tmpRatioSilver + ratioBronze));
    if (ratioBronze === 0) {
        rankBronze = rankSilver;
    }
    
    return {
        rankGold, rankSilver, rankBronze, total: cntBase
    };
}

// #########################################
//  动画相关函数
// #########################################

/**
 * 计算动画持续时间（考虑速度倍率）
 * @param {number} baseDuration - 基础持续时间（毫秒）
 * @param {number} speedMultiplier - 速度倍率（默认为1.0）
 * @param {number} minDuration - 最小持续时间（毫秒，默认300）
 * @param {number} maxDuration - 最大持续时间（毫秒，默认2000）
 * @returns {number} 计算后的动画持续时间（毫秒）
 */
function RankToolCalculateAnimationDuration(baseDuration = 2000, speedMultiplier = 1.0, minDuration = 300, maxDuration = 2000) {
    const calculated = baseDuration / speedMultiplier;
    return Math.max(minDuration, Math.min(maxDuration, calculated));
}

// #########################################
//  数据结构模块
// #########################################

/**
 * 将 list 格式转换为 dict 格式
 * @param {Object} data - 包含 solution 和 team 数组的数据对象
 * @returns {Object} 转换后的数据对象（直接修改原对象）
 */
function RankToolConvertListToDict(data) {
    if (!data) return data;
    
    // 判断 solution 数据是否需要转换：list -> dict
    // 字段顺序：[0]solution_id, [1]contest_id, [2]problem_id, [3]team_id（数据库是team_id）, [4]result, [5]in_date
    if (data.solution && Array.isArray(data.solution) && data.solution.length > 0) {
        // 检查第一个元素是否为数组（list格式）还是对象（dict格式）
        const firstItem = data.solution[0];
        if (Array.isArray(firstItem)) {
            // 是 list 格式，需要转换
            data.solution = data.solution.map(item => ({
                solution_id: item[0],
                contest_id: item[1],
                problem_id: item[2],
                team_id: item[3],
                result: item[4],
                in_date: item[5]
            }));
        }
        // 如果已经是 dict 格式，则不需要转换
    }
    
    // 判断 team 数据是否需要转换：list -> dict
    // 字段顺序：[0]contest_id, [1]team_id, [2]name, [3]name_en, [4]coach, [5]tmember, [6]school, [7]region, [8]tkind, [9]room, [10]privilege, [11]team_global_code
    if (data.team && Array.isArray(data.team) && data.team.length > 0) {
        // 检查第一个元素是否为数组（list格式）还是对象（dict格式）
        const firstItem = data.team[0];
        if (Array.isArray(firstItem)) {
            // 是 list 格式，需要转换
            data.team = data.team.map(item => ({
                contest_id: item[0],
                team_id: item[1],
                name: item[2],
                name_en: item[3],
                coach: item[4],
                tmember: item[5],
                school: item[6],
                region: item[7],        // 国家/地区
                tkind: item[8],
                room: item[9],
                privilege: item[10],
                team_global_code: item[11]
            }));
        }
        // 如果已经是 dict 格式，则不需要转换
    }
    
    return data;
}

/**
 * 优先级队列（类似 C++ 的 std::priority_queue）
 * 默认是最大堆（队首是优先级最高的元素）
 * @template T
 */
if(typeof RankToolPriorityQueue == 'undefined') {   
    class RankToolPriorityQueue {
        /**
         * @param {function(T, T): number} compareFn - 比较函数，返回正数表示第一个参数优先级更高
         *                                          默认是最大堆（大的元素在前）
         */
        constructor(compareFn = null) {
            this.data = [];
            // 默认比较函数（最大堆）
            this.compareFn = compareFn || ((a, b) => {
                if (a < b) return 1;
                if (a > b) return -1;
                return 0;
            });
        }
        
        /**
         * 获取队列大小
         * @returns {number}
         */
        size() {
            return this.data.length;
        }
        
        /**
         * 判断队列是否为空
         * @returns {boolean}
         */
        empty() {
            return this.data.length === 0;
        }
        
        /**
         * 获取队首元素（不移除）
         * @returns {T|null}
         */
        top() {
            return this.empty() ? null : this.data[0];
        }
        
        /**
         * 入队
         * @param {T} item
         */
        push(item) {
            this.data.push(item);
            this._heapifyUp(this.data.length - 1);
        }
        
        /**
         * 出队
         * @returns {T|null}
         */
        pop() {
            if (this.empty()) {
                return null;
            }
            
            const top = this.data[0];
            const last = this.data.pop();
            
            if (this.data.length > 0) {
                this.data[0] = last;
                this._heapifyDown(0);
            }
            
            return top;
        }
        
        /**
         * 上浮（从底部向上调整）
         * @private
         */
        _heapifyUp(index) {
            while (index > 0) {
                const parentIndex = Math.floor((index - 1) / 2);
                if (this.compareFn(this.data[index], this.data[parentIndex]) <= 0) {
                    break;
                }
                this._swap(index, parentIndex);
                index = parentIndex;
            }
        }
        
        /**
         * 下沉（从顶部向下调整）
         * @private
         */
        _heapifyDown(index) {
            while (true) {
                let largest = index;
                const left = 2 * index + 1;
                const right = 2 * index + 2;
                
                if (left < this.data.length && 
                    this.compareFn(this.data[left], this.data[largest]) > 0) {
                    largest = left;
                }
                
                if (right < this.data.length && 
                    this.compareFn(this.data[right], this.data[largest]) > 0) {
                    largest = right;
                }
                
                if (largest === index) {
                    break;
                }
                
                this._swap(index, largest);
                index = largest;
            }
        }
        
        /**
         * 交换两个元素
         * @private
         */
        _swap(i, j) {
            [this.data[i], this.data[j]] = [this.data[j], this.data[i]];
        }
        
        /**
         * 清空队列
         */
        clear() {
            this.data = [];
        }
        
        /**
         * 获取所有元素（用于调试）
         * @returns {T[]}
         */
        toArray() {
            return [...this.data];
        }
    }

    // 导出到全局
    if (typeof window !== 'undefined') {
        window.RankToolPriorityQueue = RankToolPriorityQueue;
    }
}
// #########################################
//  缓存管理模块
// #########################################
// IndexedDB缓存管理器

if(typeof IndexedDBCache == 'undefined') {
    class IndexedDBCache {
        constructor(dbName = 'csgoj_rank', storeName = 'cache') {
            this.dbName = dbName;
            this.storeName = storeName;
            this.db = null;
            this.isReady = false;
            this.fallback = false;
        }
        // 初始化IndexedDB
        async init() {
            return new Promise((resolve) => {
                const openReq = indexedDB.open(this.dbName, 1);
                openReq.onupgradeneeded = (e) => {
                    const db = e.target.result;
                    if (!db.objectStoreNames.contains(this.storeName)) {
                        db.createObjectStore(this.storeName);
                    }
                };
                openReq.onsuccess = () => {
                    this.db = openReq.result;
                    this.isReady = true;
                    this.fallback = false;
                    resolve();
                };
                openReq.onerror = () => {
                    this.isReady = true;
                    this.fallback = true;
                    resolve();
                };
            });
        }
        // 获取数据（自动JSON解析）
        async get(key) {
            if (!this.isReady) {
                await this.init();
            }
            if (this.fallback) {
                const value = localStorage.getItem(key);
                if (value === null) return null;
                // localStorage fallback 需要手动解析，因为localStorage只存储字符串
                try {
                    return JSON.parse(value);
                } catch {
                    return value; // 返回原始字符串
                }
            }
            return new Promise((resolve) => {
                const tx = this.db.transaction([this.storeName], 'readonly');
                const os = tx.objectStore(this.storeName);
                const req = os.get(key);
                req.onsuccess = () => {
                    const result = req.result;
                    if (!result) {
                        resolve(null);
                        return;
                    }
                    // 检查是否过期
                    if (result.expireTime && Date.now() > result.expireTime) {
                        // 数据已过期，删除并返回 null
                        this.delete(key).then(() => resolve(null));
                        return;
                    }
                    // IndexedDB中存储的value已经是序列化后的字符串，需要解析
                    try {
                        resolve(JSON.parse(result.value));
                    } catch {
                        resolve(result.value); // 返回原始字符串
                    }
                };
                req.onerror = () => resolve(null);
            });
        }
        // 设置数据（自动JSON序列化）
        async set(key, value, expire = null) {
            if (!this.isReady) {
                await this.init();
            }
            // 自动JSON序列化
            const serializedValue = typeof value === 'string' ? value : JSON.stringify(value);
            if (this.fallback) {
                // localStorage fallback 不支持过期时间，直接存储
                localStorage.setItem(key, serializedValue);
                return;
            }
            return new Promise((resolve) => {
                const tx = this.db.transaction([this.storeName], 'readwrite');
                const os = tx.objectStore(this.storeName);
                const now = Date.now();
                const expireTime = expire && expire > 0 ? now + expire : null;
                const data = {
                    key: key,
                    value: serializedValue,
                    expireTime: expireTime,
                    createTime: now
                };
                const req = os.put(data, key);
                req.onsuccess = () => resolve();
                req.onerror = () => resolve();
            });
        }
        // 删除数据
        async delete(key) {
            if (!this.isReady) {
                await this.init();
            }
            if (this.fallback) {
                localStorage.removeItem(key);
                return;
            }
            return new Promise((resolve) => {
                const tx = this.db.transaction([this.storeName], 'readwrite');
                const os = tx.objectStore(this.storeName);
                const req = os.delete(key);
                req.onsuccess = () => resolve();
                req.onerror = () => resolve();
            });
        }
        // 清空所有数据
        async clear() {
            if (!this.isReady) {
                await this.init();
            }
            if (this.fallback) {
                localStorage.clear();
                return;
            }
            return new Promise((resolve) => {
                const tx = this.db.transaction([this.storeName], 'readwrite');
                const os = tx.objectStore(this.storeName);
                const req = os.clear();
                req.onsuccess = () => resolve();
                req.onerror = () => resolve();
            });
        }
    }
    window.IndexedDBCache = IndexedDBCache;
}

// #########################################
//  离线滚榜导出功能
// #########################################

/**
 * 标准化路径分隔符（Windows兼容）
 * 将路径统一使用正斜杠（/），适用于HTML中的路径引用
 */
function RankToolNormalizePath(path) {
    if (!path) return '';
    return path.replace(/\\/g, '/');
}

/**
 * 文件名安全化处理
 * 去掉所有不合法文件名字符，将连续的空格（包括单个空格和连续空格）替换成下划线
 * @param {string} filename - 原始文件名
 * @returns {string} 安全化的文件名
 */
function RankToolSanitizeFilename(filename) {
    if (!filename) return '';
    // 去掉不合法文件名字符（保留中文字符、字母、数字、连字符、下划线、点号）
    let sanitized = filename.replace(/[<>:"/\\|?*\x00-\x1f]/g, '');
    // 将所有连续的空格（包括单个空格）替换成下划线
    sanitized = sanitized.replace(/\s+/g, '_');
    // 去掉首尾的下划线
    sanitized = sanitized.replace(/^_+|_+$/g, '');
    return sanitized || 'contest';
}

/**
 * 生成14位时间戳（YYYYMMDDHHmmss）
 * @returns {string} 14位时间戳字符串
 */
function RankToolGenerateTimestamp14() {
    const now = new Date();
    const year = now.getFullYear().toString();
    const month = (now.getMonth() + 1).toString().padStart(2, '0');
    const day = now.getDate().toString().padStart(2, '0');
    const hours = now.getHours().toString().padStart(2, '0');
    const minutes = now.getMinutes().toString().padStart(2, '0');
    const seconds = now.getSeconds().toString().padStart(2, '0');
    return `${year}${month}${day}${hours}${minutes}${seconds}`;
}

/**
 * 标准化文件系统路径分隔符（Windows兼容）
 * 将路径统一使用正斜杠（/），适用于zip文件内的路径
 */
function RankToolNormalizeFileSystemPath(path) {
    if (!path) return '';
    return path.replace(/\\/g, '/').replace(/\/+/g, '/');
}

/**
 * 从CSS文件中提取字体文件URL
 */
async function RankToolExtractFontUrlsFromCSS(cssUrl) {
    try {
        const response = await fetch(cssUrl);
        if (!response.ok) return [];
        const cssText = await response.text();
        const fontUrls = [];
        // 匹配 url(...) 中的字体文件，包括查询参数
        const urlRegex = /url\(['"]?([^'"]*\.(woff2?|ttf|otf|eot))[^'"]*['"]?\)/gi;
        let match;
        while ((match = urlRegex.exec(cssText)) !== null) {
            let fontUrl = match[1];
            // 移除查询参数（如果有）
            if (fontUrl.includes('?')) {
                fontUrl = fontUrl.substring(0, fontUrl.indexOf('?'));
            }
            // 处理相对路径
            if (fontUrl.startsWith('./') || (!fontUrl.startsWith('/') && !fontUrl.startsWith('http'))) {
                // 相对路径，需要基于CSS文件路径计算
                const baseUrl = cssUrl.substring(0, cssUrl.lastIndexOf('/'));
                const relativePath = fontUrl.startsWith('./') ? fontUrl.substring(2) : fontUrl;
                fontUrls.push(RankToolNormalizePath(baseUrl + '/' + relativePath));
            } else if (fontUrl.startsWith('/')) {
                // 绝对路径
                fontUrls.push(fontUrl);
            }
        }
        return [...new Set(fontUrls)]; // 去重
    } catch (error) {
        console.warn('Failed to extract font URLs from CSS:', error);
        return [];
    }
}

/**
 * 获取资源文件（fetch并返回blob）
 */
async function RankToolFetchResource(url) {
    try {
        const response = await fetch(url);
        if (response.ok) {
            return await response.blob();
        }
        return null;
    } catch (error) {
        console.warn(`Failed to fetch resource: ${url}`, error);
        return null;
    }
}

/**
 * 计算旗帜文件路径（基于rank.js的逻辑）
 */
async function RankToolCalculateFlagFilePath(region, flagBaseUrl, flagMapping) {
    if (!region || typeof region !== 'string') return null;
    const trimmedRegion = region.trim();
    if (!trimmedRegion) return null;
    
    // 先尝试从映射表查找
    if (flagMapping && flagMapping.has(trimmedRegion)) {
        const fileName = flagMapping.get(trimmedRegion);
        return RankToolNormalizePath(`${flagBaseUrl}/${fileName}`);
    }
    // 映射表没找到，直接尝试 region.png
    return RankToolNormalizePath(`${flagBaseUrl}/${encodeURIComponent(trimmedRegion)}.png`);
}

/**
 * 加载旗帜映射数据
 */
async function RankToolLoadFlagMapping(flagBaseUrl) {
    const mappingUrl = RankToolNormalizePath(`${flagBaseUrl}/region_mapping.json`);
    try {
        const response = await fetch(mappingUrl);
        if (!response.ok) return new Map();
        const data = await response.json();
        const mapping = new Map();
        data.forEach(region => {
            if (region['中文名']) mapping.set(region['中文名'], region['文件名']);
            if (region['中文简称']) mapping.set(region['中文简称'], region['文件名']);
            if (region['英文名']) mapping.set(region['英文名'], region['文件名']);
            if (region['英文简称']) mapping.set(region['英文简称'], region['文件名']);
            if (region['英文缩写']) mapping.set(region['英文缩写'], region['文件名']);
        });
        return mapping;
    } catch (error) {
        console.warn('Failed to load flag mapping:', error);
        return new Map();
    }
}

/**
 * 导出离线滚榜包
 * @param {RankRollSystem} rankRollSystem - RankRollSystem实例
 * @param {Function} progressCallback - 进度回调函数 (message, progress)
 * @returns {Promise<Blob>} zip文件的Blob
 */
async function RankToolExportOfflineRollPack(rankRollSystem, progressCallback = null) {
    if (!window.zip) {
        throw new Error('zip.js library is not loaded');
    }
    
    if (!rankRollSystem || !rankRollSystem.data) {
        throw new Error('RankRollSystem instance or data not available');
    }
    
    const config = rankRollSystem.config || window.RANK_CONFIG || {};
    const updateProgress = (message, progress) => {
        if (progressCallback) progressCallback(message, progress);
    };
    
    // 初始化zip
    const zipWriter = new zip.ZipWriter(new zip.BlobWriter('application/zip'));
    const addedFiles = new Set(); // 避免重复文件
    
    // 统计文件数
    let totalFiles = 0;
    let processedFiles = 0;
    
    // 必需的文件列表
    const requiredFiles = [
        '/static/js/csg_anim.js',
        '/static/csgoj/contest/rank_tool.js',
        '/static/csgoj/contest/rank.js',
        '/static/csgoj/contest/rank_roll.js',
        '/static/csgoj/contest/rank.css'
    ];
    totalFiles += requiredFiles.length;
    
    // Bootstrap Icons CSS
    const bootstrapIconsCSS = '/static/bootstrap-icons-1.13.1/font/bootstrap-icons.min.css';
    totalFiles += 1;
    
    // 数据文件
    totalFiles += 1;
    
    // 计算图片文件数
    const teams = rankRollSystem.data.team || [];
    const schools = new Set();
    const regions = new Set();
    teams.forEach(team => {
        if (team.team_id) totalFiles += 1; // 队伍照片
        if (team.school) schools.add(team.school);
        if (team.region) regions.add(team.region);
    });
    // 注意：校徽和旗帜不再打包文件（只生成base64到images.js），所以不计算到totalFiles
    
    // 字体文件（估算2个：woff2和woff）
    totalFiles += 2;
    
    // 辅助函数：添加文件到zip
    const addFileToZip = async (filePath, content, zipPath = null) => {
        const normalizedZipPath = RankToolNormalizeFileSystemPath(zipPath || filePath);
        if (addedFiles.has(normalizedZipPath)) {
            return; // 跳过重复文件
        }
        
        let blob;
        if (content instanceof Blob) {
            blob = content;
        } else if (typeof content === 'string') {
            blob = new Blob([content], { type: 'text/plain' });
        } else {
            return; // 无效内容
        }
        
        await zipWriter.add(normalizedZipPath, new zip.BlobReader(blob));
        addedFiles.add(normalizedZipPath);
        processedFiles++;
        updateProgress(`正在打包 ${normalizedZipPath}`, (processedFiles / totalFiles * 100).toFixed(2));
    };
    
    // 辅助函数：获取并添加远程文件
    const fetchAndAddFile = async (url, zipPath) => {
        const normalizedUrl = RankToolNormalizePath(url);
        const normalizedZipPath = RankToolNormalizeFileSystemPath(zipPath || normalizedUrl);
        
        if (addedFiles.has(normalizedZipPath)) {
            return; // 已添加
        }
        
        const blob = await RankToolFetchResource(normalizedUrl);
        if (blob) {
            await zipWriter.add(normalizedZipPath, new zip.BlobReader(blob));
            addedFiles.add(normalizedZipPath);
            processedFiles++;
            updateProgress(`正在打包 ${normalizedZipPath}`, (processedFiles / totalFiles * 100).toFixed(2));
        }
    };
    
    // 1. 打包必需的JS和CSS文件
    updateProgress('正在获取必需文件...', 0);
    for (const filePath of requiredFiles) {
        // 将绝对路径转换为相对路径（去掉前导斜杠）
        const zipPath = filePath.startsWith('/') ? filePath.substring(1) : filePath;
        await fetchAndAddFile(filePath, zipPath);
    }
    
    // 2. 打包Bootstrap Icons CSS并提取字体文件
    updateProgress('正在获取Bootstrap Icons...', 0);
    const bootstrapIconsZipPath = bootstrapIconsCSS.startsWith('/') ? bootstrapIconsCSS.substring(1) : bootstrapIconsCSS;
    await fetchAndAddFile(bootstrapIconsCSS, bootstrapIconsZipPath);
    
    // 提取字体文件URL
    const fontUrls = await RankToolExtractFontUrlsFromCSS(bootstrapIconsCSS);
    for (const fontUrl of fontUrls) {
        // Bootstrap Icons字体通常在同一目录的fonts子目录下
        const fontPath = RankToolNormalizePath(fontUrl);
        // 将绝对路径转换为相对路径（去掉前导斜杠）
        const fontZipPath = fontPath.startsWith('/') ? fontPath.substring(1) : fontPath;
        await fetchAndAddFile(fontPath, fontZipPath);
    }
    
    // 3. 打包图片资源
    updateProgress('正在获取图片资源...', 0);
    
    // 初始化离线图片对象（用于生成images.js）
    const offlineImages = {
        school_badge: {},
        region_flag: {}
    };
    
    // 辅助函数：将Blob转换为base64 data URL
    const imageToBase64 = async (blob) => {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onloadend = () => resolve(reader.result);
            reader.onerror = reject;
            reader.readAsDataURL(blob);
        });
    };
    
    // 3.1 队伍照片
    const teamPhotoBase = config.team_photo_url || '/upload/contest_attach/default/team_photo';
    const teamPhotoZipBase = 'static/team_photo';
    for (const team of teams) {
        if (team.team_id) {
            const photoUrl = RankToolNormalizePath(`${teamPhotoBase}/${team.team_id}.jpg`);
            const photoZipPath = RankToolNormalizeFileSystemPath(`${teamPhotoZipBase}/${team.team_id}.jpg`);
            await fetchAndAddFile(photoUrl, photoZipPath);
        }
    }
    
    // 3.2 学校徽章（只生成base64，不打包文件）
    const schoolBadgeBase = config.school_badge_url || '/static/image/school_badge';
    for (const school of schools) {
        if (school) {
            const badgeUrl = RankToolNormalizePath(`${schoolBadgeBase}/${school}.jpg`);
            try {
                const blob = await RankToolFetchResource(badgeUrl);
                if (blob) {
                    // 转换为base64并记录（使用URL编码的学校名作为key，与rank.js中的逻辑一致）
                    const encodedSchool = encodeURIComponent(school);
                    const base64DataUrl = await imageToBase64(blob);
                    offlineImages.school_badge[encodedSchool] = base64DataUrl;
                    updateProgress(`正在处理校徽: ${school}`, (processedFiles / totalFiles * 100).toFixed(2));
                }
            } catch (e) {
                // 图片加载失败，跳过（不记录到offlineImages）
                console.warn(`Failed to load school badge: ${school}`, e);
            }
        }
    }
    
    // 3.3 旗帜（需要先加载映射，只生成base64，不打包文件）
    updateProgress('正在加载旗帜映射...', 0);
    const flagBaseUrl = config.region_flag_url || '/static/image/region_flag';
    const flagMapping = await RankToolLoadFlagMapping(flagBaseUrl);
    
    for (const region of regions) {
        if (region) {
            const flagUrl = await RankToolCalculateFlagFilePath(region, flagBaseUrl, flagMapping);
            if (flagUrl) {
                // 提取文件名
                const fileName = flagUrl.substring(flagUrl.lastIndexOf('/') + 1);
                try {
                    const blob = await RankToolFetchResource(flagUrl);
                    if (blob) {
                        // 转换为base64并记录（使用文件名作为key）
                        const base64DataUrl = await imageToBase64(blob);
                        offlineImages.region_flag[fileName] = base64DataUrl;
                        updateProgress(`正在处理旗帜: ${fileName}`, (processedFiles / totalFiles * 100).toFixed(2));
                    }
                } catch (e) {
                    // 图片加载失败，跳过
                    console.warn(`Failed to load flag: ${fileName}`, e);
                }
            }
        }
    }
    
    // 4. 打包数据文件
    updateProgress('正在打包数据文件...', 0);
    // 打包为JSON格式（用于api_url加载）
    const dataJson = JSON.stringify({
        code: 1,
        msg: 'ok',
        data: rankRollSystem.data
    }, null, 2);
    await addFileToZip('static/data.json', dataJson, 'static/data.json');
    
    // 也打包为JS格式（用于直接加载，兼容性）
    const dataJs = `var cdata = ${JSON.stringify(rankRollSystem.data)};`;
    await addFileToZip('static/data.js', dataJs, 'static/data.js');
    
    // 4.5 生成离线图片数据文件（images.js）
    updateProgress('正在生成离线图片数据...', 0);
    const imagesJs = `window.OFFLINE_IMAGES = ${JSON.stringify(offlineImages, null, 2)};`;
    await addFileToZip('static/images.js', imagesJs, 'static/images.js');
    
    // 5. 生成index.html
    updateProgress('正在生成index.html...', 0);
    
    // 构建配置（使用相对路径）
    // api_url 设置为 cdata 对象，rank.js 会识别并直接使用
    const offlineConfig = {
        key: config.key || 'offline_roll',
        cid_list: config.cid_list || '',
        api_url: null, // 将在脚本中设置为 cdata
        team_photo_url: './static/team_photo',
        school_badge_url: './static/school_badge',
        region_flag_url: './static/region_flag',
        rank_mode: 'roll',
        flg_rank_cache: false, // 离线模式禁用缓存
        flg_show_page_contest_title: config.flg_show_page_contest_title !== undefined ? config.flg_show_page_contest_title : false,
        backend_time_diff: config.backend_time_diff || 0,
        flg_show_time_progress: config.flg_show_time_progress !== undefined ? config.flg_show_time_progress : false,
        flg_show_controls_toolbar: config.flg_show_controls_toolbar !== undefined ? config.flg_show_controls_toolbar : false,
        flg_show_export_offline_roll: false  // 离线滚榜包中不显示导出按钮
    };
    
    // 转义HTML中的特殊字符
    const contestTitle = (rankRollSystem.data?.contest?.title || '滚榜').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    
    const indexHtml = `<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>${contestTitle}</title>
    <link rel="stylesheet" type="text/css" href="./static/csgoj/contest/rank.css">
    <link rel="stylesheet" type="text/css" href="./static/bootstrap-icons-1.13.1/font/bootstrap-icons.min.css">
    <style>
        /* 页头样式 - Bootstrap 5 风格 */
        body {
            margin: 0;
            padding: 0;
            background-color: #f8f9fa;
        }
        .offline-header {
            background-color: #ffffff;
            border-bottom: 1px solid #dee2e6;
            padding: 1.5rem 1rem;
            margin-bottom: 0;
        }
        .offline-header-content {
            max-width: 1200px;
            margin: 0 auto;
        }
        .offline-header-title {
            font-size: 2rem;
            font-weight: 500;
            color: #212529;
            margin: 0 0 0.5rem 0;
            line-height: 1.3;
            word-wrap: break-word;
            word-break: break-word;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .offline-header-subtitle {
            font-size: 0.875rem;
            color: #6c757d;
            margin: 0;
            font-weight: 400;
        }
        @media (max-width: 768px) {
            .offline-header {
                padding: 1.25rem 0.75rem;
            }
            .offline-header-title {
                font-size: 1.5rem;
                -webkit-line-clamp: 2;
            }
            .offline-header-subtitle {
                font-size: 0.8125rem;
            }
        }
        .offline-rank-wrapper {
            max-width: 1200px;
            width: 100%;
            margin: 0 auto;
            padding: 1rem;
        }
        #rank-container {
            background-color: #ffffff;
        }
    </style>
</head>
<body>
    <!-- 页头 -->
    <header class="offline-header">
        <div class="offline-header-content">
            <h1 class="offline-header-title">${contestTitle}</h1>
            <p class="offline-header-subtitle">离线滚榜 Offline Roll Ranking</p>
        </div>
    </header>
    
    <!-- 榜单包装容器（用于居中） -->
    <div class="offline-rank-wrapper">
        <div id="rank-container"></div>
    </div>

    <!-- 先加载数据文件 -->
    <script type="text/javascript" src="./static/data.js"></script>
    <!-- 加载离线图片数据 -->
    <script type="text/javascript" src="./static/images.js"></script>
    
    <!-- 再加载依赖的JS文件 -->
    <script type="text/javascript" src="./static/js/csg_anim.js"></script>
    <script type="text/javascript" src="./static/csgoj/contest/rank_tool.js"></script>
    <script type="text/javascript" src="./static/csgoj/contest/rank.js"></script>
    <script type="text/javascript" src="./static/csgoj/contest/rank_roll.js"></script>
    
    <script>
        // 配置信息 - 离线滚榜页面配置
        // 将 api_url 设置为 cdata 对象（rank.js 会识别并直接使用）
        window.RANK_CONFIG = ${JSON.stringify(offlineConfig, null, 8)};
        if (typeof cdata !== 'undefined') {
            window.RANK_CONFIG.api_url = cdata;
        } else {
            console.error('cdata not found. Make sure data.js is loaded.');
        }
        
        // 等待DOM加载完成后初始化
        (function() {
            function initRollSystem() {
                if (typeof RankRollSystem === 'undefined') {
                    console.error('RankRollSystem not found. Make sure all JS files are loaded.');
                    return;
                }
                
                if (typeof cdata === 'undefined') {
                    console.error('cdata not found. Make sure data.js is loaded.');
                    return;
                }
                
                // 初始化滚榜系统（会自动识别 api_url 是数据对象）
                const rollSystem = new RankRollSystem('rank-container', window.RANK_CONFIG);
                
                // 将 rollSystem 保存到全局，方便调试
                window.rollSystem = rollSystem;
            }
            
            // 如果DOM已经加载完成，直接初始化；否则等待
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initRollSystem);
            } else {
                // 延迟一下确保所有脚本都加载完成
                setTimeout(initRollSystem, 100);
            }
        })();
    </script>
</body>
</html>`;
    
    await addFileToZip('index.html', indexHtml, 'index.html');
    
    // 6. 完成打包
    updateProgress('正在完成打包...', 100);
    const zipBlob = await zipWriter.close();
    
    return zipBlob;
}

// 导出到全局
if (typeof window !== 'undefined') {
    window.RankToolExportOfflineRollPack = RankToolExportOfflineRollPack;
    window.RankToolNormalizePath = RankToolNormalizePath;
    window.RankToolNormalizeFileSystemPath = RankToolNormalizeFileSystemPath;
    window.RankToolSanitizeFilename = RankToolSanitizeFilename;
    window.RankToolGenerateTimestamp14 = RankToolGenerateTimestamp14;
}