

// 打印状态formatter
function FormatterPrintStatus(value, row, index, field) {
    const statusMap = {
        0: { 
            icon: 'bi bi-clock', 
            class: 'btn-info', 
            enText: 'Waiting',
            title: '等待打印，点击查看代码 (Waiting for Print, Click to View Code)'
        },
        1: { 
            icon: 'bi bi-check-circle', 
            class: 'btn-success', 
            enText: 'Printed',
            title: '已打印，点击查看代码 (Printed, Click to View Code)'
        },
        2: { 
            icon: 'bi bi-x-circle', 
            class: 'btn-danger', 
            enText: 'Denied',
            title: '已拒绝，点击查看代码 (Denied, Click to View Code)'
        }
    };
    
    const status = statusMap[value] || { 
        icon: 'bi bi-question-circle', 
        class: 'btn-secondary', 
        enText: 'Unknown',
        title: '未知状态 (Unknown Status)'
    };
    
    return `<span class="btn btn-sm ${status.class} print-status" print_id="${row.print_id}" title="${status.title}">
        <i class="${status.icon} "></i><span class="en-text">${status.enText}</span>
    </span>`;
}
// 打印操作按钮formatter
function FormatterPrintAction(value, row, index, field) {
    if (row.flg_can_print) {
        return `<span class="btn btn-success btn-sm do_print" title="双击打印代码 (Double-click to Print Code)">
            <i class="bi bi-printer "></i><span class="en-text">Print</span>
        </span>`;
    }
    return '-';
}

// 拒绝操作按钮formatter
function FormatterPrintDenyAction(value, row, index, field) {
    if (row.flg_can_deny) {
        return `<span class="btn btn-danger btn-sm do_deny" title="双击拒绝打印请求 (Double-click to Deny Print Request)">
            <i class="bi bi-x-circle "></i><span class="en-text">Deny</span>
        </span>`;
    }
    return '-';
}

// 打印状态选项映射（与 FormatterPrintStatus 保持一致）
const printStatusOptions = {
    0: { 
        text: '等待打印',
        textEn: 'Waiting'
    },
    1: { 
        text: '已打印',
        textEn: 'Printed'
    },
    2: { 
        text: '已拒绝',
        textEn: 'Denied'
    }
};

// 初始化打印状态选择框选项
function initPrintStatusSelect() {
    const select = document.querySelector('select[name="print_status"]');
    if (!select) return;
    
    // 保留 "All" 选项，清除其他选项
    const allOption = select.querySelector('option[value="-1"]');
    if (allOption) {
        select.innerHTML = '';
        select.appendChild(allOption);
    }
    
    // 添加三种状态的选项（select option 不支持 HTML，使用纯文本显示）
    Object.keys(printStatusOptions).forEach(value => {
        const option = document.createElement('option');
        option.value = value;
        const status = printStatusOptions[value];
        // 使用中英文格式：中文 (English)
        option.textContent = `${status.text} (${status.textEn})`;
        select.appendChild(option);
    });
}

// 初始化自动打印开关
function initAutoPrintSwitch() {
    const switchEl = document.getElementById('auto_print_box');
    if (!switchEl) return;
    
    // 初始化 csg-switch
    if (window.csgSwitch) {
        window.csgSwitch.initSwitch(switchEl, {
            onChange: function(checked) {
                handleAutoPrintToggle(checked);
            }
        });
    }
}

// 初始化颜色模式开关
function initPrintColorModeSwitch() {
    const switchEl = document.getElementById('print_color_mode_box');
    if (!switchEl) return;
    
    // 初始化 csg-switch（使用 localStorage 自动保存状态）
    if (window.csgSwitch) {
        window.csgSwitch.initSwitch(switchEl, {
            onChange: function(checked) {
                // 状态变化时保存到 localStorage
                try {
                    localStorage.setItem('print_color_mode', checked ? 'true' : 'false');
                } catch(e) {
                    // 忽略 localStorage 错误
                }
            }
        });
    }
}

