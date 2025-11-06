{__NOLAYOUT__}
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSGOJ 评测机接口调试工具</title>
    <!-- 使用框架提供的CSS -->
    {include file="../../csgoj/view/public/global_css" /}
    {include file="../../csgoj/view/public/global_js" /}
    <style>
        body {
            background-color: #f8f9fa;
            min-height: 100vh;
        }
        .main-container {
            min-height: 100vh;
            padding: 20px 0;
        }
        .response-card {
            background: white;
            border-radius: 0.375rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: 1px solid rgba(0, 0, 0, 0.125);
            max-width: 1200px;
            width: 100%;
            margin: 0 auto;
        }
        .response-header {
            background-color:lightgray;
            padding: 1.5rem;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.125);
        }
        .response-header h1 {
            margin: 0;
            font-size: 1.75rem;
            font-weight: 500;
        }
        .response-body {
            padding: 1.5rem;
        }
        .debug-section {
            background: white;
            border-radius: 0.375rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid #dee2e6;
        }
        .json-editor {
            font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, Courier, monospace;
            font-size: 0.875rem;
            background-color: #f8f9fa;
            border: 1px solid #ced4da;
            border-radius: 0.375rem;
            padding: 0.75rem;
            resize: vertical;
        }
        .json-editor:focus {
            border-color: #86b7fe;
            outline: 0;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
        .json-display {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 1rem;
            font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, Courier, monospace;
            font-size: 0.875rem;
            line-height: 1.5;
            max-height: 400px;
            overflow-y: auto;
            white-space: pre-wrap;
        }
        .status-alert {
            border-radius: 0.375rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        .status-success {
            background-color: #d1e7dd;
            border-color: #badbcc;
            color: #0f5132;
        }
        .status-error {
            background-color: #f8d7da;
            border-color: #f5c2c7;
            color: #842029;
        }
        .api-info {
            background-color: #e7f1ff;
            border: 1px solid #b6d7ff;
            border-radius: 0.375rem;
            padding: 1rem;
            margin-top: 1.5rem;
        }
        .code-badge {
            background-color: #fff3cd;
            border: 1px solid #ffecb5;
            color: #664d03;
            padding: 0.375rem 0.75rem;
            border-radius: 0.375rem;
            font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, Courier, monospace;
            font-size: 0.875rem;
            display: inline-block;
        }
        .icon-large {
            font-size: 1.25rem;
            margin-right: 0.5rem;
        }
        .card {
            border: 1px solid #dee2e6;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .card-header {
            border-bottom: 1px solid #dee2e6;
            font-weight: 500;
        }
        .card-body {
            padding: 1.5rem;
        }
        .json-format-btn {
            margin-left: 0.5rem;
        }
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="response-card">
            <div class="response-header">
                <h1>评测机接口调试工具</h1>
                <p class="mb-0 mt-2 text-muted">管理员专用接口调试界面</p>
            </div>
            <div class="response-body">
                <!-- 调试工具区域 -->
                <div class="debug-section">
                    <h5 class="mb-4 text-muted">接口调试</h5>
                    
                    <!-- 接口选择器 -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row align-items-end">
                                        <div class="col-md-8">
                                            <label for="apiAction" class="form-label">选择接口</label>
                                            <select class="form-select" id="apiAction" name="action">
                                                <option value="getpending">getpending - 获取待评测任务</option>
                                                <option value="getsolutioninfo">getsolutioninfo - 获取solution信息</option>
                                                <option value="getsolution">getsolution - 获取solution代码</option>
                                                <option value="getprobleminfo">getprobleminfo - 获取问题信息</option>
                                                <option value="getdata">getdata - 获取评测数据</option>
                                                <option value="getproblemlist">getproblemlist - 获取问题列表</option>
                                                <option value="getdatahash">getdatahash - 获取数据哈希</option>
                                                <option value="get_datafile_list">get_datafile_list - 获取数据文件列表</option>
                                                <option value="getdatafile">getdatafile - 获取单个文件内容</option>
                                                <option value="judge_login">judge_login - 评测机登录</option>
                                                <option value="updatesolution">updatesolution - 更新任务状态</option>
                                                <option value="addceinfo">addceinfo - 添加编译错误信息</option>
                                                <option value="addreinfo">addreinfo - 添加运行时错误信息</option>
                                                <option value="updateproblem">updateproblem - 更新问题统计信息</option>
                                                <option value="getallproblemsinfo">getallproblemsinfo - 获取所有问题数据信息</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="d-flex gap-2">
                                                <button type="button" class="btn btn-primary" id="sendGetRequest">
                                                    <i class="fas fa-search"></i> 发送 GET
                                                </button>
                                                <button type="button" class="btn btn-success" id="sendPostRequest">
                                                    <i class="fas fa-paper-plane"></i> 发送 POST
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <!-- GET 请求面板 -->
                        <div class="col-lg-6 mb-4">
                            <div class="card h-100">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0"><i class="fas fa-search"></i> GET 参数</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="getParams" class="form-label">GET 参数 (JSON 格式)</label>
                                        <textarea class="form-control json-editor" id="getParams" rows="8" placeholder='{"max_tasks": 5, "flg_checkout": 1, "sid": 123}' title="支持粘贴JSON内容，会自动格式化">{if isset($smarty.get) && !empty($smarty.get)}{$get_params_json}{/if}</textarea>
                                        <div class="mt-2">
                                            <button type="button" class="btn btn-outline-secondary btn-sm json-format-btn" id="formatGetJson" title="格式化JSON (Ctrl+Shift+F)">
                                                <i class="fas fa-magic"></i> 格式化 JSON
                                            </button>
                                            <button type="button" class="btn btn-outline-info btn-sm json-format-btn" id="clearGetJson">
                                                <i class="fas fa-trash"></i> 清空
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- POST 请求面板 -->
                        <div class="col-lg-6 mb-4">
                            <div class="card h-100">
                                <div class="card-header bg-success text-white">
                                    <h5 class="mb-0"><i class="fas fa-paper-plane"></i> POST 数据</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="postJson" class="form-label">JSON 请求体</label>
                                        <textarea class="form-control json-editor" id="postJson" rows="8" placeholder='{"user_id": "admin", "password": "123456"}' title="支持粘贴JSON内容，会自动格式化">{if isset($json_body) && !empty($json_body)}{$json_body}{/if}</textarea>
                                        <div class="mt-2">
                                            <button type="button" class="btn btn-outline-secondary btn-sm json-format-btn" id="formatJson" title="格式化JSON (Ctrl+Shift+F)">
                                                <i class="fas fa-magic"></i> 格式化 JSON
                                            </button>
                                            <button type="button" class="btn btn-outline-info btn-sm json-format-btn" id="clearJson">
                                                <i class="fas fa-trash"></i> 清空
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 统一的响应结果显示区域 -->
                <div class="debug-section" id="responseResultSection">
                    <h5><i class="fas fa-database"></i> 响应结果</h5>
                    
                    <!-- 状态信息 -->
                    <div id="statusAlert" style="display:none;">
                        <div class="alert status-alert" role="alert">
                            <i class="fas icon-large"></i>
                            <span id="statusMessage"></span>
                        </div>
                    </div>
                    
                    <!-- 响应数据 -->
                    <div class="json-display" id="responseData" style="display:none;"></div>
                    
                    <!-- 错误代码 -->
                    <div id="errorCodeSection" style="display:none;">
                        <h6><i class="fas fa-exclamation-triangle"></i> 错误代码</h6>
                        <span class="code-badge" id="errorCode"></span>
                    </div>
                </div>
                
                <!-- API信息 -->
                <div class="api-info">
                    <h6><i class="fas fa-info-circle"></i> 接口信息</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <strong>API接口:</strong> <code>{$action}</code>
                        </div>
                        <div class="col-md-6">
                            <strong>请求时间:</strong> <span class="text-muted">{$timestamp}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 模板变量配置 -->
    <script>
        var PAGE_CONFIG = {
            hasMessage: <?php echo isset($message) && !empty($message) ? 'true' : 'false'; ?>,
            success: <?php echo isset($success) && $success ? 'true' : 'false'; ?>,
            message: <?php echo isset($message) ? "'" . addslashes($message) . "'" : 'null'; ?>,
            data: <?php echo isset($data) ? json_encode($data) : 'null'; ?>,
            code: <?php echo isset($code) ? "'" . addslashes($code) . "'" : 'null'; ?>,
            action: '<?php echo isset($action) ? addslashes($action) : ''; ?>',
            timestamp: '<?php echo isset($timestamp) ? addslashes($timestamp) : ''; ?>'
        };
    </script>
    
    <script>
        $(document).ready(function() {
            // 自动选中当前页面对应的接口
            const currentPath = window.location.pathname;
            const currentAction = currentPath.split('/').pop();
            if (currentAction && $('#apiAction option[value="' + currentAction + '"]').length > 0) {
                $('#apiAction').val(currentAction);
            }
            
            // 自动填充URL中的GET参数到输入框（JSON格式）
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.toString()) {
                const paramObj = {};
                for (const [key, value] of urlParams) {
                    if (key !== 'action') { // 排除action参数
                        // 尝试解析为数字，否则保持字符串
                        paramObj[key] = isNaN(value) ? value : (value.includes('.') ? parseFloat(value) : parseInt(value));
                    }
                }
                if (Object.keys(paramObj).length > 0) {
                    $('#getParams').val(JSON.stringify(paramObj, null, 2));
                }
            }
            
            // 格式化 POST JSON 按钮
            $('#formatJson').click(function() {
                try {
                    const jsonText = $('#postJson').val();
                    if (!jsonText.trim()) {
                        return;
                    }
                    
                    // 智能格式化：支持不带引号的键和单引号
                    const formatted = formatToValidJson(jsonText);
                    $('#postJson').val(formatted);
                } catch (e) {
                    alerty.error('格式化失败: ' + e.message);
                }
            });
            
            // 格式化 GET JSON 按钮
            $('#formatGetJson').click(function() {
                try {
                    const jsonText = $('#getParams').val();
                    if (!jsonText.trim()) {
                        return;
                    }
                    
                    // 智能格式化：支持不带引号的键和单引号
                    const formatted = formatToValidJson(jsonText);
                    $('#getParams').val(formatted);
                } catch (e) {
                    alerty.error('格式化失败: ' + e.message);
                }
            });
            
            // 智能JSON格式化函数
            function formatToValidJson(text) {
                // 移除注释（// 和 /* */）
                text = text.replace(/\/\*[\s\S]*?\*\//g, '');
                text = text.replace(/\/\/.*$/gm, '');
                
                // 处理单引号字符串
                text = text.replace(/'([^'\\]|\\.)*'/g, function(match) {
                    return '"' + match.slice(1, -1).replace(/\\/g, '\\\\').replace(/"/g, '\\"') + '"';
                });
                
                // 处理不带引号的键（JavaScript对象字面量格式）
                text = text.replace(/([{,]\s*)([a-zA-Z_$][a-zA-Z0-9_$]*)\s*:/g, '$1"$2":');
                
                // 处理布尔值和null
                text = text.replace(/\btrue\b/g, 'true');
                text = text.replace(/\bfalse\b/g, 'false');
                text = text.replace(/\bnull\b/g, 'null');
                
                // 处理数字（确保是有效的JSON数字）
                text = text.replace(/\b(\d+\.?\d*)\b/g, function(match, num) {
                    // 如果已经是字符串的一部分，不处理
                    if (match.includes('"')) return match;
                    return num;
                });
                
                // 尝试解析和重新格式化
                try {
                    const parsed = JSON.parse(text);
                    return JSON.stringify(parsed, null, 2);
                } catch (e) {
                    // 如果还是解析失败，尝试更宽松的解析
                    try {
                        // 使用eval（在安全环境下）来解析JavaScript对象字面量
                        const cleaned = text.replace(/[`]/g, '"'); // 处理模板字符串
                        const parsed = eval('(' + cleaned + ')');
                        return JSON.stringify(parsed, null, 2);
                    } catch (e2) {
                        throw new Error('无法解析为有效的JSON格式。请检查语法。');
                    }
                }
            }
            
            // 清空按钮
            $('#clearJson').click(function() {
                $('#postJson').val('');
            });
            
            $('#clearGetJson').click(function() {
                $('#getParams').val('');
            });
            
            // 自动格式化粘贴的JSON内容
            function autoFormatOnPaste(textareaId) {
                $(textareaId).on('paste', function(e) {
                    // 延迟执行，等待粘贴内容插入到textarea
                    setTimeout(() => {
                        try {
                            const currentValue = $(this).val();
                            if (!currentValue.trim()) {
                                return;
                            }
                            
                            // 检查是否看起来像JSON（包含{}或[]）
                            if (!currentValue.includes('{') && !currentValue.includes('[')) {
                                return;
                            }
                            
                            // 尝试智能格式化
                            const formatted = formatToValidJson(currentValue);
                            if (formatted !== currentValue) {
                                $(this).val(formatted);
                                // 使用更温和的提示
                                const toast = alerty.success('已自动格式化JSON内容', 2000);
                            }
                        } catch (e) {
                            // 格式化失败时不显示错误，保持原内容
                            // 可以在这里添加调试信息
                            console.warning('JSON格式化失败:', e.message);
                        }
                    }, 10);
                });
            }
            
            // 为两个textarea启用自动格式化
            autoFormatOnPaste('#postJson');
            autoFormatOnPaste('#getParams');
            
            // 添加键盘快捷键支持 (Ctrl+Shift+F 格式化JSON)
            $('.json-editor').on('keydown', function(e) {
                if (e.ctrlKey && e.shiftKey && e.keyCode === 70) { // Ctrl+Shift+F
                    e.preventDefault();
                    const textareaId = '#' + $(this).attr('id');
                    if (textareaId === '#postJson') {
                        $('#formatJson').click();
                    } else if (textareaId === '#getParams') {
                        $('#formatGetJson').click();
                    }
                }
            });
            
            // 渲染响应到页面（统一处理服务端和AJAX响应）
            function renderResponse(resp, isSuccess = true, message = '', code = null) {
                try {
                    // 显示状态信息
                    if (message) {
                        const statusClass = isSuccess ? 'status-success' : 'status-error';
                        const statusIcon = isSuccess ? 'fa-check-circle' : 'fa-times-circle';
                        
                        $('#statusAlert .alert').removeClass('status-success status-error').addClass(statusClass);
                        $('#statusAlert .fas').removeClass('fa-check-circle fa-times-circle').addClass(statusIcon);
                        $('#statusMessage').text(message);
                        $('#statusAlert').show();
                    } else {
                        $('#statusAlert').hide();
                    }
                    
                    // 显示响应数据
                    if (resp) {
                        let text;
                        if (typeof resp === 'string') {
                            text = resp;
                        } else if (typeof resp === 'object') {
                            text = JSON.stringify(resp, null, 2);
                        } else {
                            text = String(resp);
                        }
                        $('#responseData').text(text).show();
                    } else {
                        $('#responseData').hide();
                    }
                    
                    // 显示错误代码
                    if (code) {
                        $('#errorCode').text(code);
                        $('#errorCodeSection').show();
                    } else {
                        $('#errorCodeSection').hide();
                    }
                    
                    // 显示整个响应区域
                    $('#responseResultSection').show();
                } catch (e) {
                    $('#responseData').text('无法显示返回数据').show();
                    $('#responseResultSection').show();
                }
            }
            
            // 初始化页面时渲染服务端数据
            function initServerResponse() {
                if (PAGE_CONFIG.hasMessage) {
                    // 处理服务端数据
                    let serverData = null;
                    if (PAGE_CONFIG.data) {
                        try {
                            // 如果data是字符串，尝试解析为JSON
                            if (typeof PAGE_CONFIG.data === 'string') {
                                serverData = JSON.parse(PAGE_CONFIG.data);
                            } else {
                                serverData = PAGE_CONFIG.data;
                            }
                        } catch (e) {
                            serverData = PAGE_CONFIG.data;
                        }
                    }
                    renderResponse(serverData, PAGE_CONFIG.success, PAGE_CONFIG.message, PAGE_CONFIG.code);
                } else {
                    // 如果没有服务端数据，隐藏响应区域
                    $('#responseResultSection').hide();
                }
            }

            // GET 请求按钮（使用 AJAX，不跳转页面）
            $('#sendGetRequest').click(function() {
                const params = $('#getParams').val();
                const action = $('#apiAction').val();
                
                try {
                    // 解析JSON参数
                    let paramObj = {};
                    if (params.trim()) {
                        try {
                            paramObj = JSON.parse(params);
                        } catch (e) {
                            // 如果不是严格JSON，尝试智能格式化
                            const formatted = formatToValidJson(params);
                            paramObj = JSON.parse(formatted);
                        }
                    }
                    
                    // 构建URL
                    const url = new URL(window.location);
                    url.search = '';
                    url.pathname = url.pathname.replace(/\/[^\/]*$/, '/' + action);
                    
                    // 添加参数到URL
                    Object.keys(paramObj).forEach(key => {
                        url.searchParams.append(key, paramObj[key]);
                    });
                    
                    // 发送 AJAX GET
                    $.ajax({
                        url: url.toString(),
                        method: 'GET',
                        dataType: 'json',
                        success: function(res) {
                            alerty.success('GET 请求成功');
                            // 解析响应结构
                            if (res && typeof res === 'object') {
                                const isSuccess = res.code === 1 || res.success === true;
                                const message = res.msg || res.message || (isSuccess ? '请求成功' : '请求失败');
                                const data = res.data || res;
                                const errorCode = res.code && res.code !== 1 ? res.code : null;
                                renderResponse(data, isSuccess, message, errorCode);
                            } else {
                                renderResponse(res, true, 'GET 请求成功');
                            }
                        },
                        error: function(xhr) {
                            alerty.error('GET 请求失败');
                            let errorData = null;
                            let errorMessage = 'GET 请求失败';
                            
                            try {
                                const responseText = xhr.responseText;
                                if (responseText) {
                                    const parsed = JSON.parse(responseText);
                                    errorData = parsed;
                                    errorMessage = parsed.msg || parsed.message || errorMessage;
                                } else {
                                    errorMessage = 'HTTP ' + xhr.status;
                                }
                            } catch (e) {
                                errorData = xhr.responseText || ('HTTP ' + xhr.status);
                            }
                            
                            renderResponse(errorData, false, errorMessage);
                        }
                    });
                } catch (e) {
                    alerty.error('GET 参数格式错误: ' + e.message);
                }
            });
            
            // POST 请求按钮（使用 AJAX，不跳转页面）
            $('#sendPostRequest').click(function() {
                const jsonData = $('#postJson').val();
                const action = $('#apiAction').val();
                const getParams = $('#getParams').val();
                
                try {
                    // 验证 JSON 格式
                    let bodyToSend = jsonData.trim();
                    if (bodyToSend) {
                        // 先尝试直接解析为 JSON
                        try {
                            JSON.parse(bodyToSend);
                        } catch (e) {
                            // 若不是严格 JSON，尝试智能格式化再发送
                            bodyToSend = formatToValidJson(bodyToSend);
                        }
                    } else {
                        bodyToSend = '{}';
                    }
                    
                    // 解析 GET 参数（JSON格式）
                    let paramObj = {};
                    if (getParams.trim()) {
                        try {
                            paramObj = JSON.parse(getParams);
                        } catch (e) {
                            // 如果不是严格JSON，尝试智能格式化
                            const formatted = formatToValidJson(getParams);
                            paramObj = JSON.parse(formatted);
                        }
                    }
                    
                    // 构建 URL，包含 GET 参数
                    let actionUrl = window.location.pathname.replace(/\/[^\/]*$/, '/' + action);
                    const url = new URL(actionUrl, window.location.origin);
                    Object.keys(paramObj).forEach(key => {
                        url.searchParams.append(key, paramObj[key]);
                    });
                    actionUrl = url.toString();
                    
                    // 发送 AJAX POST（application/json）
                    $.ajax({
                        url: actionUrl,
                        method: 'POST',
                        data: bodyToSend,
                        contentType: 'application/json; charset=UTF-8',
                        dataType: 'json',
                        success: function(res) {
                            alerty.success('POST 请求成功');
                            // 解析响应结构
                            if (res && typeof res === 'object') {
                                const isSuccess = res.code === 1 || res.success === true;
                                const message = res.msg || res.message || (isSuccess ? '请求成功' : '请求失败');
                                const data = res.data || res;
                                const errorCode = res.code && res.code !== 1 ? res.code : null;
                                renderResponse(data, isSuccess, message, errorCode);
                            } else {
                                renderResponse(res, true, 'POST 请求成功');
                            }
                        },
                        error: function(xhr) {
                            alerty.error('POST 请求失败');
                            let errorData = null;
                            let errorMessage = 'POST 请求失败';
                            
                            try {
                                const responseText = xhr.responseText;
                                if (responseText) {
                                    const parsed = JSON.parse(responseText);
                                    errorData = parsed;
                                    errorMessage = parsed.msg || parsed.message || errorMessage;
                                } else {
                                    errorMessage = 'HTTP ' + xhr.status;
                                }
                            } catch (e) {
                                errorData = xhr.responseText || ('HTTP ' + xhr.status);
                            }
                            
                            renderResponse(errorData, false, errorMessage);
                        }
                    });
                } catch (e) {
                    alerty.error('JSON 格式错误: ' + e.message);
                }
            });
            
            // 响应数据复制功能
            $('#responseData').on('click', function() {
                const text = $(this).text();
                if (text.trim()) {
                    navigator.clipboard.writeText(text).then(function() {
                        alerty.success('数据已复制到剪贴板');
                    }).catch(function() {
                        alerty.error('复制失败，请手动选择复制');
                    });
                }
            });
            
            // 添加鼠标悬停提示
            $('#responseData').attr('title', '点击复制数据');
            $('#responseData').css('cursor', 'pointer');
            
            // 初始化页面
            initServerResponse();
            
            // 添加淡入动画
            $('.response-card').hide().fadeIn(800);
        });
    </script>
</body>
</html>
