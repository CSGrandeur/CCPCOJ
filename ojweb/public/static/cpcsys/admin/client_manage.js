// 客户端管理 JavaScript 逻辑
let client_PreviewData = [];
let client_PreviewMode = false;
let client_delete_infoed = false;

// 初始化
function ClientManageInit() {
    // 初始化页面状态 - 显示实际数据
    client_PreviewMode = false;
    
    // 设置初始工具栏状态
    $("#toolbar_title").html(
        '<span class="cn-text"><i class="bi bi-database me-2"></i>实际数据</span><span class="en-text">Actual Data</span>'
    );
    $("#toolbar_subtitle").hide();
    $("#execute_import_btn").hide();
    $("#error_summary").hide();
    $("#batch_delete_btn").show();
    $("#export_standard_btn").show();
    updateBatchButtonsVisibility();
    
    // 应用实际数据样式
    $("#client_toolbar")
        .removeClass("toolbar-preview-data")
        .addClass("toolbar-actual-data");
    
    // 手动加载数据
    ClientLoadServerData();
    
    // 绑定所有事件
    ClientBindAllEvents();
}

// 手动加载服务器数据
function ClientLoadServerData() {
    $.ajax({
        url: CLIENT_MANAGE_CONFIG.client_list_url,
        type: "GET",
        success: function (data) {
            // 切换到实际数据模式
            client_PreviewMode = false;
            client_PreviewData = [];
            
            // 为每行数据添加 row_index
            if (data && Array.isArray(data)) {
                data.forEach((row, index) => {
                    if (!row.row_index) {
                        row.row_index = `server_${index}`;
                    }
                });
            }
            
            // 隐藏错误信息列，显示SSH相关列
            $("#client_table").bootstrapTable("hideColumn", "validation_errors");
            $("#client_table").bootstrapTable("showColumn", "ssh_actions");
            $("#client_table").bootstrapTable("showColumn", "connect_status");
            $("#client_table").bootstrapTable("showColumn", "lock_status");
            
            // 更新工具栏状态
            $("#toolbar_title").html(
                '<span class="cn-text"><i class="bi bi-database me-2"></i>实际数据</span><span class="en-text">Actual Data</span>'
            );
            $("#toolbar_subtitle").hide();
            $("#execute_import_btn").hide();
            $("#error_summary").hide();
            $("#batch_delete_btn").show();
            $("#export_standard_btn").show();
            
            // 应用实际数据样式
            $("#client_toolbar")
                .removeClass("toolbar-preview-data")
                .addClass("toolbar-actual-data");
            
            // 加载数据到表格
            $("#client_table").bootstrapTable("load", data);
            updateBatchButtonsVisibility();
        },
        error: function () {
            console.error("加载服务器数据失败");
            window.alerty.error("加载数据失败，请刷新页面重试");
        },
    });
}

// 绑定所有事件
function ClientBindAllEvents() {
    // 解析数据按钮
    $("#parse_data_btn").on("click", function () {
        ClientParseTextData();
    });
    
    // 执行导入按钮
    $("#execute_import_btn").on("click", function () {
        ClientExecuteImport();
    });
    
    // Excel 文件选择
    $("#excel_file_btn").on("click", function () {
        $("#excel_file_input").click();
    });
    
    $("#excel_file_input").on("change", function (e) {
        const file = e.target.files[0];
        if (file) {
            ClientImportExcel(file);
        }
    });
    
    // 下载模板
    $("#download_template_btn").on("click", function () {
        ClientDownloadTemplate();
    });
    
    // 导出标准数据
    $("#export_standard_btn").on("click", function () {
        ClientExportStandard();
    });
    
    // 批量删除
    $("#batch_delete_btn").on("click", function () {
        ClientBatchDelete();
    });
    
    // 批量 SSH 操作
    $("#batch_check_connect_btn").on("click", function () {
        ClientBatchSshAction('check_connect');
    });
    
    $("#batch_lock_btn").on("click", function () {
        ClientBatchSshAction('lock');
    });
    
    $("#batch_unlock_btn").on("click", function () {
        ClientBatchSshAction('unlock');
    });
    
    // 表格选择变化
    $("#client_table").on("check.bs.table uncheck.bs.table check-all.bs.table uncheck-all.bs.table", function () {
        updateBatchButtonsVisibility();
    });
    
    // 绑定表格点击事件（支持双击删除）
    BindClientTableEvents();
}

