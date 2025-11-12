{__NOLAYOUT__}
{include file="../../csgoj/view/public/global_head" /}
<div class="container">
    <div class="page-header">
        <div class="bg-white border border-primary border-opacity-25 rounded-3 px-3 py-2 mb-3 shadow-sm">
            <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <h1 class="page-title bilingual-inline mb-0">
                        题目导出/导入文件管理<span class="en-text">Problem Export/Import File Manager</span>
                    </h1>
                </div>
            </div>
        </div>
    </div>
    <script type="text/javascript">
        var re_checkfile = /^[0-9a-zA-Z-_\.\(\)]+\.(zip)$/;
    </script>
    {include file="filemanager/js_upload" /}
</div>
<script type="text/javascript">
    const upload_table_problem_import = $('#upload_table');
    // id_input 在 js_upload.php，获取变量（itemId、fire_url在 upload_page.js）
    const item_name_problem_import = $('#item_input').val();
    upload_table_problem_import.on('click-cell.bs.table', function(e, field, td, row) {
        if (field === 'file_type') {
            var button = $('#' + $(td).attr('id'));
            alerty.confirm({
                
                title: "导入题目",
                message: "导入题目将添加新题目，重复导入题目文件可能会导致重复题目。\n\n确定要导入吗？",
                message_en: "Import problem will add new problems, reimport problem file may make duplicated problems.\n\nAre you sure to import?",
                okText: '确定',
                cancelText: '取消',
                width: 'lg',
                callback: function() {
                    button.attr('disabled', true);
                    var button_text = button.text();
                    button.text('Running...');
                    $.get(
                        fire_url, {
                            'filename': row.file_name,
                            'item': item_name_problem_import
                        },
                        function(ret) {
                            if (ret.code === 1) {
                                data = ret.data;
                                
                                // 构建格式化的消息（中文）
                                var msg_cn = '\n';
                                msg_cn += '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n';
                                msg_cn += '导入结果\n';
                                msg_cn += '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n';
                                
                                // 构建格式化的消息（英文）
                                var msg_en = '\n';
                                msg_en += '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n';
                                msg_en += 'Import Result\n';
                                msg_en += '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n';
                                
                                // 成功数量
                                msg_cn += '✅ ' + data.addedList.length + ' 个题目已成功导入\n\n';
                                msg_en += '✅ ' + data.addedList.length + ' problems successfully imported\n\n';
                                
                                // 失败数量
                                if (data.failedList.length > 0) {
                                    msg_cn += '❌ ' + data.failedList.length + ' 个题目导入失败\n\n';
                                    msg_en += '❌ ' + data.failedList.length + ' problems failed to import\n\n';
                                } else {
                                    msg_cn += '✅ 所有题目导入成功\n\n';
                                    msg_en += '✅ All problems imported successfully\n\n';
                                }
                                
                                // 权限问题提示
                                var permissionWarnings_cn = [];
                                var permissionWarnings_en = [];
                                if (data['judgeDataFolderPermission'] === false) {
                                    permissionWarnings_cn.push('⚠️ 数据文件夹权限被拒绝');
                                    permissionWarnings_en.push('⚠️ Data Folder Permission Denied');
                                }
                                if (data['attachFolderPermission'] === false) {
                                    permissionWarnings_cn.push('⚠️ 附件文件夹权限被拒绝');
                                    permissionWarnings_en.push('⚠️ Attach Folder Permission Denied');
                                }
                                
                                if (permissionWarnings_cn.length > 0) {
                                    msg_cn += '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n';
                                    msg_cn += '⚠️ 权限警告\n';
                                    msg_cn += '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n';
                                    permissionWarnings_cn.forEach(function(warning) {
                                        msg_cn += warning + '\n';
                                    });
                                    msg_cn += '\n';
                                    
                                    msg_en += '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n';
                                    msg_en += '⚠️ Permission Warning\n';
                                    msg_en += '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n';
                                    permissionWarnings_en.forEach(function(warning) {
                                        msg_en += warning + '\n';
                                    });
                                    msg_en += '\n';
                                }
                                
                                // 失败详情
                                if (data.failedList.length > 0) {
                                    msg_cn += '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n';
                                    msg_cn += '失败详情\n';
                                    msg_cn += '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n';
                                    data.failedList.forEach(function(failedItem) {
                                        msg_cn += '  • ' + failedItem + '\n';
                                    });
                                    
                                    msg_en += '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n';
                                    msg_en += 'Failed Details\n';
                                    msg_en += '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n';
                                    data.failedList.forEach(function(failedItem) {
                                        msg_en += '  • ' + failedItem + '\n';
                                    });
                                }

                                alerty.alert({
                                    message: (ret.msg || '导入完成') + msg_cn,
                                    message_en: (ret.msg || 'Import completed') + msg_en,
                                    width: 'lg',
                                    callback: function() {
                                        button_delay(button, 3, 'Import', 'Import');
                                        upload_table_problem_import.bootstrapTable('refresh');
                                    }
                                });
                            } else {
                                alerty.alert({
                                    message: ret.msg || '导入失败',
                                    message_en: ret.msg || 'Import failed',
                                    width: 'lg',
                                    callback: function() {
                                        button_delay(button, 3, 'Import', 'Import');
                                    }
                                });
                            }
                            return false;
                        }
                    );
                },
                callbackCancel: function() {
                    alerty.info('已取消导入', 'Import canceled');
                }
            });
        }
    });
</script>