// 处理自动打印开关切换
function handleAutoPrintToggle(checked) {
    const switchEl = document.getElementById('auto_print_box');
    
    if (checked) {
        alerty.confirm({
            message: '自动打印将会发送页面内所有等待的打印任务，并自动刷新列表接收新任务，该状态下Room将禁止修改。<br/><strong>请确认Room已设置好并已执行Filter，且任务按打印状态列升序（默认），即表头有朝上的小三角。</strong>',
            message_en: 'Auto print will send all Waiting print tasks on the page and automatically refresh the list to receive new tasks. Room will be disabled in this state.<br/><strong>Please confirm that Room is set up and Filter has been executed, and tasks are sorted in ascending order by Print Status column (default), i.e., there is an upward arrow in the table header.</strong>',
            callback: function(){
                room_ids.attr('readonly', 'readonly');
                DoAutoPrint();
            },
            callbackCancel: function(){
                alerty.message("已取消", "Cancelled");
                // 关闭开关
                if (window.csgSwitch && switchEl) {
                    const switchId = switchEl.dataset.csgId;
                    if (switchId) {
                        const switchInstance = window.csgSwitch.switches.get(switchId);
                        if (switchInstance) {
                            switchEl.checked = false;
                            window.csgSwitch.updateSwitchState(switchEl, switchInstance.config);
                        }
                    }
                }
            }
        });
    } else {
        room_ids.removeAttr('readonly');
        clearTimeout(auto_print_timout_id);
        auto_print_time = auto_print_interval;
        auto_print_interval_span.text(auto_print_time);
    }
}



var $table;
var print_status_page_information;
var team_id;
var auto_print_interval;
var auto_print_time;
var auto_print_timout_id;
var auto_print_interval_span;
var team_map = {}; // 队伍信息映射（只在页面加载时初始化一次）
var team_data_loaded = false; // 标记队伍数据是否已加载

// 加载队伍数据（只在页面加载时调用一次，返回 Promise）
function loadTeamData() {
    // 如果已经加载过，直接返回 resolved Promise
    if (team_data_loaded) {
        return Promise.resolve();
    }
    
    const config = window.PRINT_STATUS_CONFIG || {};
    const contestDataUrl = config.contest_data_url || '';
    const cid = config.cid || '';
    
    if (!contestDataUrl || !cid) {
        console.warn('Contest data URL or CID not configured, team info will not be available');
        team_data_loaded = true; // 标记为已加载（避免重复请求）
        return Promise.resolve();
    }
    
    // 返回 Promise
    return new Promise((resolve, reject) => {
        // 请求队伍数据
        $.get(contestDataUrl + '?info_need[]=team&cid=' + cid, function(ret) {
            if (ret.code === 1 && ret.data && ret.data.team) {
                // 转换数据格式
                const teamData = { team: ret.data.team };
                RankToolConvertListToDict(teamData);
                
                // 构建 team_map
                team_map = {};
                if (Array.isArray(teamData.team)) {
                    teamData.team.forEach(team => {
                        if (team.team_id) {
                            team_map[team.team_id] = team;
                        }
                    });
                }
                team_data_loaded = true;
                resolve();
            } else {
                console.warn('Failed to load team data:', ret.msg || 'Unknown error');
                team_data_loaded = true; // 标记为已加载（避免重复请求）
                resolve(); // 即使失败也 resolve，让表格可以初始化
            }
        }).fail(function(xhr, status, error) {
            console.warn('Failed to load team data:', error);
            team_data_loaded = true; // 标记为已加载（避免重复请求）
            resolve(); // 即使失败也 resolve，让表格可以初始化
        });
    });
}

// 手动初始化表格函数
function initPrintStatusTable() {
    $table = $('#print_status_table');
    // 如果已经初始化，直接返回
    if ($table.data('bootstrap.table')) {
        return;
    }
    // 手动初始化表格
    $table.bootstrapTable();
}

// 合并队伍信息到数据行
function mergeTeamInfo(rows) {
    if (!rows || !Array.isArray(rows)) {
        return rows;
    }
    return rows.map(row => {
        const teamInfo = team_map[row.team_id];
        
        if (teamInfo) {
            row.school = teamInfo.school || '';
            row.name = teamInfo.name || '';
        } else {
            row.school = '';
            row.name = '';
        }
        return row;
    });
}

