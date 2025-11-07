{include file="../../csgoj/view/public/js_zip" /}
{include file="../../csgoj/view/public/js_rank" /}
{include file="../../csgoj/view/public/base_csg_switch" /}
<style>
.outrank-push-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

.config-section {
    background: #fff;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.config-section h5 {
    margin-bottom: 20px;
    color: #333;
    border-bottom: 2px solid #007bff;
    padding-bottom: 10px;
}

.form-group {
    margin-bottom: 15px;
}

.form-label {
    font-weight: 500;
    margin-bottom: 5px;
    display: block;
}

.log-section {
    background: #fff;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    height: 500px;
    display: flex;
    flex-direction: column;
}

.log-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    border-bottom: 2px solid #007bff;
    padding-bottom: 10px;
}

.log-progress-container {
    margin-bottom: 15px;
    display: none; /* 默认隐藏 */
}

.log-progress-container.active {
    display: block;
}

.log-progress-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
    font-size: 13px;
    color: #333;
}

.log-progress-bar {
    width: 100%;
    height: 24px;
    background-color: #e9ecef;
    border-radius: 12px;
    overflow: hidden;
    position: relative;
}

.log-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #007bff 0%, #0056b3 100%);
    transition: width 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 12px;
    font-weight: 500;
}

.log-progress-text {
    position: absolute;
    width: 100%;
    text-align: center;
    line-height: 24px;
    font-size: 12px;
    font-weight: 500;
    color: #333;
    z-index: 1;
}

.log-content {
    flex: 1;
    overflow-y: auto;
    background: #1e1e1e;
    color: #d4d4d4;
    padding: 15px;
    font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
    font-size: 13px;
    line-height: 1.6;
    border-radius: 4px;
}

.log-entry {
    margin-bottom: 5px;
    word-wrap: break-word;
}

.log-entry.success {
    color: #4ec9b0;
}

.log-entry.error {
    color: #f48771;
}

.log-entry.info {
    color: #569cd6;
}

.log-entry.warning {
    color: #dcdcaa;
}

.log-timestamp {
    color: #808080;
    margin-right: 8px;
}

.control-buttons {
    display: flex;
    gap: 10px;
    margin-top: 20px;
}

.status-indicator {
    display: inline-block;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    margin-right: 8px;
}

.status-indicator.active {
    background-color: #28a745;
    box-shadow: 0 0 8px rgba(40, 167, 69, 0.5);
}

.status-indicator.inactive {
    background-color: #dc3545;
}

.status-indicator.pending {
    background-color: #ffc107;
}
</style>

<div class="outrank-push-container">
    <h3 class="mb-4">外榜推送 <span class="en-text">Outrank Push</span></h3>
    
    <!-- 配置区域 -->
    <div class="config-section">
        <h5>推送配置 <span class="en-text">Push Configuration</span></h5>
        
        <div class="form-group">
            <label class="form-label">外榜接口地址 <span class="en-text">Outrank API URL</span></label>
            <input type="text" class="form-control" id="api_url" placeholder="https://example.com/outrank/index/receive_data">
        </div>

        <div class="form-group">
            <label class="form-label">外榜UUID <span class="en-text">Outrank UUID</span></label>
            <input type="text" class="form-control" id="outrank_uuid" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
        </div>

        <div class="form-group">
            <label class="form-label">外榜Token <span class="en-text">Outrank Token</span></label>
            <input type="text" class="form-control" id="outrank_token" placeholder="your-token-here">
        </div>

        <div class="form-group">
            <label class="form-label">推送方式 <span class="en-text">Push Method</span></label>
            <div class="d-flex align-items-center gap-3">
                <label class="d-flex align-items-center gap-2">
                    <input type="radio" name="push_method" value="post" checked class="form-check-input">
                    <span>POST (推荐) <span class="en-text">POST (Recommended)</span></span>
                </label>
                <label class="d-flex align-items-center gap-2">
                    <input type="radio" name="push_method" value="jsonp" class="form-check-input">
                    <span>JSONP (备用) <span class="en-text">JSONP (Fallback)</span></span>
                </label>
            </div>
        </div>

        <div class="form-group" id="jsonp_chunk_size_group" style="display: none;">
            <label class="form-label">JSONP分包大小 <span class="en-text">JSONP Chunk Size</span></label>
            <div class="d-flex flex-wrap align-items-center gap-3">
                <label class="d-flex align-items-center gap-2">
                    <input type="radio" name="jsonp_chunk_size" value="1000" class="form-check-input">
                    <span>1KB</span>
                </label>
                <label class="d-flex align-items-center gap-2">
                    <input type="radio" name="jsonp_chunk_size" value="2000" class="form-check-input">
                    <span>2KB</span>
                </label>
                <label class="d-flex align-items-center gap-2">
                    <input type="radio" name="jsonp_chunk_size" value="3000" class="form-check-input">
                    <span>3KB</span>
                </label>
                <label class="d-flex align-items-center gap-2">
                    <input type="radio" name="jsonp_chunk_size" value="4000" class="form-check-input">
                    <span>4KB</span>
                </label>
                <label class="d-flex align-items-center gap-2">
                    <input type="radio" name="jsonp_chunk_size" value="5000" class="form-check-input">
                    <span>5KB</span>
                </label>
                <label class="d-flex align-items-center gap-2">
                    <input type="radio" name="jsonp_chunk_size" value="6000" class="form-check-input">
                    <span>6KB</span>
                </label>
                <label class="d-flex align-items-center gap-2">
                    <input type="radio" name="jsonp_chunk_size" value="auto" class="form-check-input" checked>
                    <span>尝试最大 <span class="en-text">Try Maximum</span> <small class="text-muted" id="jsonp_max_size_hint"></small></span>
                </label>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">数据压缩 <span class="en-text">Data Compression</span></label>
            <div class="d-flex align-items-center gap-2">
                <input type="checkbox" class="csg-switch-input" id="use_zip" data-csg-size="md" data-csg-theme="primary" checked>
                <span>启用ZIP压缩 <span class="en-text">Enable ZIP Compression</span></span>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">推送真榜 <span class="en-text">Push Real Rank</span></label>
            <div class="d-flex align-items-center gap-2">
                <input type="checkbox" class="csg-switch-input" id="push_real_rank" data-csg-size="md" data-csg-theme="warning">
                <span>推送真实榜单（关闭时按封榜节奏处理） <span class="en-text">Push real rank (when off, process according to frozen schedule)</span></span>
            </div>
        </div>

        <div class="control-buttons">
            <button type="button" class="btn btn-primary" id="btn_start">
                <span><i class="bi bi-play-circle"></i> 开始推送</span> <span class="en-text">Start Push</span>
            </button>
            <button type="button" class="btn btn-danger" id="btn_stop" disabled>
                <span><i class="bi bi-stop-circle"></i> 停止推送</span> <span class="en-text">Stop Push</span>
            </button>
            <button type="button" class="btn btn-secondary" id="btn_clear_log">
                <span><i class="bi bi-trash"></i> 清空日志</span> <span class="en-text">Clear Log</span>
            </button>
            <button type="button" class="btn btn-info" id="btn_import_config">
                <span><i class="bi bi-upload"></i> 导入配置</span> <span class="en-text">Import Config</span>
            </button>
            <button type="button" class="btn btn-success" id="btn_export_config">
                <span><i class="bi bi-download"></i> 导出配置</span> <span class="en-text">Export Config</span>
            </button>
            <input type="file" id="config_file_input" accept=".json" style="display: none;">
            <div class="ms-auto d-flex align-items-center">
                <span class="status-indicator inactive" id="status_indicator"></span>
                <span id="status_text">未启动 <span class="en-text">Not Started</span></span>
            </div>
        </div>
    </div>

    <!-- 日志区域 -->
    <div class="log-section">
        <div class="log-header">
            <h5 class="mb-0">推送日志 <span class="en-text">Push Log</span></h5>
            <div class="d-flex align-items-center gap-2">
                <small class="text-muted" id="log_count">0 条 <span class="en-text">0 entries</span></small>
                <button type="button" class="btn btn-sm btn-outline-primary" id="btn_download_log">
                    <span><i class="bi bi-download"></i> 下载日志</span> <span class="en-text">Download Log</span>
                </button>
            </div>
        </div>
        <div class="log-progress-container" id="log_progress_container">
            <div class="log-progress-info">
                <span id="log_progress_text">正在推送... <span class="en-text">Pushing...</span></span>
                <span id="log_progress_percent">0%</span>
            </div>
            <div class="log-progress-bar">
                <div class="log-progress-fill" id="log_progress_fill" style="width: 0%;"></div>
                <div class="log-progress-text" id="log_progress_detail">0 / 0</div>
            </div>
        </div>
        <div class="log-content" id="log_content"></div>
    </div>
