
function GetHashImg(input_str) {
    // 利用 identicon 生成 hash 图片
    if(typeof input_str !== 'string' || input_str.length == 0) {
        input_str = 'xcpc';
    }
    let res = FNV1aHash(input_str);
    let rr = res & 255, gg = res >> 8 & 255, bb = res >> 16 & 255;
    input_str = FNV1aHash2Str(input_str, 16);
    let data = new Identicon(btoa(input_str), {size: 16, format: 'svg', foreground: [rr, gg, bb, 255]}).toString();
    return 'data:image/svg+xml;base64,' + data;
}

async function GetHashImgAsync(input_str) {
    // 参数验证
    if(typeof input_str !== 'string' || input_str.length == 0) {
        input_str = 'xcpc';
    }
    
    // 生成缓存键
    const cacheKey = `hash_img#${input_str}`;
    
    try {
        const cachedImg = await window.idb.idb(cacheKey);
        if (window.idb && typeof window.idb.GetIdb === 'function') {
            if (cachedImg) {
                return cachedImg;
            }
        }
    } catch (error) {
        console.warn('Failed to get hash img from idb:', error);
    }
    
    // 缓存中没有，生成新的 hash 图片
    const hashImg = GetHashImg(input_str);
    
    try {
        await window.idb.SetIdb(cacheKey, hashImg, 7 * 24 * 60 * 60 * 1000);
    } catch (error) {
        console.warn('Failed to cache hash img:', error);
    }
    
    return hashImg;
}

/**
 * 将元素替换为hash图片的通用函数
 * @param {HTMLElement} element - 目标元素
 * @param {string} sourceString - 生成hash图片的源字符串
 * @param {string} hashImg - hash图片的data URL
 */
function ReplaceElementWithHashImage(element, sourceString, hashImg) {
    // 如果是占位符div，替换为img元素
    if (element.tagName.toLowerCase() === 'div') {
        const img = document.createElement('img');
        img.className = element.className.replace('hash-img-placeholder', 'hash-img');
        img.src = hashImg;
        img.alt = sourceString;
        img.style.width = element.style.width || '32px';
        img.style.height = element.style.height || '32px';
        img.style.borderRadius = element.style.borderRadius || '50%';
        img.style.objectFit = 'cover';
        img.setAttribute('data-hash-source', sourceString);
        img.setAttribute('data-hash-img', '');
        
        // 替换原元素
        element.parentNode.replaceChild(img, element);
    } 
    // 如果是img元素，直接设置src
    else if (element.tagName.toLowerCase() === 'img') {
        element.src = hashImg;
    }
}

/**
 * 初始化页面中所有需要hash图片的元素
 * 约定：使用 data-hash-img 属性标记需要生成hash图片的元素
 * 约定：使用 data-hash-source 属性指定生成hash图片的源字符串
 */
function InitHashImages() {
    const hashElements = document.querySelectorAll('[data-hash-img]');
    hashElements.forEach(async function(element) {
        const sourceString = element.getAttribute('data-hash-source');
        if (sourceString && typeof GetHashImgAsync === 'function') {
            try {
                const hashImg = await GetHashImgAsync(sourceString);
                ReplaceElementWithHashImage(element, sourceString, hashImg);
            } catch (error) {
                console.warn('Failed to load hash image for source:', sourceString, error);
            }
        }
    });
}

/**
 * 为指定元素设置hash图片
 * @param {HTMLElement} element - 目标元素
 * @param {string} sourceString - 生成hash图片的源字符串
 */
async function SetHashImage(element, sourceString) {
    if (!element || !sourceString || typeof GetHashImgAsync !== 'function') {
        return;
    }
    
    try {
        const hashImg = await GetHashImgAsync(sourceString);
        ReplaceElementWithHashImage(element, sourceString, hashImg);
    } catch (error) {
        console.warn('Failed to set hash image for source:', sourceString, error);
    }
}