// 更新批量按钮可见性
function updateBatchButtonsVisibility() {
    const selected = $("#client_table").bootstrapTable("getSelections");
    const hasSelection = selected && selected.length > 0;
    const hasSshConfig = selected && selected.some(row => row.ssh_user && row.ssh_user.trim() !== '');
    
    $("#batch_delete_btn").toggle(hasSelection);
    $("#batch_check_connect_btn").toggle(hasSshConfig);
    $("#batch_lock_btn").toggle(hasSshConfig);
    $("#batch_unlock_btn").toggle(hasSshConfig);
}

// 检查比赛状态
function ClientCheckContestStatus() {
    if (CLIENT_MANAGE_CONFIG.contest_status == 2) {
        window.alerty.error("比赛已结束，不允许操作 / Contest ended, operation not allowed");
        return false;
    }
    return true;
}

// 解析文本数据
function ClientParseTextData() {
    if (!ClientCheckContestStatus()) return;
    
    const clientDescription = $("#client_description").val().trim();
    
    if (!clientDescription) {
        window.alerty.error("请输入客户端描述 / Please enter client description");
        return;
    }
    
    // 清理旧数据
    client_PreviewData = [];
    client_PreviewMode = true;
    
    const lines = clientDescription.split("\n");
    const clientData = [];
    
    lines.forEach((line, index) => {
        line = line.trim();
        if (!line) return;
        
        const parts = line.split("\t");
            const clientInfo = {
                row_index: `text_${index}`,
                contest_id: CLIENT_MANAGE_CONFIG.contest_id,
                team_id_bind: parts[0] || "",
                ip_bind: parts[1] || "",
                ssh_user: parts[2] || "",
                ssh_pass: parts[3] || "",
                ssh_rsa: parts[4] || "",
                ssh_port: parts[5] || "22",
                validation_errors: [],
            };
        
        // 验证数据
        const validationResult = ClientValidateData(clientInfo, index + 1);
        clientInfo.validation_errors = validationResult.errors;
        clientInfo.validation_warnings = validationResult.warnings || [];
        
        clientData.push(clientInfo);
    });
    
    if (clientData.length === 0) {
        window.alerty.error("没有有效的客户端数据 / No valid client data found");
        return;
    }
    
    ClientShowPreview(clientData);
}

// 验证IP地址格式（简单验证）
function isValidIpAddress(ip) {
    if (!ip || typeof ip !== 'string') return false;
    // 简单的IP地址格式验证（IPv4）
    const ipv4Regex = /^(\d{1,3}\.){3}\d{1,3}$/;
    return ipv4Regex.test(ip);
}

// 验证客户端数据
function ClientValidateData(clientInfo, rowNumber) {
    const errors = [];
    const warnings = [];
    
    // 验证 team_id_bind（队伍ID，不是状态量）
    const teamIdBind = (clientInfo.team_id_bind || "").trim();
    if (!teamIdBind) {
        errors.push(`第${rowNumber}行: 队伍号不能为空 / Row ${rowNumber}: team_id_bind is required`);
    } else {
        // team_id_bind 不应该是IP地址格式（防止字段混淆）
        if (isValidIpAddress(teamIdBind)) {
            errors.push(`第${rowNumber}行: 队伍号不能是IP地址格式，请检查数据列顺序是否正确 / Row ${rowNumber}: team_id_bind cannot be an IP address format, please check if the column order is correct`);
        }
    }
    
    // 验证 ip_bind（IP地址）
    const ipBind = (clientInfo.ip_bind || "").trim();
    if (!ipBind) {
        errors.push(`第${rowNumber}行: IP地址不能为空 / Row ${rowNumber}: ip_bind is required`);
    }
    
    // 验证 SSH 配置
    // SSH相关信息允许全为空
    // 仅当提供了SSH账号（用户名）的时候，才验证密码、密钥方面是否存在
    const sshUser = (clientInfo.ssh_user || "").trim();
    const sshPass = (clientInfo.ssh_pass || "").trim();
    const sshRsa = (clientInfo.ssh_rsa || "").trim();
    const sshPort = (clientInfo.ssh_port || "22").trim();
    
    // 如果SSH用户名为空，则通过验证（允许SSH信息全为空）
    if (sshUser === "") {
        // 通过验证
    } else {
        // ssh_user 不应该是IP地址格式（防止字段混淆）
        if (isValidIpAddress(sshUser)) {
            errors.push(`第${rowNumber}行: SSH用户名不能是IP地址格式，请检查数据列顺序是否正确 / Row ${rowNumber}: SSH user cannot be an IP address format, please check if the column order is correct`);
        }
        
        // 如果提供了SSH用户名，则需要验证port必填
        if (sshPort === "") {
            errors.push(`第${rowNumber}行: 提供SSH用户名时，SSH端口为必填项 / Row ${rowNumber}: SSH port is required when SSH user is provided`);
        }
        
        // 如果提供了SSH用户名，pass 和 rsa 至少需要有一个
        if (sshPass === "" && sshRsa === "") {
            errors.push(`第${rowNumber}行: 提供SSH用户名时，SSH密码或RSA密钥至少需要提供一个 / Row ${rowNumber}: SSH password or RSA key is required when SSH user is provided`);
        }
    }
    
    return {
        valid: errors.length === 0,
        errors: errors,
        warnings: warnings,
    };
}