</div>

<script>
// 全局变量
let pushInterval = null;
let isPushing = false;
let logEntries = [];
let logIndex = 0;
const MAX_LOG_ENTRIES_IDB = 2000; // IDB中保留的最大日志数
const MAX_LOG_ENTRIES_DOM = 500;  // DOM中显示的最大日志数
const IDB_BATCH_SIZE = 10;
const IDB_LOG_KEY = 'outrank_logs_all'; // IDB中存储日志的固定key
let idbBatch = [];
let rankSystem = null; // RankSystem 实例

// 初始化
$(document).ready(function() {
    // csg-switch 会自动初始化，但为了确保在动态内容中也能工作，手动触发一次
    if (typeof window.csgSwitch !== 'undefined') {
        window.csgSwitch.autoInit();
    }

    // 从localStorage加载配置
    loadConfig();

    // 绑定事件
    $('#btn_start').on('click', startPush);
    $('#btn_stop').on('click', stopPush);
    $('#btn_clear_log').on('click', clearLog);
    $('#btn_download_log').on('click', downloadAllLogs);
    $('#btn_import_config').on('click', function() {
        $('#config_file_input').click();
    });
    $('#config_file_input').on('change', importConfig);
    $('#btn_export_config').on('click', exportConfig);
    
    // 页面加载时从IDB加载最近100条日志
    loadLogsFromIDB();
    
    // 页面卸载前保存剩余的批次日志
    $(window).on('beforeunload', function() {
        if (idbBatch.length > 0) {
            // 同步保存（使用sendBeacon或同步方式）
            saveLogBatchToIDB().catch(function(error) {
                console.warn('Failed to save logs before unload:', error);
            });
        }
    });

    // 显示/隐藏JSONP分包大小选项
    function toggleJsonpChunkSizeGroup() {
        const isJsonp = $('input[name="push_method"]:checked').val() === 'jsonp';
        $('#jsonp_chunk_size_group').toggle(isJsonp);
    }
    
    // 初始化时检查（在loadConfig之后）
    setTimeout(function() {
        toggleJsonpChunkSizeGroup();
    }, 100);
    
    // 推送方式变化时显示/隐藏
    $('input[name="push_method"]').on('change', function() {
        toggleJsonpChunkSizeGroup();
        saveConfig();
    });

    // 输入框变化时保存到localStorage
    $('#api_url, #outrank_uuid, #outrank_token').on('change', saveConfig);
    $('#use_zip').on('change', saveConfig);
    $('input[name="jsonp_chunk_size"]').on('change', saveConfig);
    $('#push_real_rank').on('change', function() {
        if ($(this).is(':checked')) {
            // 打开时显示警告
            alerty.alert({
                message: '警告：推送真榜模式已开启，将推送所有真实数据，包括封榜期间的提交结果。请确保已获得授权。',
                message_en: 'Warning: Push real rank mode is enabled. All real data will be pushed, including submission results during frozen period. Please ensure you have authorization.',
                type: 'warning'
            });
        }
        saveConfig();
    });
});

// 加载配置
function loadConfig() {
    const apiUrl = csg.store('outrank_api_url') || '';
    const uuid = csg.store('outrank_uuid') || '';
    const token = csg.store('outrank_token') || '';
    const method = csg.store('outrank_push_method') || 'post';
    const useZip = csg.store('outrank_use_zip');
    const pushRealRank = csg.store('outrank_push_real_rank');
    const jsonpChunkSize = csg.store('outrank_jsonp_chunk_size') || 'auto';
    
    $('#api_url').val(apiUrl);
    $('#outrank_uuid').val(uuid);
    $('#outrank_token').val(token);
    $(`input[name="push_method"][value="${method}"]`).prop('checked', true);
    if (useZip !== null && useZip !== undefined) {
        $('#use_zip').prop('checked', useZip === 'true' || useZip === true);
    } else {
        $('#use_zip').prop('checked', true); // 默认启用
    }
    if (pushRealRank !== null && pushRealRank !== undefined) {
        $('#push_real_rank').prop('checked', pushRealRank === 'true' || pushRealRank === true);
    } else {
        $('#push_real_rank').prop('checked', false); // 默认关闭
    }
    
    // 加载JSONP分包大小配置
    $(`input[name="jsonp_chunk_size"][value="${jsonpChunkSize}"]`).prop('checked', true);
    updateJsonpMaxSizeHint();
}

// 保存配置
function saveConfig() {
    csg.store('outrank_api_url', $('#api_url').val());
    csg.store('outrank_uuid', $('#outrank_uuid').val());
    csg.store('outrank_token', $('#outrank_token').val());
    csg.store('outrank_push_method', $('input[name="push_method"]:checked').val());
    csg.store('outrank_use_zip', $('#use_zip').is(':checked'));
    csg.store('outrank_push_real_rank', $('#push_real_rank').is(':checked'));
    csg.store('outrank_jsonp_chunk_size', $('input[name="jsonp_chunk_size"]:checked').val());
}

// 更新"尝试最大"的提示信息
function updateJsonpMaxSizeHint() {
    const maxSize = getMaxChunkSize();
    if (maxSize) {
        $('#jsonp_max_size_hint').text(`(当前: ${Math.round(maxSize / 1024)}KB)`).show();
    } else {
        $('#jsonp_max_size_hint').text('(未检测)').show();
    }
}

// 获取最大分包大小（从存储中读取）
function getMaxChunkSize() {
    const stored = csg.store('outrank_jsonp_max_chunk_size');
    if (stored && !isNaN(stored)) {
        return parseInt(stored);
    }
    return null;
}

// 保存最大分包大小
function saveMaxChunkSize(size) {
    csg.store('outrank_jsonp_max_chunk_size', size.toString());
    updateJsonpMaxSizeHint();
}

