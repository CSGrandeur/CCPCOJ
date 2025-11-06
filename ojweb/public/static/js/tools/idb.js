/**
 * IndexedDB 全局服务
 * 提供完整的 IndexedDB 操作接口，包含容错措施和重复引入处理
 */

// 防止重复初始化
if (window.idbInitialized) {
    // console.warn('idb.js already initialized, skipping...');
} else {
    window.idbInitialized = true;

    // IndexedDB 服务类
    class IndexedDBService {
        constructor() {
            this.dbName = 'CSGOJ-GlobalIDB';
            this.version = 1;
            this.db = null;
            this.isInitialized = false;
            this.initPromise = null;
        }

        // 初始化数据库
        async init() {
            if (this.isInitialized) {
                return this.db;
            }

            if (this.initPromise) {
                return this.initPromise;
            }

            this.initPromise = this._initDB();
            return this.initPromise;
        }

        async _initDB() {
            return new Promise((resolve, reject) => {
                // 检查浏览器支持
                if (!window.indexedDB) {
                    console.warn('IndexedDB is not supported in this browser');
                    reject(new Error('IndexedDB not supported'));
                    return;
                }

                const request = indexedDB.open(this.dbName, this.version);

                request.onerror = () => {
                    console.error('IndexedDB open failed:', request.error);
                    reject(request.error);
                };

                request.onsuccess = () => {
                    this.db = request.result;
                    this.isInitialized = true;
                    resolve(this.db);
                };

                request.onupgradeneeded = (event) => {
                    const db = event.target.result;
                    
                    // 创建默认的键值存储表
                    if (!db.objectStoreNames.contains('keyValueStore')) {
                        const store = db.createObjectStore('keyValueStore', { keyPath: 'key' });
                        store.createIndex('expireTime', 'expireTime', { unique: false });
                    }

                    // 创建通用表存储
                    if (!db.objectStoreNames.contains('tableStore')) {
                        db.createObjectStore('tableStore', { keyPath: 'id' });
                    }
                };
            });
        }

        // 确保数据库已初始化
        async ensureDB() {
            if (!this.isInitialized) {
                await this.init();
            }
            return this.db;
        }

        // 通用 idb 方法
        async idb(key, val = null, expire = null) {
            if (val === null) {
                return await this.GetIdb(key);
            } else {
                await this.SetIdb(key, val, expire);
                return val;
            }
        }

        // 获取数据
        async GetIdb(key) {
            try {
                const db = await this.ensureDB();
                const transaction = db.transaction(['keyValueStore'], 'readonly');
                const store = transaction.objectStore('keyValueStore');
                const request = store.get(key);

                return new Promise((resolve, reject) => {
                    request.onsuccess = () => {
                        const result = request.result;
                        if (!result) {
                            resolve(null);
                            return;
                        }

                        // 检查是否过期
                        if (result.expireTime && Date.now() > result.expireTime) {
                            // 数据已过期，删除并返回 null
                            this.DelIdb(key);
                            resolve(null);
                            return;
                        }

                        resolve(result.value);
                    };

                    request.onerror = () => {
                        console.error('GetIdb error:', request.error);
                        reject(request.error);
                    };
                });
            } catch (error) {
                console.error('GetIdb error:', error);
                return null;
            }
        }

        // 设置数据
        async SetIdb(key, val, expire = null) {
            try {
                const db = await this.ensureDB();
                const transaction = db.transaction(['keyValueStore'], 'readwrite');
                const store = transaction.objectStore('keyValueStore');
                
                const expireTime = expire ? Date.now() + expire : null;
                const data = {
                    key: key,
                    value: val,
                    expireTime: expireTime,
                    createTime: Date.now()
                };

                const request = store.put(data);

                return new Promise((resolve, reject) => {
                    request.onsuccess = () => {
                        resolve();
                    };

                    request.onerror = () => {
                        console.error('SetIdb error:', request.error);
                        reject(request.error);
                    };
                });
            } catch (error) {
                console.error('SetIdb error:', error);
                throw error;
            }
        }

        // 删除数据
        async DelIdb(key) {
            try {
                const db = await this.ensureDB();
                const transaction = db.transaction(['keyValueStore'], 'readwrite');
                const store = transaction.objectStore('keyValueStore');
                const request = store.delete(key);

                return new Promise((resolve, reject) => {
                    request.onsuccess = () => {
                        resolve();
                    };

                    request.onerror = () => {
                        console.error('DelIdb error:', request.error);
                        reject(request.error);
                    };
                });
            } catch (error) {
                console.error('DelIdb error:', error);
                throw error;
            }
        }

        // 获取表的所有数据
        async GetIdbTable(tableName) {
            try {
                const db = await this.ensureDB();
                const transaction = db.transaction(['tableStore'], 'readonly');
                const store = transaction.objectStore('tableStore');
                const request = store.getAll();

                return new Promise((resolve, reject) => {
                    request.onsuccess = () => {
                        const results = request.result;
                        const tableData = results
                            .filter(item => item.tableName === tableName)
                            .map(item => item.data);
                        resolve(tableData);
                    };

                    request.onerror = () => {
                        console.error('GetIdbTable error:', request.error);
                        reject(request.error);
                    };
                });
            } catch (error) {
                console.error('GetIdbTable error:', error);
                return [];
            }
        }

        // 设置表数据
        async SetIdbTable(tableName, data) {
            try {
                const db = await this.ensureDB();
                const transaction = db.transaction(['tableStore'], 'readwrite');
                const store = transaction.objectStore('tableStore');
                
                // 先删除该表的所有数据
                const deleteRequest = store.getAll();
                deleteRequest.onsuccess = () => {
                    const results = deleteRequest.result;
                    results.forEach(item => {
                        if (item.tableName === tableName) {
                            store.delete(item.id);
                        }
                    });
                };

                // 添加新数据
                data.forEach((item, index) => {
                    const record = {
                        id: `${tableName}_${index}`,
                        tableName: tableName,
                        data: item,
                        createTime: Date.now()
                    };
                    store.add(record);
                });

                return new Promise((resolve, reject) => {
                    transaction.oncomplete = () => {
                        resolve();
                    };

                    transaction.onerror = () => {
                        console.error('SetIdbTable error:', transaction.error);
                        reject(transaction.error);
                    };
                });
            } catch (error) {
                console.error('SetIdbTable error:', error);
                throw error;
            }
        }

        // 删除表
        async DelIdbTable(tableName) {
            try {
                const db = await this.ensureDB();
                const transaction = db.transaction(['tableStore'], 'readwrite');
                const store = transaction.objectStore('tableStore');
                const request = store.getAll();

                return new Promise((resolve, reject) => {
                    request.onsuccess = () => {
                        const results = request.result;
                        results.forEach(item => {
                            if (item.tableName === tableName) {
                                store.delete(item.id);
                            }
                        });
                        resolve();
                    };

                    request.onerror = () => {
                        console.error('DelIdbTable error:', request.error);
                        reject(request.error);
                    };
                });
            } catch (error) {
                console.error('DelIdbTable error:', error);
                throw error;
            }
        }

        // 根据键获取表数据
        async GetIdbTableByKey(tableName, key) {
            try {
                const db = await this.ensureDB();
                const transaction = db.transaction(['tableStore'], 'readonly');
                const store = transaction.objectStore('tableStore');
                const request = store.getAll();

                return new Promise((resolve, reject) => {
                    request.onsuccess = () => {
                        const results = request.result;
                        const item = results.find(item => 
                            item.tableName === tableName && 
                            JSON.stringify(item.data.key) === JSON.stringify(key)
                        );
                        resolve(item ? item.data.value : null);
                    };

                    request.onerror = () => {
                        console.error('GetIdbTableByKey error:', request.error);
                        reject(request.error);
                    };
                });
            } catch (error) {
                console.error('GetIdbTableByKey error:', error);
                return null;
            }
        }

        // 根据键设置表数据
        async SetIdbTableByKey(tableName, key, value) {
            try {
                const db = await this.ensureDB();
                const transaction = db.transaction(['tableStore'], 'readwrite');
                const store = transaction.objectStore('tableStore');
                
                const record = {
                    id: `${tableName}_${JSON.stringify(key)}`,
                    tableName: tableName,
                    data: { key: key, value: value },
                    createTime: Date.now()
                };

                const request = store.put(record);

                return new Promise((resolve, reject) => {
                    request.onsuccess = () => {
                        resolve();
                    };

                    request.onerror = () => {
                        console.error('SetIdbTableByKey error:', request.error);
                        reject(request.error);
                    };
                });
            } catch (error) {
                console.error('SetIdbTableByKey error:', error);
                throw error;
            }
        }

        // 根据键删除表数据
        async DelIdbTableByKey(tableName, key) {
            try {
                const db = await this.ensureDB();
                const transaction = db.transaction(['tableStore'], 'readwrite');
                const store = transaction.objectStore('tableStore');
                const request = store.delete(`${tableName}_${JSON.stringify(key)}`);

                return new Promise((resolve, reject) => {
                    request.onsuccess = () => {
                        resolve();
                    };

                    request.onerror = () => {
                        console.error('DelIdbTableByKey error:', request.error);
                        reject(request.error);
                    };
                });
            } catch (error) {
                console.error('DelIdbTableByKey error:', error);
                throw error;
            }
        }

        // 获取过期时间
        async GetExpire(key) {
            try {
                const db = await this.ensureDB();
                const transaction = db.transaction(['keyValueStore'], 'readonly');
                const store = transaction.objectStore('keyValueStore');
                const request = store.get(key);

                return new Promise((resolve, reject) => {
                    request.onsuccess = () => {
                        const result = request.result;
                        if (!result || !result.expireTime) {
                            resolve(null);
                            return;
                        }

                        const remaining = result.expireTime - Date.now();
                        resolve(remaining > 0 ? remaining : 0);
                    };

                    request.onerror = () => {
                        console.error('GetExpire error:', request.error);
                        reject(request.error);
                    };
                });
            } catch (error) {
                console.error('GetExpire error:', error);
                return null;
            }
        }

        // 获取缓存时间
        async GetCacheTime(key) {
            try {
                const db = await this.ensureDB();
                const transaction = db.transaction(['keyValueStore'], 'readonly');
                const store = transaction.objectStore('keyValueStore');
                const request = store.get(key);

                return new Promise((resolve, reject) => {
                    request.onsuccess = () => {
                        const result = request.result;
                        resolve(result ? result.createTime : null);
                    };

                    request.onerror = () => {
                        console.error('GetCacheTime error:', request.error);
                        reject(request.error);
                    };
                });
            } catch (error) {
                console.error('GetCacheTime error:', error);
                return null;
            }
        }
    }

    // 创建全局实例
    const indexedDBService = new IndexedDBService();

    // 初始化数据库
    indexedDBService.init().catch(error => {
        console.error('IndexedDB initialization failed:', error);
    });

    // 创建全局 idb 对象
    window.idb = {
        // 通用方法
        idb: async (key, val = null, expire = null) => {
            try {
                return await indexedDBService.idb(key, val, expire);
            } catch (error) {
                console.error('idb error:', error);
                return val;
            }
        },

        // 基础操作方法
        GetIdb: async (key) => {
            try {
                return await indexedDBService.GetIdb(key);
            } catch (error) {
                console.error('GetIdb error:', error);
                return null;
            }
        },

        SetIdb: async (key, val, expire = null) => {
            try {
                await indexedDBService.SetIdb(key, val, expire);
            } catch (error) {
                console.error('SetIdb error:', error);
            }
        },

        DelIdb: async (key) => {
            try {
                await indexedDBService.DelIdb(key);
            } catch (error) {
                console.error('DelIdb error:', error);
            }
        },

        // 表操作方法
        GetIdbTable: async (tableName) => {
            try {
                return await indexedDBService.GetIdbTable(tableName);
            } catch (error) {
                console.error('GetIdbTable error:', error);
                return [];
            }
        },

        SetIdbTable: async (tableName, data) => {
            try {
                await indexedDBService.SetIdbTable(tableName, data);
            } catch (error) {
                console.error('SetIdbTable error:', error);
            }
        },

        DelIdbTable: async (tableName) => {
            try {
                await indexedDBService.DelIdbTable(tableName);
            } catch (error) {
                console.error('DelIdbTable error:', error);
            }
        },

        GetIdbTableByKey: async (tableName, key) => {
            try {
                return await indexedDBService.GetIdbTableByKey(tableName, key);
            } catch (error) {
                console.error('GetIdbTableByKey error:', error);
                return null;
            }
        },

        SetIdbTableByKey: async (tableName, key, value) => {
            try {
                await indexedDBService.SetIdbTableByKey(tableName, key, value);
            } catch (error) {
                console.error('SetIdbTableByKey error:', error);
            }
        },

        DelIdbTableByKey: async (tableName, key) => {
            try {
                await indexedDBService.DelIdbTableByKey(tableName, key);
            } catch (error) {
                console.error('DelIdbTableByKey error:', error);
            }
        },

        // 时间相关方法
        GetExpire: async (key) => {
            try {
                return await indexedDBService.GetExpire(key);
            } catch (error) {
                console.error('GetExpire error:', error);
                return null;
            }
        },

        GetCacheTime: async (key) => {
            try {
                return await indexedDBService.GetCacheTime(key);
            } catch (error) {
                console.error('GetCacheTime error:', error);
                return null;
            }
        }
    };

}