// 显示预览
function ClientShowPreview(clientData) {
    client_PreviewData = clientData;
    client_PreviewMode = true;
    
    // 显示错误信息列
    $("#client_table").bootstrapTable("showColumn", "validation_errors");
    // 隐藏SSH操作列（预览模式下不需要）
    $("#client_table").bootstrapTable("hideColumn", "ssh_actions");
    $("#client_table").bootstrapTable("hideColumn", "connect_status");
    $("#client_table").bootstrapTable("hideColumn", "lock_status");
    
    // 更新工具栏
    $("#toolbar_title").html(
        '<span class="cn-text"><i class="bi bi-table me-2"></i>数据预览</span><span class="en-text">Data Preview</span>'
    );
    $("#toolbar_subtitle")
        .html('请检查数据后执行导入<span class="en-text">Please review data before importing</span>')
        .show();
    $("#execute_import_btn").show();
    $("#batch_delete_btn").hide();
    $("#batch_check_connect_btn").hide();
    $("#batch_lock_btn").hide();
    $("#batch_unlock_btn").hide();
    $("#export_standard_btn").hide();
    
    // 应用预览样式
    $("#client_toolbar")
        .removeClass("toolbar-actual-data")
        .addClass("toolbar-preview-data");
    
    // 计算错误和警告统计
    const errorRows = clientData.filter(
        (client) => client.validation_errors && client.validation_errors.length > 0
    );
    const warningRows = clientData.filter(
        (client) => client.validation_warnings && client.validation_warnings.length > 0
    );
    
    // 显示错误和警告统计
    showErrorWarningSummary(errorRows, warningRows);
    
    // 加载数据到表格
    $("#client_table").bootstrapTable("load", clientData);
    
    // 显示解析完成提示
    const totalRows = clientData.length;
    const errorCount = errorRows.length;
    const warningCount = warningRows.length;
    
    if (errorCount > 0) {
        window.alerty.warning(
            "数据解析完成",
            `成功解析 ${totalRows} 行数据，发现 ${errorCount} 行有错误${warningCount > 0 ? `，${warningCount} 行有警告` : ''}。请检查错误后执行导入。`
        );
    } else if (warningCount > 0) {
        window.alerty.warning(
            "数据解析完成",
            `成功解析 ${totalRows} 行数据，发现 ${warningCount} 行有警告。可以执行导入。`
        );
    } else {
        window.alerty.success(
            "数据解析完成",
            `成功解析 ${totalRows} 行数据，数据格式正确，可以执行导入。`
        );
    }
}

// 执行导入
function ClientExecuteImport() {
    if (!ClientCheckContestStatus()) return;
    
    if (client_PreviewData.length === 0) {
        window.alerty.error("没有可导入的数据 / No data to import");
        return;
    }
    
    // 检查是否有错误
    const hasErrors = client_PreviewData.some(row => 
        row.validation_errors && row.validation_errors.length > 0
    );
    
    if (hasErrors) {
        window.alerty.error("请先修复数据错误 / Please fix data errors first");
        return;
    }
    
    // 准备数据
    const clientList = client_PreviewData.map(row => ({
        contest_id: row.contest_id || CLIENT_MANAGE_CONFIG.contest_id,
        team_id_bind: row.team_id_bind || "",
        ip_bind: row.ip_bind || "",
        ssh_user: row.ssh_user || "",
        ssh_pass: row.ssh_pass || "",
        ssh_rsa: row.ssh_rsa || "",
        ssh_port: row.ssh_port || "22",
    }));
    
    $.ajax({
        url: CLIENT_MANAGE_CONFIG.client_save_url,
        type: "POST",
        data: {
            client_list: JSON.stringify(clientList)
        },
        success: function (ret) {
            if (ret.code == 1) {
                window.alerty.success(ret.msg);
                // 重置预览模式
                client_PreviewMode = false;
                client_PreviewData = [];
                $("#client_description").val("");
                $("#excel_file_input").val("");
                // 重新加载数据（会切换到实际数据模式）
                ClientLoadServerData();
            } else {
                window.alerty.error(ret.msg);
            }
        },
        error: function () {
            window.alerty.error("提交失败，请重试");
        },
    });
}