// 使用工厂函数生成queryParams处理器，支持自定义处理
window.queryParams = window.makeQueryParams('print_status', null, function(params) {
    // 处理队伍ID筛选
    const teamId = $('#team_id_input').val();
    if (teamId && teamId.trim() !== '') {
        params.team_id = teamId.trim();
    }
    
    // 处理房间筛选
    const roomIds = $('#room_ids').val();
    if (roomIds && roomIds.trim() !== '') {
        params.room_ids = roomIds.trim();
    }
    
    return params;
});

// 自定义 responseHandler：在数据返回后合并 team 信息
window.printStatusResponseHandler = function(res) {
    // print_status_ajax 返回格式: {total: xxx, rows: [...], order: 'xxx'}
    // 合并队伍信息到 rows
    
    if (res && res.rows && Array.isArray(res.rows)) {
        res.rows = mergeTeamInfo(res.rows);
    }
    
    return res;
};

// 先初始化打印状态选择框，确保选项已经生成
$(function(){
    // 初始化打印状态选择框（必须在工具栏初始化之前）
    initPrintStatusSelect();
    
    // 先加载队伍数据，完成后再初始化表格
    loadTeamData().then(function() {
        // 队伍数据加载完成后，初始化表格
        initPrintStatusTable();
        
        // 使用 setTimeout 确保 DOM 更新完成后再初始化工具栏
        setTimeout(function() {
            // 初始化工具栏（在打印状态选择框初始化之后）
            initBootstrapTableToolbar({
                tableId: 'print_status_table',
                prefix: 'print_status',
                filterSelectors: ['print_status'],
                searchInputId: null, // 使用自定义搜索
                customHandlers: {
                    // 自定义处理器：URL搜索同步
                    initUrlSearch: function() {
                        let print_status_table = $('#print_status_table');
                        
                        // 处理所有筛选框的 URL anchor 同步
                        $('.print_status_filter').each(function(index, elem){
                            let search_input = $(this);
                            let search_name = search_input.attr('name');
                            
                            // 使用全局的 GetAnchor/SetAnchor 函数
                            let search_str = GetAnchor(search_name);
                            if(search_str !== null) {
                                search_input.val(search_str);
                            }
                            
                            // 监听输入框变化，同步到 URL anchor
                            search_input.on('input change', function() {
                                SetAnchor(search_input.val(), search_name);
                                // 筛选条件变化时刷新表格
                                $table.bootstrapTable('refresh', {pageNumber: 1});
                            });
                        });
                        
                        // 监听 hashchange 事件，从 URL anchor 恢复筛选条件
                        $(window).on('hashchange', function(e) {
                            $('.print_status_filter').each(function(index, elem){
                                let search_input = $(this);
                                let search_name = search_input.attr('name');
                                let search_str = GetAnchor(search_name);
                                if(search_str !== null) {
                                    search_input.val(search_str).trigger('change');
                                }
                            });
                        });
                    }
                }
            });
        }, 0);
        
        // 初始化自动打印开关
        initAutoPrintSwitch();
        
        // 初始化颜色模式开关
        initPrintColorModeSwitch();
        
        // 检查是否已经开启自动打印（从 localStorage 或其他地方恢复状态）
        const switchEl = document.getElementById('auto_print_box');
        if (switchEl && switchEl.checked) {
            room_ids.attr('readonly', 'readonly');
            DoAutoPrint();
        }

        //table related
        print_status_page_information = $('#print_status_page_information');
        team_id = print_status_page_information.attr('team_id');

        auto_print_interval = 20;
        auto_print_time = auto_print_interval;
        auto_print_interval_span = $('#auto_print_interval_span');
        room_ids = $('#room_ids');
        
        // 刷新按钮事件（只刷新表格数据，不重新加载队伍数据）
        $('#print_status_refresh').on('click', function(e) {
            e.preventDefault();
            $table.bootstrapTable('refresh');
        });
        
        // 清空按钮事件
        $('#print_status_clear').on('click', function(e) {
            e.preventDefault();
            SetPrintFilter(true);
        });
        
        // 表格点击事件处理
        $table.on('click-cell.bs.table', function(e, field, val, row, td){
            var tdContent = td.children();
            if(field == 'print_status') {
                if(row.flg_showcode == 1) {
                    $.get(
                        print_status_page_information.attr('show_code_url'),
                        {
                            'print_id': row.print_id,
                            'cid': print_status_page_information.attr('cid')
                        },
                        function(ret){
                            if(ret.code == 1)
                            {
                                var data = ret.data;
                                var showcode_pre = $("<pre>" + data['auth'] + data['source'] +"</pre>")[0];
                                hljs.highlightElement(showcode_pre);
                                alerty.modal({
                                    title: '打印请求代码',
                                    message: showcode_pre.outerHTML,
                                    okText: '确定',
                                    width: '900px',
                                    allowBackdropClose: true
                                });
                            }
                            else
                            {
                                alerty.error(ret.msg);
                                return false;
                            }
                        }
                    );
                }
            }
        });
        $table.on('dbl-click-cell.bs.table', function(e, field, val, row, td) {
            if(field == 'print_id') {
                // 双击 print_id 列预览打印
                if(row.flg_can_print == 1) {
                    PreviewPrint(row['print_id'], row);
                }
            }
            else if(field == 'do_print') {
                // 双击打印按钮执行打印
                if(row.flg_can_print == 1) {
                    StartSinglePrint(row['print_id']);
                }
            }
            else if(field == 'do_deny') {
                // 双击拒绝按钮执行拒绝
                if(row.flg_can_deny == 1) {
                    $.get(
                        'print_deny_ajax',
                        {
                            'print_id': row['print_id'],
                            'cid': print_status_page_information.attr('cid')
                        },
                        function(ret){
                            if(ret.code == 1) {
                                alerty.success({
                                    message: `打印请求 ${row['print_id']} 已拒绝`,
                                    message_en: `Print request ${row['print_id']} has been denied`
                                });
                                // 只刷新表格，不重新加载队伍数据
                                $table.bootstrapTable('refresh');
                            }
                            else {
                                alerty.error(ret.msg);
                            }
                        }
                    );
                }
            }
        });
        $table.on('post-body.bs.table', function(){
            //状态表格更新完毕时判断是否需要自动打印代码。AutoPrint()会触发表格刷新。
            const switchEl = document.getElementById('auto_print_box');
            if (switchEl && switchEl.checked) {
                DoAutoPrint();
            }
        });
    });
});