// 检测浏览器支持的最大URL长度（使用二分法）
async function detectMaxChunkSize(baseUrl) {
    // 如果已有存储的值，先尝试使用（避免每次都迭代）
    const stored = getMaxChunkSize();
    if (stored) {
        // 快速验证存储的值是否仍然有效（只测试一次，不迭代）
        addLog('info', `使用已存储的最大分包大小: ${Math.round(stored / 1024)}KB (Using stored maximum chunk size: ${Math.round(stored / 1024)}KB)`);
        const isValid = await testChunkSize(baseUrl, stored);
        if (isValid) {
            addLog('info', `验证通过，使用 ${Math.round(stored / 1024)}KB (Validation passed, using ${Math.round(stored / 1024)}KB)`);
            return stored;
        }
        // 如果无效，清除存储，重新检测
        addLog('warning', `存储的值无效，重新检测 (Stored value invalid, re-detecting...)`);
        csg.store('outrank_jsonp_max_chunk_size', null);
    }
    
    // 二分法查找最大可用的分包大小
    let min = 1000;   // 最小1KB
    let max = 6000;   // 最大6KB
    let best = min;
    
    addLog('info', '开始检测最大分包大小 (Starting to detect maximum chunk size)...');
    
    // 最多迭代10次（避免无限循环）
    let iterationCount = 0;
    for (let i = 0; i < 10; i++) {
        const mid = Math.floor((min + max) / 2);
        iterationCount++;
        
        addLog('info', `[${iterationCount}] 测试分包大小: ${Math.round(mid / 1024)}KB (Testing chunk size: ${Math.round(mid / 1024)}KB)...`);
        
        const isValid = await testChunkSize(baseUrl, mid);
        
        if (isValid) {
            best = mid;
            min = mid + 1;
            // 如果已经达到最大值，提前结束
            if (mid >= max) {
                break;
            }
        } else {
            max = mid - 1;
        }
        
        // 如果范围已经很小（小于100字节），提前结束
        if (max - min < 100) {
            break;
        }
    }
    
    // 保存检测到的最大值
    saveMaxChunkSize(best);
    addLog('success', `最大分包大小检测完成: ${Math.round(best / 1024)}KB (经过 ${iterationCount} 次迭代) (Maximum chunk size detected: ${Math.round(best / 1024)}KB after ${iterationCount} iterations)`);
    
    return best;
}

// 测试指定大小的分包是否可用
function testChunkSize(baseUrl, chunkSize) {
    return new Promise((resolve) => {
        // 生成测试数据（模拟base64编码后的字符串，使用'A'字符）
        // 注意：实际base64字符可能包含+、/、=等，但这里只测试URL长度限制
        const testChunk = 'A'.repeat(chunkSize);
        const callbackName = 'test_chunk_size_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        
        // 构建测试URL
        const params = [
            'callback=' + encodeURIComponent(callbackName),
            'token=' + encodeURIComponent('test'),
            'outrank_uuid=' + encodeURIComponent('test'),
            'data_type=full',
            'chunk_index=0',
            'chunk_total=1',
            'chunk_data=' + encodeURIComponent(testChunk),
            'is_zip=0'
        ];
        const testUrl = baseUrl + '?' + params.join('&');
        
        // 检查URL长度（大多数浏览器限制在2048-8192字符）
        // base64数据在URL编码后可能增加约33%的长度
        // 注意：实际base64字符可能包含+、/、=等特殊字符，编码后可能增加更多
        const estimatedUrlLength = baseUrl.length + testChunk.length * 1.33 + 200;
        
        // 如果URL长度明显超过浏览器限制（8000字符），直接返回false
        // 但还是要实际测试，因为有些浏览器可能支持更长的URL
        if (estimatedUrlLength > 10000) {
            // 如果估算长度超过10000字符，直接返回false（避免无意义的测试）
            resolve(false);
            return;
        }
        
        let resolved = false;
        const timeout = setTimeout(() => {
            if (!resolved) {
                resolved = true;
                if (window[callbackName]) {
                    delete window[callbackName];
                }
                const script = document.querySelector('script[data-test-chunk-size]');
                if (script) {
                    script.remove();
                }
                resolve(false);
            }
        }, 5000); // 5秒超时
        
        // 注册回调
        window[callbackName] = function(result) {
            if (resolved) return;
            resolved = true;
            clearTimeout(timeout);
            delete window[callbackName];
            const script = document.querySelector('script[data-test-chunk-size]');
            if (script) {
                script.remove();
            }
            // 只要能执行回调，就认为URL长度可以接受
            resolve(true);
        };
        
        // 创建script标签测试
        const script = document.createElement('script');
        script.type = 'text/javascript';
        script.async = true;
        script.charset = 'utf-8';
        script.setAttribute('data-test-chunk-size', 'true');
        script.src = testUrl;
        
        script.onerror = function() {
            if (!resolved) {
                resolved = true;
                clearTimeout(timeout);
                if (window[callbackName]) {
                    delete window[callbackName];
                }
                script.remove();
                resolve(false);
            }
        };
        
        // 添加到页面
        document.head.appendChild(script);
    });
}

// 开始推送
function startPush() {
    const apiUrl = $('#api_url').val().trim();
    const uuid = $('#outrank_uuid').val().trim();
    const token = $('#outrank_token').val().trim();
    const method = $('input[name="push_method"]:checked').val();
    const useZip = $('#use_zip').is(':checked');

    if (!apiUrl || !uuid || !token) {
        addLog('error', '请填写完整的配置信息 (Please fill in complete configuration)');
        return;
    }

    isPushing = true;
    $('#btn_start').prop('disabled', true);
    $('#btn_stop').prop('disabled', false);
    $('#status_indicator').removeClass('inactive pending').addClass('active');
    $('#status_text').html('推送中 <span class="en-text">Pushing</span>');

    addLog('info', '开始推送数据 (Starting to push data)...');
    addLog('info', `推送方式: ${method.toUpperCase()} (Push method: ${method.toUpperCase()})`);
    if (useZip && method === 'post') {
        addLog('info', 'ZIP压缩已启用 (ZIP compression enabled)');
    }

    // 立即推送一次
    pushData(apiUrl, uuid, token, method, useZip);

    // 每30秒推送一次
    pushInterval = setInterval(function() {
        pushData(apiUrl, uuid, token, method, useZip);
    }, 30000);
}

// 停止推送
function stopPush() {
    isPushing = false;
    if (pushInterval) {
        clearInterval(pushInterval);
        pushInterval = null;
    }
    $('#btn_start').prop('disabled', false);
    $('#btn_stop').prop('disabled', true);
    $('#status_indicator').removeClass('active pending').addClass('inactive');
    $('#status_text').html('已停止 <span class="en-text">Stopped</span>');
    addLog('info', '推送已停止 (Push stopped)');
}

// 推送数据
function pushData(apiUrl, uuid, token, method, useZip) {
    // 获取比赛数据
    addLog('info', '正在获取比赛数据 (Fetching contest data)...');
    
    fetchContestData().then(function(contestData) {
        if (!contestData) {
            addLog('error', '获取比赛数据失败 (Failed to fetch contest data)');
            return;
        }

        addLog('success', '比赛数据获取成功 (Contest data fetched successfully)');

        if (method === 'post') {
            pushDataPost(apiUrl, uuid, token, contestData, useZip);
        } else {
            pushDataJsonp(apiUrl, uuid, token, contestData);
        }
    }).catch(function(error) {
        addLog('error', `推送失败: ${error.message || error} (Push failed: ${error.message || error})`);
        console.error(error);
    });
}

// 初始化 RankSystem（external 模式）
async function initRankSystem(forceRefresh = false) {
    const cid = <?php echo $contest['contest_id']; ?>;
    
    // 如果已存在且不需要强制刷新，直接返回
    if (rankSystem && !forceRefresh) {
        return rankSystem;
    }
    
    // 如果需要强制刷新，先清理旧的实例
    if (rankSystem && forceRefresh) {
        rankSystem = null;
    }
    
    // 创建 RankSystem 实例（external 模式）
    rankSystem = new RankSystem('outrank_external_mode', {
        api_url: `/csgoj/contest/contest_data_ajax?cid=${cid}`,
        flg_rank_cache: false, // 禁用缓存，确保获取最新数据
        cid_list: cid.toString()
    });
    
    // 加载数据
    await rankSystem.LoadData();
    
    return rankSystem;
}

// 判断提交是否在封榜期间内
function isFrozen(solution, contest) {
    if (!contest) return false;
    
    const inDate = solution.in_date;
    const submitTime = new Date(inDate).getTime();
    const endTime = new Date(contest.end_time).getTime();
    const frozenMinutes = contest.frozen_minute || 0;
    const frozenAfter = contest.frozen_after || 0;
    
    // 封榜开始时间：end_time 之前的 frozen_minute 分钟
    const frozenStartTime = endTime - frozenMinutes * 60 * 1000;
    // 封榜结束时间：end_time 之后的 frozen_after 分钟
    const frozenEndTime = endTime + frozenAfter * 60 * 1000;
    
    // 提交时间在封榜期间内
    return frozenStartTime <= submitTime && submitTime <= frozenEndTime;
}