// 删除客户端
function ClientDelete(clientId) {
    if (!ClientCheckContestStatus()) return;
    
    if (!confirm("确定要删除这个客户端吗？ / Are you sure to delete this client?")) {
        return;
    }
    
    $.ajax({
        url: CLIENT_MANAGE_CONFIG.client_del_url,
        type: "POST",
        data: {
            client_id: clientId
        },
        success: function (ret) {
            if (ret.code == 1) {
                window.alerty.success(ret.msg);
                ClientLoadServerData();
            } else {
                window.alerty.error(ret.msg);
            }
        },
        error: function () {
            window.alerty.error("删除失败，请重试");
        },
    });
}

// 批量删除
function ClientBatchDelete() {
    if (!ClientCheckContestStatus()) return;
    
    const selected = $("#client_table").bootstrapTable("getSelections");
    if (!selected || selected.length === 0) {
        window.alerty.warning("请先选择要删除的客户端 / Please select clients to delete");
        return;
    }
    
    if (!confirm(`确定要删除选中的 ${selected.length} 个客户端吗？ / Are you sure to delete ${selected.length} selected clients?`)) {
        return;
    }
    
    const deletePromises = selected.map(row => {
        return new Promise((resolve, reject) => {
            $.ajax({
                url: CLIENT_MANAGE_CONFIG.client_del_url,
                type: "POST",
                data: {
                    client_id: row.client_id
                },
                success: function (ret) {
                    if (ret.code == 1) {
                        resolve({ success: true, row: row });
                    } else {
                        resolve({ success: false, row: row, error: ret.msg });
                    }
                },
                error: function () {
                    resolve({ success: false, row: row, error: "Network error" });
                },
            });
        });
    });
    
    Promise.all(deletePromises).then(results => {
        const successCount = results.filter(r => r.success).length;
        const failCount = results.filter(r => !r.success).length;
        const failRows = results.filter(r => !r.success).map(r => r.row.client_id || r.row.ip_bind);
        
        let message = `成功删除 ${successCount} 个客户端 / Successfully deleted ${successCount} clients`;
        if (failCount > 0) {
            message += `\n失败 ${failCount} 个 / Failed ${failCount}`;
            if (failRows.length > 0) {
                message += `\n失败的ID: ${failRows.join(", ")}`;
            }
        }
        
        window.alerty.alert(message);
        ClientLoadServerData();
    });
}

// 批量 SSH 操作
function ClientBatchSshAction(action) {
    if (!ClientCheckContestStatus()) return;
    
    const selected = $("#client_table").bootstrapTable("getSelections");
    if (!selected || selected.length === 0) {
        window.alerty.warning("请先选择要操作的客户端 / Please select clients to operate");
        return;
    }
    
    // 过滤出有 SSH 配置的客户端
    const sshClients = selected.filter(row => row.ssh_user && row.ssh_user.trim() !== '');
    if (sshClients.length === 0) {
        window.alerty.warning("选中的客户端没有 SSH 配置 / Selected clients have no SSH config");
        return;
    }
    
    const clientIds = sshClients.map(row => row.client_id);
    
    $.ajax({
        url: CLIENT_MANAGE_CONFIG.client_ssh_url,
        type: "POST",
        data: {
            action: action,
            client_ids: clientIds
        },
        success: function (ret) {
            if (ret.code == 1) {
                window.alerty.success(ret.msg);
                // 延迟刷新数据，等待 Python 处理
                setTimeout(() => {
                    ClientLoadServerData();
                }, 2000);
            } else {
                window.alerty.error(ret.msg);
            }
        },
        error: function () {
            window.alerty.error("操作失败，请重试");
        },
    });
}