$(window).on('keydown', function(e) {
    if (e.key === 'F5' && !e.ctrlKey) {
        e.preventDefault();
        // 只刷新表格，不重新加载队伍数据
        $table.bootstrapTable('refresh');
    }
});


// ============================================================================
// ============================================================================
// 打印/预览执行模块（调用 print_control.js 中的函数）
// ============================================================================
// ============================================================================

// 执行打印或预览的公共函数（复用逻辑）
function ExecutePrintOrPreview(print_id, row, preview) {
    // 从表格获取行数据（如果未提供）
    if (!row) {
        const rows = $table.bootstrapTable('getData');
        row = rows.find(r => r.print_id == print_id);
    }
    
    // 如果还是找不到行数据，使用基本信息
    if (!row) {
        row = { print_id: print_id, team_id: '', room: '', code_length: '' };
    }
    
    // 检查 Lodop 是否已初始化（仅预览时需要）
    if (preview && (typeof LODOP === 'undefined' || !LODOP)) {
        alerty.error({
            message: 'Lodop 未初始化，请刷新页面重试',
            message_en: 'Lodop not initialized, please refresh the page'
        });
        return;
    }
    
    // 收集队伍信息
    const teamInfo = team_map[row.team_id] || {};
    const printInfo = {
        print_id: print_id,
        team_id: row.team_id || '',
        room: row.room || '',
        school: row.school || teamInfo.school || '',
        name: row.name || teamInfo.name || '',
        code_length: row.code_length || ''
    };
    
    // 获取代码内容并执行打印或预览
    $.get(
        'print_code_plain_content_ajax',
        {
            'print_id': print_id,
            'cid': print_status_page_information.attr('cid')
        },
        function(ret){
            if(ret.code == 1)
            {
                // 合并所有打印信息到数据中（完整的数据处理）
                var printData = {
                    print_id: ret.data.print_id || print_id,
                    team_id: ret.data.team_id || printInfo.team_id,
                    source: ret.data.source || '',
                    lang: ret.data.lang || '',
                    contest_title: ret.data.contest_title || '',
                    school: printInfo.school || '',
                    name: printInfo.name || '',
                    room: printInfo.room || '',
                    in_date: ret.data.in_date
                };
                
                if (preview) {
                    // 预览模式：使用 PrintCode 函数，传入 preview=true 参数
                    // 这样预览和打印使用完全相同的逻辑，确保预览结果与打印结果一致
                    PrintCode(printData, null, true);
                } else {
                    // 打印模式：执行打印任务（使用回调）
                    PrintCode(printData, function(printError, printResult) {
                        // 执行状态更新任务（使用 Promise）
                        UpdatePrintStatus(print_id, printInfo, printError, printResult);
                    });
                }
            }
            else
            {
                // 获取代码内容失败
                if (preview) {
                    alerty.error({
                        message: '获取代码内容失败',
                        message_en: 'Failed to get code content'
                    });
                } else {
                    ShowPrintResultAlert(printInfo, {
                        getCodeError: ret.msg || '获取代码内容失败'
                    }, null, null);
                }
            }
        }
    ).fail(function(xhr, status, error) {
        // 网络错误
        if (preview) {
            alerty.error({
                message: '网络错误：' + (xhr.responseJSON?.msg || xhr.statusText || '未知错误'),
                message_en: 'Network error: ' + (xhr.responseJSON?.msg || xhr.statusText || 'Unknown error')
            });
        } else {
            ShowPrintResultAlert(printInfo, {
                getCodeError: xhr.responseJSON?.msg || xhr.statusText || '网络错误'
            }, null, null);
        }
    });
}