// 处理数据：根据"推送真榜"开关处理 frozen 的 solution
function processContestData(rawData, pushRealRank) {
    // 深拷贝数据，避免修改原始数据
    const data = JSON.parse(JSON.stringify(rawData));
    
    if (!pushRealRank && data.solution && data.contest) {
        // 关闭"推送真榜"：将封榜期间的提交 result 设置为 -1
        data.solution.forEach(solution => {
            if (isFrozen(solution, data.contest)) {
                solution.result = -1;
            }
        });
    }
    
    return data;
}

// 获取比赛数据（使用 RankSystem 处理）
async function fetchContestData() {
    try {
        // 初始化 RankSystem（如果不存在）
        if (!rankSystem) {
            await initRankSystem(false);
        } else {
            // 如果已存在，刷新数据以获取最新内容
            await rankSystem.RefreshData();
        }
        
        if (!rankSystem || !rankSystem.OuterIsDataLoaded()) {
            throw new Error('RankSystem 数据未加载');
        }
        
        // 获取原始数据
        const rawData = {
            contest: rankSystem.OuterGetContest(),
            team: rankSystem.OuterGetTeams(),
            problem: rankSystem.OuterGetProblems(),
            solution: rankSystem.OuterGetSolutions()
        };
        
        // 检查数据完整性
        if (!rawData.contest || !rawData.team || !rawData.problem || !rawData.solution) {
            throw new Error('数据不完整');
        }
        
        // 根据"推送真榜"开关处理数据
        const pushRealRank = $('#push_real_rank').is(':checked');
        const processedData = processContestData(rawData, pushRealRank);
        
        return processedData;
    } catch (error) {
        console.error('获取比赛数据失败:', error);
        throw error;
    }
}

// 格式化文件大小
function formatFileSize(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return (bytes / Math.pow(k, i)).toFixed(2) + ' ' + sizes[i];
}

// 生成推送成功日志信息
function generatePushSuccessMessage(data, originalSize, compressedSize, result) {
    const teamCount = data.team ? data.team.length : 0;
    const solutionCount = data.solution ? data.solution.length : 0;
    const problemCount = data.problem ? data.problem.length : 0;
    
    let message = `数据推送成功 (Data pushed successfully)`;
    if (result && result.filename) {
        message += `: ${result.filename}`;
    }
    message += ` | 队伍: ${teamCount} (Teams: ${teamCount})`;
    message += ` | 提交: ${solutionCount} (Solutions: ${solutionCount})`;
    message += ` | 题目: ${problemCount} (Problems: ${problemCount})`;
    
    if (compressedSize !== null && compressedSize !== undefined) {
        // 压缩模式
        const compressionRatio = ((1 - compressedSize / originalSize) * 100).toFixed(1);
        message += ` | 原始: ${formatFileSize(originalSize)} (Original: ${formatFileSize(originalSize)})`;
        message += ` | 压缩: ${formatFileSize(compressedSize)} (Compressed: ${formatFileSize(compressedSize)})`;
        message += ` | 压缩率: ${compressionRatio}% (Ratio: ${compressionRatio}%)`;
    } else {
        // 未压缩模式
        message += ` | 大小: ${formatFileSize(originalSize)} (Size: ${formatFileSize(originalSize)})`;
    }
    
    return message;
}

// POST方式推送
function pushDataPost(apiUrl, uuid, token, data, useZip) {
    // 不再将敏感信息放在 URL 中，而是放在 POST body 中
    
    // 计算原始数据大小
    const jsonStr = JSON.stringify(data, null, 2);
    const originalSize = new Blob([jsonStr]).size;
    
    if (useZip && typeof zip !== 'undefined') {
        // 使用 zip.js 压缩数据
        addLog('info', '正在压缩数据 (Compressing data)...');
        
        const zipWriter = new zip.ZipWriter(new zip.BlobWriter('application/zip'));
        
        zipWriter.add('rank.json', new zip.TextReader(jsonStr)).then(function() {
            return zipWriter.close();
        }).then(function(zipBlob) {
            // 计算压缩后大小
            const compressedSize = zipBlob.size;
            
            // 使用 FormData 发送 zip 文件，将敏感信息放在 FormData 中
            const formData = new FormData();
            formData.append('data', zipBlob, 'rank.zip');
            formData.append('token', token);
            formData.append('outrank_uuid', uuid);
            
            addLog('info', '数据压缩完成，正在推送 (Data compressed, pushing...)');
            
            $.ajax({
                url: apiUrl, // 不再在 URL 中包含敏感信息
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                success: function(result) {
                    if (result.status === 'success' || result.code === 1) {
                        const successMsg = generatePushSuccessMessage(data, originalSize, compressedSize, result);
                        addLog('success', successMsg);
                    } else {
                        const errorMsg = result.msg || result.message || 'Unknown error';
                        addLog('error', '推送失败: ' + errorMsg + ' (Push failed: ' + errorMsg + ')');
                    }
                },
                error: function(xhr, status, error) {
                    let errorMsg = error || status;
                    if (xhr.responseJSON) {
                        errorMsg = xhr.responseJSON.msg || xhr.responseJSON.message || errorMsg;
                    }
                    addLog('error', 'POST推送失败: ' + errorMsg + ' (POST push failed: ' + errorMsg + ')');
                }
            });
        }).catch(function(error) {
            addLog('error', 'ZIP压缩失败: ' + (error.message || error) + ' (ZIP compression failed: ' + (error.message || error) + ')');
            // 压缩失败，尝试发送未压缩数据
            pushDataPostUncompressed(apiUrl, uuid, token, data, originalSize);
        });
    } else {
        // 不使用压缩，直接发送 JSON
        pushDataPostUncompressed(apiUrl, uuid, token, data, originalSize);
    }
}

// POST方式推送（未压缩）
function pushDataPostUncompressed(apiUrl, uuid, token, data, originalSize) {
    // 不再将敏感信息放在 URL 中，而是放在 POST body 中
    
    // 如果没有传入原始大小，计算一下
    if (originalSize === null || originalSize === undefined) {
        const jsonStr = JSON.stringify(data, null, 2);
        originalSize = new Blob([jsonStr]).size;
    }
    
    // 将敏感信息和数据一起放在 JSON body 中
    const requestBody = {
        token: token,
        outrank_uuid: uuid,
        data: data
    };
    
    const body = JSON.stringify(requestBody);
    
    $.ajax({
        url: apiUrl, // 不再在 URL 中包含敏感信息
        method: 'POST',
        data: body,
        contentType: 'application/json',
        processData: true,
        dataType: 'json',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        success: function(result) {
            if (result.status === 'success' || result.code === 1) {
                const successMsg = generatePushSuccessMessage(data, originalSize, null, result);
                addLog('success', successMsg);
            } else {
                const errorMsg = result.msg || result.message || 'Unknown error';
                addLog('error', '推送失败: ' + errorMsg + ' (Push failed: ' + errorMsg + ')');
            }
        },
        error: function(xhr, status, error) {
            let errorMsg = error || status;
            if (xhr.responseJSON) {
                errorMsg = xhr.responseJSON.msg || xhr.responseJSON.message || errorMsg;
            }
            addLog('error', 'POST推送失败: ' + errorMsg + ' (POST push failed: ' + errorMsg + ')');
        }
    });
}

// 将 Blob 转换为 base64 字符串（Promise）
function blobToBase64(blob) {
    return new Promise(function(resolve, reject) {
        const reader = new FileReader();
        reader.onloadend = function() {
            // 移除 data URL 前缀（如 "data:application/zip;base64,"）
            const base64 = reader.result.split(',')[1];
            resolve(base64);
        };
        reader.onerror = reject;
        reader.readAsDataURL(blob);
    });
}

