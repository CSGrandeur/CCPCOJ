let sl_db = null;
let IDDB_NAME = 'csgoj';
let IDDB_TABLE = ['ranktable', 'logotable'];
let IDDB_VERSION = 1;
function InitIndexedDb(store_name, dbcallback) {
    flag_init_iddb = true;
    let ldb_request = indexedDB.open(IDDB_NAME, 1);
    let SaveLocalIdDb;
    let LoadLocalIdDb;
    ldb_request.onupgradeneeded = function(e) {
        local_db = e.target.result;
        for(let i = 0; i < IDDB_TABLE.length; i ++) {
            if(!local_db.objectStoreNames.contains(IDDB_TABLE[i])) {
                local_db.createObjectStore(IDDB_TABLE[i]);
            }
        }
    }
    ldb_request.onsuccess = function(e) {
        local_db = e.target.result;
        SaveLocalIdDb = async function(key, val) {
            let addTransaction = local_db.transaction([store_name], 'readwrite');
            let addStore = addTransaction.objectStore(store_name);
            let addRequest = addStore.put(val, key);
            addRequest.onerror = function(e) {
                console.error("IndexedDB写入失败", key, val);
            }
    
        }
        LoadLocalIdDb = async function(key) {
            return new Promise((resolve, reject) => {
                let loadTransaction = local_db.transaction([store_name], 'readonly');
                let loadStore = loadTransaction.objectStore(store_name);
                let loadRequest = loadStore.get(key);
                loadRequest.onerror = function(e) {
                    resolve(null);
                }
                loadRequest.onsuccess = function(e) {
                    if(typeof loadRequest.result === 'undefined' || loadRequest.result == null) {
                        resolve(null);
                    }
                    resolve(loadRequest.result);
                }
            })
        }
        dbcallback({
            db: local_db,
            get: LoadLocalIdDb,
            set: SaveLocalIdDb
        });
    }
    ldb_request.onerror = function(e) {
        SaveLocalIdDb = async function(key, val) {
            if(typeof(val) != 'string') {
                val = JSON.stringify(val);
            }
            localStorage.setItem(key, val);
        }
        LoadLocalIdDb = async function(key) {
            return new Promise((resolve, reject) => {
                let logo = localStorage.getItem(key);
                resolve(logo);
            })
        }
        dbcallback({
            local_db: null,
            get: LoadLocalIdDb,
            set: SaveLocalIdDb
        });
    }
}
InitIndexedDb('logotable', function(local_db) {sl_db = local_db;});  // 初始化 IndexedDB

function SchoolLogoUri(school_str) {
    return '/static/image/school_badge/' + school_str + '.jpg';
}
// const IMG_TRY_LIST = ["svg", "jpg"]; // 即使懒加载，30kb的svg也很卡
const IMG_TRY_LIST = ["jpg"];
function FetchSchoolLogo(school_str) {
    let i = 0;
    function next() {
        if(i < IMG_TRY_LIST.length) {
            return fetch(`${school_str}.${IMG_TRY_LIST[i ++]}`).then(rep => {
                if(!rep.ok) {
                    return next();
                }
                return rep;
            })
        } else {
            throw new Error('No valid school logo file found');
        }
    }
    return next();
}
async function SchoolLogoUriAuto(school_str) {
    const key = '/static/image/school_badge/' + Any2Ascii(school_str);
    const file_key = '/static/image/school_badge/' + school_str;
    return new Promise((resolve, reject) => {
        sl_db.get(key).then(logo => {
            // logo = null;    // 用于更新logo
            if(logo == null) {
                FetchSchoolLogo(file_key)
                    .then(response => response.blob())
                    .then(blob => {
                        const reader = new FileReader();
                        reader.onloadend = function() {
                            const base64data = reader.result;
                            sl_db.set(key, base64data);
                            resolve(base64data);
                        }
                        reader.readAsDataURL(blob);
                    })
                    .catch(() => {
                        const hashImg = GetHashImg(school_str);
                        // sl_db.set(key, hashImg);    // 注释后动态生成的不设置缓存
                        resolve(hashImg);
                    });
            } else {
                resolve(logo);
            }
        })
    });
}
function GetHashImg(school_str) {
    if(typeof school_str !== 'string' || school_str.length == 0) {
        school_str = 'xcpc';
    }
    let res = FNV1aHash(school_str);
    let rr = res & 255, gg = res >> 8 & 255, bb = res >> 16 & 255;
    school_str = FNV1aHash2Str(school_str, 16);
    let data = new Identicon(btoa(school_str), {size: 16, format: 'svg', foreground: [rr, gg, bb, 255]}).toString();
    return 'data:image/svg+xml;base64,' + data;
}
function SchoolLogoUriOnError(img_obj) {
    img_obj.src = GetHashImg(img_obj.getAttribute('school'));
}
let observer = new IntersectionObserver(async (entries, observer) => {
    for(let entry of entries) {
        if (entry.isIntersecting) {
            let img = entry.target;
            SchoolLogoUriAuto(img.getAttribute('school')).then(rep => {
                img.src = rep;
            });
            observer.unobserve(img);
        }
    }
});

function BatchProcessSchoolLogo() {
    if(sl_db === null) {
        setTimeout(BatchProcessSchoolLogo, 100);
    } else {
        document.querySelectorAll('.school_logo').forEach(img => {
            observer.observe(img);
        });
    }
    // const school_logo_img_list = document.getElementsByClassName('school_logo');
    // let task_list = [], img_dom_list = [];
    // for (let i = 0; i < school_logo_img_list.length; i++) {
    //     const img = school_logo_img_list[i];
    //     const school_str = img.getAttribute('school');
    //     img_dom_list.push(img);
    //     task_list.push(SchoolLogoUriAuto(school_str));
    // }
    // Promise.all(task_list).then((results) => {
    //     for(let i = 0; i < results.length; i ++) {
    //         img_dom_list[i].src = results[i];
    //     }
    // }).catch((e) => {
    //     console.error(e);
    // });
}

function TkindIcon(value) {
    let v = value === null ? 0 : value;
    let icon = "balloon-fill", title_tip = "常规队", txtcolor="text-success";
    if(v == 1) {
        icon = "balloon-heart-fill", title_tip = "女队", txtcolor="text-danger";
    } else if(v == 2) {
        icon = "star-fill", title_tip = "打星队", txtcolor="text-primary";
    }
    return `<i class="tkind_icon ${txtcolor} bi bi-${icon}" title="${title_tip}"></i>`;
}