// 预览打印内容（复用真实打印逻辑）
function PreviewPrint(print_id, row)
{
    ExecutePrintOrPreview(print_id, row, true);
}

// 开始单个打印任务
function StartSinglePrint(print_id, row)
{
    ExecutePrintOrPreview(print_id, row, false);
}

function AutoPrint()
{
    //开始自动打印的倒计时
    auto_print_time --;
    if(auto_print_time <= 0)
    {
        // 只刷新表格，不重新加载队伍数据
        $table.bootstrapTable('refresh');
    }
    else
    {
        auto_print_timout_id = setTimeout(function(){AutoPrint();}, 1000);
    }
    auto_print_interval_span.text(auto_print_time);
}


// 自动打印任务队列
var auto_print_queue = [];
var auto_print_processing = false;
var auto_print_interval_ms = 200; // 每个任务间隔200毫秒

function DoAutoPrint()
{
    clearTimeout(auto_print_timout_id);
    
    // 收集所有 Waiting 的打印任务
    var waitingTasks = [];
    $(".print-status").each(function(){
        if($(this).text().includes('Waiting')) {
            const print_id = $(this).attr('print_id');
            const rows = $table.bootstrapTable('getData');
            const row = rows.find(r => r.print_id == print_id);
            if (row) {
                waitingTasks.push({ print_id: print_id, row: row });
            }
        }
    });
    
    // 将新任务添加到队列
    if (waitingTasks.length > 0) {
        waitingTasks.forEach(function(task) {
            // 检查是否已在队列中
            if (auto_print_queue.findIndex(function(t) { return t.print_id === task.print_id; }) === -1) {
                auto_print_queue.push(task);
            }
        });
    }
    
    // 处理队列中的任务
    processAutoPrintQueue();
    
    // 设置定时刷新
    var print_flag = (waitingTasks.length > 0 || auto_print_queue.length > 0);
    auto_print_time = 5;
    if(print_flag != true)
        auto_print_time = auto_print_interval;
    AutoPrint();
}

// 处理自动打印队列
function processAutoPrintQueue()
{
    // 如果正在处理或队列为空，直接返回
    if (auto_print_processing || auto_print_queue.length === 0) {
        return;
    }
    
    // 标记为处理中
    auto_print_processing = true;
    
    // 取出队列中的第一个任务
    var task = auto_print_queue.shift();
    
    // 执行打印任务
    StartSinglePrint(task.print_id, task.row);
    
    // 延迟后处理下一个任务（给打印服务一些时间）
    setTimeout(function() {
        auto_print_processing = false;
        // 继续处理队列
        if (auto_print_queue.length > 0) {
            setTimeout(processAutoPrintQueue, auto_print_interval_ms);
        }
    }, auto_print_interval_ms);
}