// JSONP方式推送（分包，先压缩为ZIP再分包）
function pushDataJsonp(apiUrl, uuid, token, data) {
    const jsonStr = JSON.stringify(data, null, 2);
    const originalSize = new Blob([jsonStr]).size;
    
    // 统计数据量
    const teamCount = data.team ? data.team.length : 0;
    const solutionCount = data.solution ? data.solution.length : 0;
    const problemCount = data.problem ? data.problem.length : 0;
    
    addLog('info', '正在压缩数据 (Compressing data)...');
    
    // 先压缩为 ZIP
    if (typeof zip === 'undefined') {
        addLog('error', 'ZIP库未加载，无法使用JSONP压缩推送 (ZIP library not loaded, cannot use JSONP compressed push)');
        return;
    }
    
    const zipWriter = new zip.ZipWriter(new zip.BlobWriter('application/zip'));
    
    let zipBlobSize = 0; // 保存 ZIP Blob 的大小
    
    zipWriter.add('rank.json', new zip.TextReader(jsonStr)).then(function() {
        return zipWriter.close();
    }).then(function(zipBlob) {
        // 计算压缩后大小
        zipBlobSize = zipBlob.size;
        
        addLog('info', `数据压缩完成 (Data compressed) | 原始: ${formatFileSize(originalSize)} (Original: ${formatFileSize(originalSize)}) | 压缩: ${formatFileSize(zipBlobSize)} (Compressed: ${formatFileSize(zipBlobSize)}) | 压缩率: ${((1 - zipBlobSize / originalSize) * 100).toFixed(1)}% (Ratio: ${((1 - zipBlobSize / originalSize) * 100).toFixed(1)}%)`);
        
        // 将 ZIP Blob 转换为 base64
        return blobToBase64(zipBlob);
    }).then(async function(base64Str) {
        // 构建 baseUrl（在函数外部定义，避免闭包问题）
        const baseUrl = apiUrl.replace('_jsonp', '').replace('receive_data', 'receive_data_jsonp');
        
        // 获取分包大小配置
        let chunkSize = 2500; // 默认值
        const chunkSizeConfig = $('input[name="jsonp_chunk_size"]:checked').val();
        
        if (chunkSizeConfig === 'auto') {
            // "尝试最大"模式：检测最大可用分包大小
            // 注意：这里传入baseUrl用于测试，base64Str仅用于参考（实际测试时使用模拟数据）
            chunkSize = await detectMaxChunkSize(baseUrl);
        } else {
            // 使用用户选择的大小
            chunkSize = parseInt(chunkSizeConfig) || 2500;
        }
        
        // 将 base64 字符串分包
        const chunks = [];
        for (let i = 0; i < base64Str.length; i += chunkSize) {
            chunks.push(base64Str.substr(i, chunkSize));
        }
        
        addLog('info', `数据分为 ${chunks.length} 包发送 (Data split into ${chunks.length} chunks) | 分包大小: ${Math.round(chunkSize / 1024)}KB (Chunk size: ${Math.round(chunkSize / 1024)}KB) | 队伍: ${teamCount} (Teams: ${teamCount}) | 提交: ${solutionCount} (Solutions: ${solutionCount}) | 题目: ${problemCount} (Problems: ${problemCount})`);
        
        // 显示进度条
        showProgressBar(chunks.length);
        
        // 记录每个包的状态（用于最后总结）
        const chunkStatuses = [];
        for (let i = 0; i < chunks.length; i++) {
            chunkStatuses[i] = { index: i + 1, status: 'pending', error: null };
        }
        
        // 逐包发送（使用递归方式处理异步）
        let currentChunk = 0;
        
        // 将chunkSizeConfig保存到闭包中，供sendNextChunk使用
        const savedChunkSizeConfig = chunkSizeConfig;
        
        function sendNextChunk() {
            if (currentChunk >= chunks.length) {
                // 所有包发送完成，生成总结日志
                const successCount = chunkStatuses.filter(s => s.status === 'success').length;
                const failedChunks = chunkStatuses.filter(s => s.status !== 'success');
                
                // 更新进度条到100%
                updateProgressBar(chunks.length, chunks.length);
                
                // 生成总结日志
                if (failedChunks.length === 0) {
                    // 全部成功
                    const successMsg = generatePushSuccessMessage(data, originalSize, zipBlobSize, null);
                    addLog('success', 'JSONP推送完成 (JSONP push completed) | ' + successMsg.replace('数据推送成功 (Data pushed successfully)', ''));
                } else {
                    // 有失败的包
                    const failedList = failedChunks.map(s => {
                        if (s.status === 'timeout') {
                            return `第${s.index}包超时 (Chunk ${s.index} timeout)`;
                        } else if (s.status === 'load_error') {
                            return `第${s.index}包加载失败 (Chunk ${s.index} load failed)`;
                        } else {
                            return `第${s.index}包失败: ${s.error || 'Unknown error'} (Chunk ${s.index} failed: ${s.error || 'Unknown error'})`;
                        }
                    }).join(', ');
                    
                    const summaryMsg = `JSONP推送完成 (JSONP push completed) | 成功: ${successCount}/${chunks.length} (Success: ${successCount}/${chunks.length}) | 失败: ${failedChunks.length}/${chunks.length} (Failed: ${failedChunks.length}/${chunks.length})`;
                    addLog(failedChunks.length === chunks.length ? 'error' : 'warning', summaryMsg);
                    addLog('warning', `失败详情 (Failed details): ${failedList}`);
                    
                    // 如果至少有一个包成功，也显示数据统计
                    if (successCount > 0) {
                        const successMsg = generatePushSuccessMessage(data, originalSize, zipBlobSize, null);
                        addLog('info', '数据统计 (Data statistics): ' + successMsg.replace('数据推送成功 (Data pushed successfully)', ''));
                    }
                }
                
                // 1秒后隐藏进度条
                setTimeout(function() {
                    hideProgressBar();
                }, 1000);
                return;
            }
            
            const chunkData = chunks[currentChunk];
            const chunkIndex = currentChunk; // 保存当前索引，避免闭包问题
            // 使用更唯一的回调函数名称（时间戳 + 随机数 + 索引）
            const callback = 'outrank_cb_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9) + '_' + currentChunk;
            
            // 检查 URL 长度（大多数浏览器限制在 2048-8192 字符）
            // base64 数据在 URL 编码后可能增加约 33% 的长度
            const estimatedUrlLength = baseUrl.length + chunkData.length * 1.33 + 200; // 200 为其他参数的长度
            if (estimatedUrlLength > 8000) {
                // URL 太长，如果使用的是"尝试最大"模式，需要重新检测
                if (chunkIndex === 0 && savedChunkSizeConfig === 'auto') {
                    addLog('warning', `URL 长度过长 (${Math.round(estimatedUrlLength)} 字符)，将重新检测最大分包大小 (URL too long, will re-detect maximum chunk size)`);
                    // 清除存储的最大值，下次推送时会重新检测
                    csg.store('outrank_jsonp_max_chunk_size', null);
                    updateJsonpMaxSizeHint();
                } else if (chunkIndex === 0) {
                    addLog('warning', `URL 长度可能过长 (${Math.round(estimatedUrlLength)} 字符)，建议减小分块大小 (URL may be too long, consider reducing chunk size)`);
                }
            }
            
            let resolved = false;
            const timeout = setTimeout(function() {
                if (!resolved) {
                    resolved = true;
                    if (window[callback]) {
                        delete window[callback];
                    }
                    // 移除 script 标签
                    $('script[data-outrank-chunk="' + chunkIndex + '"]').remove();
                    updateProgressBar(chunkIndex + 1, chunks.length);
                    // 记录超时状态，不输出日志
                    chunkStatuses[chunkIndex].status = 'timeout';
                    chunkStatuses[chunkIndex].error = 'Timeout';
                    currentChunk++;
                    sendNextChunk();
                }
            }, 60000); // 增加到 60 秒超时（ZIP 解压可能需要时间）

            // 注册回调函数
            window[callback] = function(result) {
                if (resolved) return;
                resolved = true;
                clearTimeout(timeout);
                // 移除 script 标签
                $('script[data-outrank-chunk="' + chunkIndex + '"]').remove();
                if (window[callback]) {
                    delete window[callback];
                }
                // 更新进度条
                updateProgressBar(chunkIndex + 1, chunks.length);
                
                // 记录状态，不输出日志
                if (result && result.status === 'success') {
                    chunkStatuses[chunkIndex].status = 'success';
                } else {
                    const errorMsg = (result && result.message) ? result.message : 'Unknown error';
                    chunkStatuses[chunkIndex].status = 'error';
                    chunkStatuses[chunkIndex].error = errorMsg;
                }
                currentChunk++;
                sendNextChunk();
            };

            // 手动构建 URL，避免 jQuery.param 对 base64 数据的额外编码
            const urlParams = [
                'callback=' + encodeURIComponent(callback),
                'token=' + encodeURIComponent(token),
                'outrank_uuid=' + encodeURIComponent(uuid),
                'data_type=full',
                'chunk_index=' + encodeURIComponent(currentChunk.toString()),
                'chunk_total=' + encodeURIComponent(chunks.length.toString()),
                'chunk_data=' + encodeURIComponent(chunkData),
                'is_zip=1'
            ];
            const url = baseUrl + '?' + urlParams.join('&');
            
            // 使用原生方式加载 script（更可靠）
            const script = document.createElement('script');
            script.type = 'text/javascript';
            script.async = true;
            script.charset = 'utf-8';
            script.setAttribute('data-outrank-chunk', chunkIndex);
            script.src = url;
            
            // 错误处理
            script.onerror = function() {
                if (!resolved) {
                    resolved = true;
                    clearTimeout(timeout);
                    if (window[callback]) {
                        delete window[callback];
                    }
                    updateProgressBar(chunkIndex + 1, chunks.length);
                    // 记录加载失败状态，不输出日志
                    chunkStatuses[chunkIndex].status = 'load_error';
                    chunkStatuses[chunkIndex].error = 'Load failed';
                    
                    // 如果是第一个包失败且使用的是"尝试最大"模式，可能是URL太长，清除存储的值以便下次重新检测
                    if (chunkIndex === 0 && savedChunkSizeConfig === 'auto') {
                        csg.store('outrank_jsonp_max_chunk_size', null);
                        updateJsonpMaxSizeHint();
                    }
                    
                    currentChunk++;
                    sendNextChunk();
                }
            };
            
            // 添加到 head
            document.head.appendChild(script);
        }
        
        sendNextChunk();
    }).catch(function(error) {
        addLog('error', 'ZIP压缩失败: ' + (error.message || error) + ' (ZIP compression failed: ' + (error.message || error) + ')');
    });
}