// SSH 操作（单行）
function ClientSshAction(clientId, action) {
    if (!ClientCheckContestStatus()) return;
    
    $.ajax({
        url: CLIENT_MANAGE_CONFIG.client_ssh_url,
        type: "POST",
        data: {
            action: action,
            client_ids: [clientId]
        },
        success: function (ret) {
            if (ret.code == 1) {
                window.alerty.success(ret.msg);
                setTimeout(() => {
                    ClientLoadServerData();
                }, 2000);
            } else {
                window.alerty.error(ret.msg);
            }
        },
        error: function () {
            window.alerty.error("操作失败，请重试");
        },
    });
}

// Excel 导入
async function ClientImportExcel(file) {
    if (!ClientCheckContestStatus()) return;
    
    try {
        const workbook = new ExcelJS.Workbook();
        const buffer = await file.arrayBuffer();
        await workbook.xlsx.load(buffer);
        
        const worksheet = workbook.getWorksheet(1);
        if (!worksheet) {
            throw new Error("Excel文件中没有找到工作表");
        }
        
        const clientData = [];
        const errors = [];
        
        worksheet.eachRow((row, rowNumber) => {
            if (rowNumber === 1) return; // 跳过表头
            
            const values = row.values;
            
            // 检查是否为样例行（最后一列包含"样例"或"Example"）
            const lastColumnValue = ClientExtractCellValue(values[values.length - 1]) || "";
            if (
                lastColumnValue.includes("样例") ||
                lastColumnValue.includes("Example")
            ) {
                return; // 跳过样例行
            }
            
            // ExcelJS的row.values数组，索引0通常是空或undefined
            // 格式：队伍号(1), IP地址(2), SSH用户(3), SSH密码(4), SSH RSA(5), SSH端口(6), 样例(7)
            // 所以：values[1]=队伍号, values[2]=IP地址, values[3]=SSH用户, values[4]=SSH密码, values[5]=SSH RSA, values[6]=SSH端口
            
            const clientInfo = {
                row_index: `excel_${rowNumber}`,
                contest_id: CLIENT_MANAGE_CONFIG.contest_id,
                team_id_bind: ClientExtractCellValue(values[1]) || "",
                ip_bind: ClientExtractCellValue(values[2]) || "",
                ssh_user: ClientExtractCellValue(values[3]) || "",
                ssh_pass: ClientExtractCellValue(values[4]) || "",
                ssh_rsa: ClientExtractCellValue(values[5]) || "",
                ssh_port: ClientExtractCellValue(values[6]) || "22",
                validation_errors: [],
            };
            
            const validationResult = ClientValidateData(clientInfo, rowNumber);
            clientInfo.validation_errors = validationResult.errors;
            clientInfo.validation_warnings = validationResult.warnings || [];
            
            clientData.push(clientInfo);
        });
        
        if (clientData.length === 0) {
            window.alerty.error("没有有效的客户端数据 / No valid client data found");
            return;
        }
        
        ClientShowPreview(clientData);
    } catch (error) {
        console.error("导入Excel失败:", error);
        window.alerty.error("导入Excel失败: " + error.message);
    }
}

// 提取单元格值
function ClientExtractCellValue(cellValue) {
    if (cellValue === null || cellValue === undefined) return "";
    if (typeof cellValue === "object" && cellValue.text !== undefined) {
        return cellValue.text.toString().trim();
    }
    return cellValue.toString().trim();
}