function UpdatePrintStatus(print_id, printInfo, printError, printResult)
{
    $.get(
        'print_do_ajax',
        {
            'print_id': print_id,
            'cid': print_status_page_information.attr('cid')
        },
        function(ret){
            if(ret.code == 1)
            {
                // 状态更新成功
                ShowPrintResultAlert(printInfo, null, printError, ret);
            }
            else
            {
                // 状态更新失败
                ShowPrintResultAlert(printInfo, {
                    updateStatusError: ret.msg || '更新打印状态失败'
                }, printError, null);
            }
        }
    ).fail(function(xhr, status, error) {
        // 网络错误
        ShowPrintResultAlert(printInfo, {
            updateStatusError: xhr.responseJSON?.msg || xhr.statusText || '网络错误'
        }, printError, null);
    });
}

// 显示统一的打印结果消息
function ShowPrintResultAlert(printInfo, errors, printError, updateStatusResult) {
    // 构建详细信息
    const details = [];
    if (printInfo.team_id) details.push(`队伍ID: ${printInfo.team_id}`);
    if (printInfo.room) details.push(`房间: ${printInfo.room}`);
    if (printInfo.school) details.push(`学校: ${printInfo.school}`);
    if (printInfo.name) details.push(`队名: ${printInfo.name}`);
    if (printInfo.print_id) details.push(`打印ID: ${printInfo.print_id}`);
    if (printInfo.code_length) details.push(`代码长度: ${printInfo.code_length}`);
    
    const detailsEn = [];
    
    // 检查是否有任何错误
    const hasError = printError || (errors && Object.keys(errors).length > 0);
    
    if (hasError) {
        // 有错误，使用 alerty.alert
        let errorMessages = [];
        let errorMessagesEn = [];
        
        if (printError) {
            errorMessages.push(`发送到打印机失败: ${printError.message || printError}`);
            errorMessagesEn.push(`Failed to send to printer: ${printError.message || printError}`);
        }
        
        if (errors) {
            if (errors.getCodeError) {
                errorMessages.push(`获取代码内容失败: ${errors.getCodeError}`);
                errorMessagesEn.push(`Failed to get code content: ${errors.getCodeError}`);
            }
            if (errors.updateStatusError) {
                errorMessages.push(`更新打印状态失败: ${errors.updateStatusError}`);
                errorMessagesEn.push(`Failed to update print status: ${errors.updateStatusError}`);
            }
        }
        
        alerty.alert({
            title: '打印失败<span class="en-text">Print Failed</span>',
            message: details.join('，') + '<br/><br/>' + errorMessages.join('<br/>'),
            message_en: detailsEn.join(', ') + '<br/><br/>' + errorMessagesEn.join('<br/>'),
            width: '600px'
        });
    } else {
        // 成功，使用 alerty.success
        alerty.success({
            message: `打印任务已完成<br/>${details.join('\n')}`,
            message_en: `Print job completed<br/>${detailsEn.join(', ')}`
        });
    }
    
    // 刷新表格
    $table.bootstrapTable('refresh');
}

// 处理搜索框动态anchor    
function SetPrintFilter(clear=false) {
    $('.print_status_filter').each(function(index, elem){
        let search_input = $(this);
        let search_name = search_input.attr('name');
        if(clear)
        {
            if(search_input.is('input'))
                search_input.val('');
            else
                search_input.val(-1);
            SetAnchor(null, search_name);
        } else {
            let search_str = GetAnchor(search_name);
            search_input.unbind('input').on('input', function() {
                SetAnchor(search_input.val(), search_name);
                // 筛选条件变化时刷新表格
                $table.bootstrapTable('refresh', {pageNumber: 1});
            });
            if(search_str !== null) {
                search_input.val(search_str);
            }
        }
    });
    // 刷新表格
    $table.bootstrapTable('refresh', {pageNumber: 1});
}