// 添加日志
function addLog(type, message) {
    const timestamp = csg.TimeNow('HH:mm:ss');
    const entry = {
        index: logIndex++,
        timestamp: timestamp,
        type: type,
        message: message
    };

    logEntries.push(entry);
    
    // 限制内存中的日志数量（保持DOM性能）
    if (logEntries.length > MAX_LOG_ENTRIES_DOM) {
        logEntries.shift();
    }

    // 添加到IDB批次
    idbBatch.push(entry);
    if (idbBatch.length >= IDB_BATCH_SIZE) {
        saveLogBatchToIDB();
    }

    // 更新显示
    updateLogDisplay();
}

// 保存日志批次到IndexedDB
async function saveLogBatchToIDB() {
    if (idbBatch.length === 0) return;
    
    try {
        const batch = idbBatch.slice();
        idbBatch = [];
        
        // 使用idb接口保存
        try {
            // 读取现有日志
            let allLogs = [];
            if (typeof window.idb !== 'undefined' && window.idb.GetIdb) {
                const existing = await window.idb.GetIdb(IDB_LOG_KEY);
                if (existing && Array.isArray(existing)) {
                    allLogs = existing;
                }
            } else if (typeof idb !== 'undefined' && idb.GetIdb) {
                const existing = await idb.GetIdb(IDB_LOG_KEY);
                if (existing && Array.isArray(existing)) {
                    allLogs = existing;
                }
            }
            
            // 追加新日志
            allLogs = allLogs.concat(batch);
            
            // 限制为2000条（保留最新的）
            if (allLogs.length > MAX_LOG_ENTRIES_IDB) {
                allLogs = allLogs.slice(-MAX_LOG_ENTRIES_IDB);
            }
            
            // 保存回去
            if (typeof window.idb !== 'undefined' && window.idb.SetIdb) {
                await window.idb.SetIdb(IDB_LOG_KEY, allLogs);
            } else if (typeof idb !== 'undefined' && idb.SetIdb) {
                await idb.SetIdb(IDB_LOG_KEY, allLogs);
            }
        } catch (error) {
            // IDB保存失败不影响主流程
            console.warn('Failed to save log to IDB:', error);
        }
    } catch (error) {
        console.error('Failed to save log to IDB:', error);
    }
}

// 从IndexedDB加载最近100条日志
async function loadLogsFromIDB() {
    try {
        let allLogs = [];
        
        // 读取IDB中的日志
        if (typeof window.idb !== 'undefined' && window.idb.GetIdb) {
            const existing = await window.idb.GetIdb(IDB_LOG_KEY);
            if (existing && Array.isArray(existing)) {
                allLogs = existing;
            }
        } else if (typeof idb !== 'undefined' && idb.GetIdb) {
            const existing = await idb.GetIdb(IDB_LOG_KEY);
            if (existing && Array.isArray(existing)) {
                allLogs = existing;
            }
        }
        
        if (allLogs.length > 0) {
            // 取最近100条
            const recentLogs = allLogs.slice(-100);
            
            // 更新logIndex（确保新日志的index不会冲突）
            if (recentLogs.length > 0) {
                const maxIndex = Math.max(...recentLogs.map(log => log.index || 0));
                logIndex = maxIndex + 1;
            }
            
            // 加载到内存
            logEntries = recentLogs;
            
            // 更新显示
            updateLogDisplay();
            
        }
    } catch (error) {
        console.warn('Failed to load logs from IDB:', error);
    }
}