// 下载模板
async function ClientDownloadTemplate() {
    try {
        const workbook = new ExcelJS.Workbook();
        const worksheet = workbook.addWorksheet("客户端信息");
        
        // 设置列定义（包含样例列）
        const clientHeaders = [
            { header: "队伍号", header_en: "Team ID", key: "team_id_bind", width: 15 },
            { header: "IP地址", header_en: "IP Address", key: "ip_bind", width: 15 },
            { header: "SSH用户", header_en: "SSH User", key: "ssh_user", width: 15 },
            { header: "SSH密码", header_en: "SSH Password", key: "ssh_pass", width: 15 },
            { header: "SSH RSA", header_en: "SSH RSA", key: "ssh_rsa", width: 30 },
            { header: "SSH端口", header_en: "SSH Port", key: "ssh_port", width: 10 },
            { header: "", header_en: "", key: "sample", width: 15 }, // 样例列，无表头
        ];
        
        worksheet.columns = clientHeaders.map((h) => ({
            key: h.key,
            width: h.width,
        }));
        
        // 添加中英双语表头（样例列使用空字符串）
        const headerRow = worksheet.addRow(
            clientHeaders.map((h) =>
                h.key === "sample" ? "" : `${h.header}\n${h.header_en}`
            )
        );
        
        // 设置表头样式
        headerRow.height = 40;
        headerRow.eachCell((cell, colNumber) => {
            cell.alignment = {
                vertical: "middle",
                horizontal: "center",
                wrapText: true,
            };
            cell.font = { bold: true, size: 11 };
            cell.fill = {
                type: "pattern",
                pattern: "solid",
                fgColor: { argb: "FFE6F3FF" },
            };
            cell.border = {
                top: { style: "thin" },
                left: { style: "thin" },
                bottom: { style: "thin" },
                right: { style: "thin" },
            };
        });
        
        // 添加样例数据（各种兼容格式）
        const sampleData = [
            {
                team_id_bind: "team001",
                ip_bind: "192.168.1.100",
                ssh_user: "admin",
                ssh_pass: "password123",
                ssh_rsa: "",
                ssh_port: "22",
                sample: "样例/Example",
            },
            {
                team_id_bind: "team002",
                ip_bind: "192.168.1.101",
                ssh_user: "root",
                ssh_pass: "",
                ssh_rsa: "-----BEGIN RSA PRIVATE KEY-----\n...\n-----END RSA PRIVATE KEY-----",
                ssh_port: "2222",
                sample: "样例/Example",
            },
            {
                team_id_bind: "team003",
                ip_bind: "10.0.0.50",
                ssh_user: "",
                ssh_pass: "",
                ssh_rsa: "",
                ssh_port: "",
                sample: "样例/Example",
            },
        ];
        
        // 添加样例行（包含样例列内容）
        sampleData.forEach((row, index) => {
            const excelRow = worksheet.addRow(row);
            excelRow.eachCell((cell, colNumber) => {
                cell.fill = {
                    type: "pattern",
                    pattern: "solid",
                    fgColor: { argb: "FFF0F0F0" },
                };
                cell.font = { color: { argb: "FF808080" } };
                cell.border = {
                    top: { style: "thin" },
                    left: { style: "thin" },
                    bottom: { style: "thin" },
                    right: { style: "thin" },
                };
            });
        });
        
        // 生成 Excel 文件
        const buffer = await workbook.xlsx.writeBuffer();
        const blob = new Blob([buffer], {
            type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = url;
        a.download = "客户端信息模板.xlsx";
        a.click();
        window.URL.revokeObjectURL(url);
        
        window.alerty.success("模板下载成功 / Template downloaded successfully");
    } catch (error) {
        console.error("下载模板失败:", error);
        window.alerty.error("下载模板失败 / Template download failed");
    }
}

// 导出标准数据
function ClientExportStandard() {
    try {
        const clientData = $("#client_table").bootstrapTable("getData", {
            includeHiddenRows: true,
        });
        if (!clientData || clientData.length === 0) {
            window.alerty.error("没有数据可导出 / No data to export");
            return;
        }
        
        ClientExportToExcel(clientData, "标准客户端数据");
        
        window.alerty.success(
            "标准数据导出成功 / Standard data exported successfully"
        );
    } catch (error) {
        console.error("导出标准数据失败:", error);
        window.alerty.error("导出标准数据失败 / Standard data export failed");
    }
}

// 导出到Excel
async function ClientExportToExcel(clientData, filename) {
    try {
        const workbook = new ExcelJS.Workbook();
        const worksheet = workbook.addWorksheet("客户端数据");
        
        // 设置列定义（不包含ID列）
        const clientHeaders = [
            { header: "队伍号", header_en: "Team ID", key: "team_id_bind", width: 15 },
            { header: "IP地址", header_en: "IP Address", key: "ip_bind", width: 15 },
            { header: "SSH用户", header_en: "SSH User", key: "ssh_user", width: 15 },
            { header: "SSH密码", header_en: "SSH Password", key: "ssh_pass", width: 15 },
            { header: "SSH RSA", header_en: "SSH RSA", key: "ssh_rsa", width: 30 },
            { header: "SSH端口", header_en: "SSH Port", key: "ssh_port", width: 10 },
        ];
        
        worksheet.columns = clientHeaders.map((h) => ({
            key: h.key,
            width: h.width,
        }));
        
        // 添加中英双语表头
        const headerRow = worksheet.addRow(
            clientHeaders.map((h) => `${h.header}\n${h.header_en}`)
        );
        
        // 设置表头样式
        headerRow.height = 40;
        headerRow.font = { bold: true };
        headerRow.eachCell((cell) => {
            cell.alignment = {
                vertical: "middle",
                horizontal: "center",
                wrapText: true,
            };
            cell.border = {
                top: { style: "thin" },
                left: { style: "thin" },
                bottom: { style: "thin" },
                right: { style: "thin" },
            };
        });
        
        // 添加数据（不包含ID列）
        clientData.forEach((client) => {
            const excelRow = worksheet.addRow([
                client.team_id_bind || "",
                client.ip_bind || "",
                client.ssh_user || "",
                client.ssh_pass || "",
                client.ssh_rsa || "",
                client.ssh_port || "22",
            ]);
            
            excelRow.eachCell((cell) => {
                cell.border = {
                    top: { style: "thin" },
                    left: { style: "thin" },
                    bottom: { style: "thin" },
                    right: { style: "thin" },
                };
            });
        });
        
        // 生成文件名
        const contestTitle = CLIENT_MANAGE_CONFIG.contest_title || "Contest";
        const contestId = CLIENT_MANAGE_CONFIG.contest_id || "";
        const date = new Date().toISOString().slice(0, 10).replace(/-/g, "");
        const exportFilename = `${filename}_${contestId}_${date}`;
        
        // 导出文件
        const buffer = await workbook.xlsx.writeBuffer();
        const blob = new Blob([buffer], {
            type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = url;
        a.download = `${exportFilename}.xlsx`;
        a.click();
        window.URL.revokeObjectURL(url);
    } catch (error) {
        console.error("导出Excel失败:", error);
        throw error;
    }
}

// 格式化函数
function FormatterConnectStatus(value, row, index) {
    if (!row.last_connect_time) {
        return '<span class="connect-status-icon connect-status-unknown" title="未知 / Unknown"></span>未知';
    }
    
    const connectTime = new Date(row.last_connect_time);
    const now = new Date();
    const diffMinutes = (now - connectTime) / (1000 * 60);
    
    let statusClass = "connect-status-old";
    let statusText = "超过2小时";
    let title = `最后连接: ${row.last_connect_time}`;
    
    if (diffMinutes <= 10) {
        statusClass = "connect-status-10min";
        statusText = "10分钟内";
    } else if (diffMinutes <= 30) {
        statusClass = "connect-status-30min";
        statusText = "30分钟内";
    } else if (diffMinutes <= 120) {
        statusClass = "connect-status-2hour";
        statusText = "2小时内";
    }
    
    return `<span class="connect-status-icon ${statusClass}" title="${title}"></span>${statusText}`;
}

function FormatterLockStatus(value, row, index) {
    const lockStatus = row.lock_status || "unlock";
    const lockTime = row.lock_time || "";
    
    if (lockStatus === "lock") {
        return `<span class="badge bg-danger">已锁屏</span>${lockTime ? `<br><small>${lockTime}</small>` : ""}`;
    } else {
        return `<span class="badge bg-success">未锁屏</span>${lockTime ? `<br><small>${lockTime}</small>` : ""}`;
    }
}

function FormatterSshActions(value, row, index) {
    if (!row.ssh_user || row.ssh_user.trim() === "") {
        return '<span class="text-muted">无SSH配置</span>';
    }
    
    const buttons = [
        `<button class="btn btn-sm btn-primary" onclick="ClientSshAction(${row.client_id}, 'check_connect')" title="确认连接 / Check Connection">
            <i class="bi bi-wifi"></i>
        </button>`,
        `<button class="btn btn-sm btn-warning" onclick="ClientSshAction(${row.client_id}, 'lock')" title="锁屏 / Lock">
            <i class="bi bi-lock"></i>
        </button>`,
        `<button class="btn btn-sm btn-info" onclick="ClientSshAction(${row.client_id}, 'unlock')" title="解锁 / Unlock">
            <i class="bi bi-unlock"></i>
        </button>`,
    ];
    
    return buttons.join(" ");
}

function FormatterModify(value, row, index) {
    return `<button class="btn btn-sm btn-primary" onclick="ClientModify(${row.client_id})" title="修改 / Modify">
        <i class="bi bi-pencil"></i>
    </button>`;
}

function FormatterDel(value, row, index) {
    return `<button class="btn btn-sm btn-danger" onclick="ClientDelete(${row.client_id})" title="删除 / Delete">
        <i class="bi bi-trash"></i>
    </button>`;
}

// 绑定表格事件（支持双击删除等）
function BindClientTableEvents() {
    const table = $("#client_table");
    
    // 防止重复绑定
    table.off("click-cell.bs.table");
    table.off("dbl-click-cell.bs.table");
    
    table.on("click-cell.bs.table", function (e, field, td, row) {
        if (field === "delete") {
            if (!client_delete_infoed) {
                client_delete_infoed = true;
            }
        } else if (field === "modify") {
            // 处理修改按钮点击
            const tableData = $("#client_table").bootstrapTable("getData");
            const rowIndex = tableData.findIndex((r) => r.row_index === row.row_index);
            if (rowIndex >= 0) {
                ClientModify(row.client_id);
            }
        }
    });
    
    table.on("dbl-click-cell.bs.table", function (e, field, td, row) {
        if (field === "delete") {
            if (client_PreviewMode) {
                // 预览模式：从待提交数据中删除
                ClientDeleteFromPreview(row);
            } else {
                // 实际数据模式：调用后端删除接口
                ClientDelete(row.client_id);
            }
        }
    });
}

// 从预览数据中删除
function ClientDeleteFromPreview(row) {
    if (!client_PreviewData) return;

    // 从预览数据中移除
    const index = client_PreviewData.findIndex(r => r.row_index === row.row_index);
    if (index >= 0) {
        client_PreviewData.splice(index, 1);

        // 重新计算错误和警告统计
        const errorRows = client_PreviewData.filter(
            (client) => client.validation_errors && client.validation_errors.length > 0
        );
        const warningRows = client_PreviewData.filter(
            (client) => client.validation_warnings && client.validation_warnings.length > 0
        );

        // 重新加载表格数据
        $("#client_table").bootstrapTable("load", client_PreviewData);

        // 显示错误和警告统计
        showErrorWarningSummary(errorRows, warningRows);

        window.alerty.success("已从预览数据中删除 / Removed from preview data");
    }
}

// 显示错误和警告统计的通用函数（与 teamgen.js 保持一致）
function showErrorWarningSummary(
    errorRows,
    warningRows,
    executeImportBtnId = "#execute_import_btn",
    errorSummaryId = "#error_summary"
) {
    if (errorRows.length > 0 || warningRows.length > 0) {
        let alertClass = "alert-warning";
        let alertIcon = "bi-exclamation-triangle";
        let alertText = "";

        if (errorRows.length > 0) {
            alertClass = "alert-danger";
            alertIcon = "bi-exclamation-triangle-fill";
            alertText = `发现 ${errorRows.length} 行数据有错误，请检查后执行导入 / Found ${errorRows.length} rows with errors, please check before importing`;
            // 有错误时禁用执行导入按钮
            $(executeImportBtnId).prop("disabled", true).addClass("disabled");
        } else if (warningRows.length > 0) {
            alertText = `发现 ${warningRows.length} 行数据有警告，请检查后执行导入 / Found ${warningRows.length} rows with warnings, please check before importing`;
            // 只有警告时不禁用按钮
            $(executeImportBtnId).prop("disabled", false).removeClass("disabled");
        }

        $(errorSummaryId)
            .html(
                `
            <div class="alert ${alertClass}">
                <i class="bi ${alertIcon} me-2"></i>
                ${alertText}
            </div>
        `
            )
            .show();
    } else {
        $(errorSummaryId).hide();
        // 没有错误和警告时启用按钮
        $(executeImportBtnId).prop("disabled", false).removeClass("disabled");
    }
}

// 格式化验证错误
function FormatterValidationErrors(value, row, index) {
    if (!row.validation_errors || row.validation_errors.length === 0) {
        return '<span class="text-success"><i class="bi bi-check-circle"></i></span>';
    }
    
    const errorCount = row.validation_errors.length;
    const errorText = row.validation_errors.join("; ");
    
    return `<span class="text-danger" title="${errorText}" style="cursor: pointer;">
        <i class="bi bi-exclamation-triangle"></i> ${errorCount}
    </span>`;
}

// 修改客户端（占位函数，后续实现）
function ClientModify(clientId) {
    if (!ClientCheckContestStatus()) return;
    
    // TODO: 实现修改功能
    window.alerty.info("修改功能开发中 / Modify function under development");
}