// 下载全量日志（打包成ZIP）
async function downloadAllLogs() {
    try {
        // 显示加载提示
        const btn = $('#btn_download_log');
        const originalText = btn.html();
        btn.prop('disabled', true);
        btn.html('<i class="bi bi-hourglass-split"></i> 准备中... <span class="en-text">Preparing...</span>');
        
        // 读取IDB中的所有日志
        let allLogs = [];
        
        if (typeof window.idb !== 'undefined' && window.idb.GetIdb) {
            const existing = await window.idb.GetIdb(IDB_LOG_KEY);
            if (existing && Array.isArray(existing)) {
                allLogs = existing;
            }
        } else if (typeof idb !== 'undefined' && idb.GetIdb) {
            const existing = await idb.GetIdb(IDB_LOG_KEY);
            if (existing && Array.isArray(existing)) {
                allLogs = existing;
            }
        }
        
        if (allLogs.length === 0) {
            alerty.alert({
                message: '没有可下载的日志',
                message_en: 'No logs available for download',
                type: 'info'
            });
            btn.prop('disabled', false);
            btn.html(originalText);
            return;
        }
        
        // 生成日志文本内容
        let logText = `外榜推送日志 (Outrank Push Log)\n`;
        logText += `生成时间 (Generated): ${new Date().toLocaleString('zh-CN')} / ${new Date().toLocaleString('en-US')}\n`;
        logText += `总条数 (Total): ${allLogs.length}\n`;
        logText += '='.repeat(80) + '\n\n';
        
        allLogs.forEach((entry, index) => {
            const typeText = {
                'info': '信息',
                'success': '成功',
                'warning': '警告',
                'error': '错误'
            };
            const typeTextEn = {
                'info': 'Info',
                'success': 'Success',
                'warning': 'Warning',
                'error': 'Error'
            };
            
            logText += `[${index + 1}] [${entry.timestamp || 'N/A'}] [${typeText[entry.type] || entry.type || 'info'} / ${typeTextEn[entry.type] || entry.type || 'info'}]\n`;
            logText += `${entry.message || ''}\n`;
            logText += '-'.repeat(80) + '\n';
        });
        
        // 同时生成JSON格式（便于程序处理）
        const logJson = JSON.stringify({
            metadata: {
                generated: new Date().toISOString(),
                total: allLogs.length,
                version: '1.0'
            },
            logs: allLogs
        }, null, 2);
        
        // 检查zip.js是否可用
        if (typeof zip === 'undefined') {
            // 如果没有zip.js，直接下载文本文件
            const blob = new Blob([logText], { type: 'text/plain;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `outrank_logs_${csg.TimeNow('YYYYMMDD_HHmmss')}.txt`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            
            alerty.alert({
                message: `日志已下载 (${allLogs.length} 条)`,
                message_en: `Logs downloaded (${allLogs.length} entries)`,
                type: 'success'
            });
        } else {
            // 使用zip.js打包
            const zipWriter = new zip.ZipWriter(new zip.BlobWriter('application/zip'));
            
            // 添加文本文件
            await zipWriter.add('outrank_logs.txt', new zip.TextReader(logText));
            
            // 添加JSON文件
            await zipWriter.add('outrank_logs.json', new zip.TextReader(logJson));
            
            // 生成ZIP文件
            const zipBlob = await zipWriter.close();
            
            // 下载ZIP文件
            const url = URL.createObjectURL(zipBlob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `outrank_logs_${csg.TimeNow('YYYYMMDD_HHmmss')}.zip`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            
            alerty.alert({
                message: `日志已打包下载 (${allLogs.length} 条)`,
                message_en: `Logs packaged and downloaded (${allLogs.length} entries)`,
                type: 'success'
            });
        }
        
        btn.prop('disabled', false);
        btn.html(originalText);
        
    } catch (error) {
        console.error('Failed to download logs:', error);
        alerty.alert({
            message: '下载日志失败: ' + error.message,
            message_en: 'Failed to download logs: ' + error.message,
            type: 'error'
        });
        
        const btn = $('#btn_download_log');
        btn.prop('disabled', false);
        btn.html('<span><i class="bi bi-download"></i> 下载日志</span> <span class="en-text">Download Log</span>');
    }
}

// 更新日志显示
function updateLogDisplay() {
    const logContent = $('#log_content');
    let html = '';
    
    // 只显示最后500条（保持DOM性能）
    const displayEntries = logEntries.slice(-MAX_LOG_ENTRIES_DOM);
    
    displayEntries.forEach(entry => {
        const typeClass = entry.type || 'info';
        html += `<div class="log-entry ${typeClass}">
            <span class="log-timestamp">[${entry.timestamp}]</span>
            ${escapeHtml(entry.message)}
        </div>`;
    });
    
    logContent.html(html);
    logContent.scrollTop(logContent[0].scrollHeight);
    
    // 显示日志数量（包括IDB中的总数）
    updateLogCount();
}

// 更新日志计数显示
async function updateLogCount() {
    try {
        // 获取IDB中的总数
        let totalInIDB = logEntries.length;
        
        if (typeof window.idb !== 'undefined' && window.idb.GetIdb) {
            const existing = await window.idb.GetIdb(IDB_LOG_KEY);
            if (existing && Array.isArray(existing)) {
                totalInIDB = existing.length;
            }
        } else if (typeof idb !== 'undefined' && idb.GetIdb) {
            const existing = await idb.GetIdb(IDB_LOG_KEY);
            if (existing && Array.isArray(existing)) {
                totalInIDB = existing.length;
            }
        }
        
        // 显示：内存中的数量 / IDB中的总数
        if (totalInIDB > logEntries.length) {
            $('#log_count').html(`${logEntries.length} / ${totalInIDB} 条 <span class="en-text">${logEntries.length} / ${totalInIDB} entries</span>`);
        } else {
            $('#log_count').html(`${logEntries.length} 条 <span class="en-text">${logEntries.length} entries</span>`);
        }
    } catch (error) {
        // 如果获取IDB总数失败，只显示内存中的数量
        $('#log_count').html(`${logEntries.length} 条 <span class="en-text">${logEntries.length} entries</span>`);
    }
}

// 显示进度条
function showProgressBar(total) {
    $('#log_progress_container').addClass('active');
    $('#log_progress_text').html('正在推送... <span class="en-text">Pushing...</span>');
    updateProgressBar(0, total);
}

// 隐藏进度条
function hideProgressBar() {
    $('#log_progress_container').removeClass('active');
}

// 更新进度条
function updateProgressBar(current, total) {
    if (total === 0) {
        $('#log_progress_fill').css('width', '0%');
        $('#log_progress_percent').text('0%');
        $('#log_progress_detail').text('0 / 0');
        return;
    }
    
    const percent = Math.round((current / total) * 100);
    $('#log_progress_fill').css('width', percent + '%');
    $('#log_progress_percent').text(percent + '%');
    $('#log_progress_detail').text(`${current} / ${total}`);
    
    if (current >= total) {
        $('#log_progress_text').html('推送完成 <span class="en-text">Push completed</span>');
    } else {
        $('#log_progress_text').html(`正在推送第 ${current} / ${total} 包 <span class="en-text">Pushing chunk ${current} / ${total}</span>`);
    }
}

// 清空日志
async function clearLog() {
    if (confirm('确定要清空日志吗？(Are you sure to clear the log?)')) {
        logEntries = [];
        idbBatch = [];
        
        // 清空IDB中的日志
        try {
            if (typeof window.idb !== 'undefined' && window.idb.SetIdb) {
                await window.idb.SetIdb(IDB_LOG_KEY, []);
            } else if (typeof idb !== 'undefined' && idb.SetIdb) {
                await idb.SetIdb(IDB_LOG_KEY, []);
            }
        } catch (error) {
            console.warn('Failed to clear logs from IDB:', error);
        }
        
        logIndex = 0;
        updateLogDisplay();
        hideProgressBar();
        addLog('info', '日志已清空 (Log cleared)');
    }
}

// 导出配置
function exportConfig() {
    const apiUrl = $('#api_url').val().trim();
    const uuid = $('#outrank_uuid').val().trim();
    const token = $('#outrank_token').val().trim();
    const method = $('input[name="push_method"]:checked').val();
    const useZip = $('#use_zip').is(':checked');
    const pushRealRank = $('#push_real_rank').is(':checked');
    const jsonpChunkSize = $('input[name="jsonp_chunk_size"]:checked').val();
    
    // 验证必要字段
    if (!uuid || !token) {
        alerty.error({
            message: '请先填写UUID和Token',
            message_en: 'Please fill in UUID and Token first'
        });
        return;
    }
    
    // 生成配置JSON（与外榜模块格式一致）
    const config = {
        api_url: apiUrl || '',
        outrank_uuid: uuid,
        outrank_token: token,
        push_method: method || 'post',
        use_zip: useZip,
        use_gzip: useZip, // 兼容旧版本
        push_real_rank: pushRealRank,
        jsonp_chunk_size: jsonpChunkSize || 'auto',
        version: '1.0',
        export_time: new Date().toISOString()
    };
    
    // 下载JSON文件
    const jsonStr = JSON.stringify(config, null, 2);
    const blob = new Blob([jsonStr], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `outrank_config_${uuid.substring(0, 8)}_${Date.now()}.json`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    
    alerty.success({
        message: '配置已导出',
        message_en: 'Configuration exported'
    });
    
    addLog('success', '配置已导出 (Config exported)');
}

// 导入配置
function importConfig(event) {
    const file = event.target.files[0];
    if (!file) {
        return;
    }
    
    const reader = new FileReader();
    reader.onload = function(e) {
        try {
            // 处理可能的 BOM 和空白字符
            let jsonText = e.target.result;
            
            // 移除 BOM (UTF-8 BOM: EF BB BF)
            if (jsonText.charCodeAt(0) === 0xFEFF) {
                jsonText = jsonText.slice(1);
            }
            
            // 移除首尾空白字符
            jsonText = jsonText.trim();
            
            // 检查是否为空
            if (!jsonText) {
                addLog('info', '配置文件为空，请手动填写 (Config file is empty, please fill manually)');
                alerty.info({
                    message: '配置文件为空，请手动填写',
                    message_en: 'Config file is empty, please fill manually'
                });
                event.target.value = '';
                return;
            }
            
            // 解析 JSON
            let config;
            try {
                config = JSON.parse(jsonText);
            } catch (parseError) {
                // JSON 解析错误，提供更详细的错误信息
                console.error('JSON parse error:', parseError);
                console.error('JSON text:', jsonText);
                addLog('error', `JSON解析失败: ${parseError.message} (JSON parse failed: ${parseError.message})`);
                alerty.error({
                    message: `配置导入失败：JSON格式错误\n错误信息: ${parseError.message}\n请检查文件格式是否正确`,
                    message_en: `Config import failed: Invalid JSON format\nError: ${parseError.message}\nPlease check if the file format is correct`
                });
                event.target.value = '';
                return;
            }
            
            // 确保 config 是对象
            if (typeof config !== 'object' || config === null || Array.isArray(config)) {
                addLog('error', '配置文件格式错误：根元素必须是对象 (Config format error: root element must be an object)');
                alerty.error({
                    message: '配置文件格式错误：根元素必须是对象',
                    message_en: 'Config format error: root element must be an object'
                });
                event.target.value = '';
                return;
            }
            
            // 不验证必要字段，即使配置为空也继续导入，缺失的字段让用户手填
            // 只加载存在的字段，缺失的字段保持表单当前值或使用默认值
            
            let importedCount = 0; // 记录成功导入的字段数
            const missingFields = []; // 记录缺失的字段
            
            // api_url: 如果存在且不为空，则导入
            if (config.api_url !== undefined && config.api_url !== null && config.api_url !== '') {
                $('#api_url').val(config.api_url);
                importedCount++;
            } else {
                missingFields.push('api_url');
            }
            
            // outrank_uuid: 如果存在且不为空，则导入
            if (config.outrank_uuid !== undefined && config.outrank_uuid !== null && config.outrank_uuid !== '') {
                $('#outrank_uuid').val(config.outrank_uuid);
                importedCount++;
            } else {
                missingFields.push('outrank_uuid');
            }
            
            // outrank_token: 如果存在且不为空，则导入
            if (config.outrank_token !== undefined && config.outrank_token !== null && config.outrank_token !== '') {
                $('#outrank_token').val(config.outrank_token);
                importedCount++;
            } else {
                missingFields.push('outrank_token');
            }
            
            // push_method: 如果存在且有效，则导入；否则使用默认值 'post'
            if (config.push_method && ['post', 'jsonp'].includes(config.push_method)) {
                $(`input[name="push_method"][value="${config.push_method}"]`).prop('checked', true);
                importedCount++;
            } else {
                $(`input[name="push_method"][value="post"]`).prop('checked', true);
            }
            
            // use_zip: 如果存在，则导入；否则使用默认值 true
            // 优先使用 use_zip，如果没有则使用 use_gzip（兼容旧版本）
            if (config.use_zip !== undefined) {
                const useZip = config.use_zip === true || config.use_zip === 'true' || config.use_zip === 1;
                $('#use_zip').prop('checked', useZip);
                importedCount++;
            } else if (config.use_gzip !== undefined) {
                // 兼容旧配置（use_gzip）
                const useZip = config.use_gzip === true || config.use_gzip === 'true' || config.use_gzip === 1;
                $('#use_zip').prop('checked', useZip);
                importedCount++;
            } else {
                $('#use_zip').prop('checked', true); // 默认值
            }
            
            // push_real_rank: 如果存在，则导入；否则使用默认值 false
            if (config.push_real_rank !== undefined) {
                const pushRealRank = config.push_real_rank === true || config.push_real_rank === 'true' || config.push_real_rank === 1;
                $('#push_real_rank').prop('checked', pushRealRank);
                importedCount++;
            } else {
                $('#push_real_rank').prop('checked', false); // 默认值
            }
            
            // jsonp_chunk_size: 如果存在且有效，则导入；否则使用默认值 'auto'
            if (config.jsonp_chunk_size !== undefined && config.jsonp_chunk_size !== null) {
                const chunkSize = String(config.jsonp_chunk_size);
                if (chunkSize === 'auto' || ['1000', '2000', '3000', '4000', '5000', '6000'].includes(chunkSize)) {
                    $(`input[name="jsonp_chunk_size"][value="${chunkSize}"]`).prop('checked', true);
                    importedCount++;
                } else {
                    $(`input[name="jsonp_chunk_size"][value="auto"]`).prop('checked', true);
                }
            } else {
                $(`input[name="jsonp_chunk_size"][value="auto"]`).prop('checked', true);
            }
            
            // 更新UI显示
            // 显示/隐藏JSONP分包大小选项（根据push_method）
            const isJsonp = $('input[name="push_method"]:checked').val() === 'jsonp';
            $('#jsonp_chunk_size_group').toggle(isJsonp);
            
            // 更新JSONP最大分包大小提示（如果函数存在）
            if (typeof updateJsonpMaxSizeHint === 'function') {
                updateJsonpMaxSizeHint();
            }
            
            // 保存到localStorage（如果函数存在）
            if (typeof saveConfig === 'function') {
                saveConfig();
            }
            
            // 显示导入结果
            if (importedCount > 0) {
                let message = `配置导入成功，已导入 ${importedCount} 个字段`;
                let messageEn = `Config imported successfully, ${importedCount} fields imported`;
                
                if (missingFields.length > 0) {
                    const missingFieldsText = missingFields.join('、');
                    message += `。缺失字段：${missingFieldsText}，请手动填写`;
                    messageEn += `. Missing fields: ${missingFields.join(', ')}, please fill manually`;
                }
                
                addLog('success', `配置导入成功，已导入 ${importedCount} 个字段 (Config imported successfully, ${importedCount} fields imported)`);
                alerty.success({
                    message: message,
                    message_en: messageEn
                });
            } else {
                // 配置为空或所有字段都缺失
                addLog('info', '配置文件为空或所有字段缺失，请手动填写 (Config file is empty or all fields are missing, please fill manually)');
                alerty.info({
                    message: '配置文件为空或所有字段缺失，请手动填写',
                    message_en: 'Config file is empty or all fields are missing, please fill manually'
                });
            }
        } catch (error) {
            // 其他未预期的错误
            console.error('Import config error:', error);
            addLog('error', `配置导入失败: ${error.message} (Config import failed: ${error.message})`);
            alerty.error({
                message: `配置导入失败：${error.message}`,
                message_en: `Config import failed: ${error.message}`
            });
        }
        
        // 清空文件选择器
        event.target.value = '';
    };
    
    reader.onerror = function() {
        addLog('error', '文件读取失败 (File read failed)');
        alerty.error({
            message: '文件读取失败',
            message_en: 'File read failed'
        });
        event.target.value = '';
    };
    
    // 使用 UTF-8 编码读取文件
    reader.readAsText(file, 'UTF-8');
}

// HTML转义
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

