
<div class="container-fluid px-3">
    <!-- 页面头部 -->
    <div class="page-header">
        <div class="bg-white border border-primary border-opacity-25 rounded-3 px-3 py-2 mb-3 shadow-sm">
            <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <h1 class="page-title bilingual-inline">
                        队伍照片管理<span class="en-text">Team Photo Management</span>
                    </h1>
                    <div class="d-flex align-items-center gap-2">
                        <span class="text-primary fs-5 px-3 py-2 text-decoration-none fw-bold">
                            <i class="bi bi-hash me-1"></i><?php echo $contest['contest_id']; ?>
                        </span>
                        <div class="vr"></div>
                        <span class="text-dark fs-6 fw-medium" style="max-width: 30vw; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; padding: 0.5rem 1rem; background: rgba(0,123,255,0.1); border-radius: 0.5rem;" title="<?php echo htmlspecialchars($contest['title']); ?>">
                            <i class="bi bi-trophy me-1"></i><?php echo htmlspecialchars($contest['title']); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 提示信息 -->
    <div class="alert alert-warning alert-dismissible fade show mb-3" role="alert">
        <div class="d-flex align-items-start">
            <i class="bi bi-exclamation-triangle me-2 mt-1"></i>
            <div class="flex-grow-1">
                <p class="mb-1">批量上传文件名需与队伍ID一一对应，图片建议长:宽=3:2。如更换图片请务必清理缓存。</p>
                <p class="mb-0"><span class="en-text">Batch upload file names must correspond one-to-one with team IDs. Image ratio recommended 3:2. Please clear cache if replacing images.</span></p>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>

    <!-- 工具栏 -->
    <div id="team_image_toolbar" class="table-toolbar">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <button id="team_image_batch_btn" class="btn btn-primary bilingual-inline toolbar-btn" type="button" style="height: 40px; margin: auto;">
                <span class="cn-text"><i class="bi bi-cloud-upload me-1"></i>批量上传</span><span class="en-text">Batch Upload</span>
            </button>
            <input class="d-none" type="file" id="team_image_batch" multiple accept="image/jpg,image/png,image/jpeg,image/bmp">
        </div>
    </div>

    <!-- 队伍图片表格 -->
    <table
        id="team_image_table"
        class="bootstraptable_refresh_local"
        data-toggle="table"
        data-buttons-align="left"
        data-sort-name="team_id"
        data-sort-order="asc"
        data-pagination="false"
        data-method="get"
        data-search="true"
        data-search-align="right"
        data-show-refresh="false"
        data-show-columns="false"
        data-classes="table table-hover table-striped"
        data-toolbar="#team_image_toolbar"
        data-unique-id="team_id"
        style="width: 100% !important; min-width: 100%;"
    >
        <thead class="table-light">
            <tr>
                <th data-field="idx" data-align="center" data-valign="middle" data-sortable="true" data-width="60" data-formatter="IndexFormatter">
                    <span class="bilingual-label">序号<span class="en-text">Idx</span></span>
                </th>
                <th data-field="team_id" data-align="center" data-valign="middle" data-sortable="true" data-width="80">
                    <span class="bilingual-label">队伍ID<span class="en-text">Team ID</span></span>
                </th>
                <th data-field="school" data-align="left" data-valign="middle" data-sortable="true" data-width="200">
                    <span class="bilingual-label">学校<span class="en-text">School</span></span>
                </th>
                <th data-field="name" data-align="left" data-valign="middle" data-sortable="true" data-width="200">
                    <span class="bilingual-label">队名<span class="en-text">Team Name</span></span>
                </th>
                <th data-field="tmember" data-align="center" data-valign="middle" data-sortable="true" data-width="150">
                    <span class="bilingual-label">成员<span class="en-text">Members</span></span>
                </th>
                <th data-field="coach" data-align="center" data-valign="middle" data-sortable="true" data-width="100">
                    <span class="bilingual-label">教练<span class="en-text">Coach</span></span>
                </th>
                <th data-field="room" data-align="center" data-valign="middle" data-sortable="true" data-width="100">
                    <span class="bilingual-label">房间/区<span class="en-text">Room/Region</span></span>
                </th>
                <th data-field="tkind" data-align="center" data-valign="middle" data-sortable="true" data-width="80" data-formatter="FormatterTkind">
                    <span class="bilingual-label">类型<span class="en-text">Type</span></span>
                </th>
                <th data-field="team_photo" data-align="center" data-valign="middle" data-formatter="TeamPhotoFormatter" data-width="250">
                    <span class="bilingual-label">照片操作<span class="en-text">Photo Operations</span></span>
                </th>
            </tr>
        </thead>
    </table>
</div>

{js href="__JS__/refresh_in_table.js" /}
{js href="__JS__/overlay.js" /}

<script type="text/javascript">
// 后端变量配置（使用 PHP 原生方式）
var RANK_TEAM_CONFIG = {
    cid: <?php echo intval($contest['contest_id']); ?>,
    contest_attach: <?php echo json_encode($contest['attach']); ?>,
    upload_url: 'team_image_upload_ajax',
    delete_url: 'team_image_del_ajax',
    list_url: 'team_image_list_ajax',
    contest_data_url: 'contest_data_ajax'
};

// 全局变量
let team_image_table = $('#team_image_table');
let team_list = [];
let team_map = {};
let team_photo_map = {};
let flag_ready_cnt = 0;
let batch_file_list = [];
let batch_error_list = [];
let batch_ith = 0;
let cnt_success = 0;

// 表格格式化函数
function IndexFormatter(value, row, index) {
    return index + 1;
}

function FormatterTkind(value, row, index) {
    let v = value === null ? 0 : value;
    let icon = "balloon";
    let title_tip = "常规队";
    let title_tip_en = "Regular Team";
    let txtcolor = "text-success";
    
    if (v == 1) {
        icon = "balloon-heart";
        title_tip = "女队";
        title_tip_en = "Girls Team";
        txtcolor = "text-danger";
    } else if (v == 2) {
        icon = "star";
        title_tip = "打星队";
        title_tip_en = "Star Team";
        txtcolor = "text-primary";
    }
    
    return `<i class="${txtcolor} bi bi-${icon}" title="${title_tip} ${title_tip_en}"></i>`;
}

function TeamImageButtonHtml(team_id) {
    const imageUrl = `/upload/contest_attach/${RANK_TEAM_CONFIG.contest_attach}/team_photo/${team_id}.jpg`;
    return `
        <div class="d-flex flex-column gap-1 align-items-center">
            <div class="btn-group btn-group-sm" role="group">
                <button id="team_btn_preview_${team_id}" class="btn btn-outline-primary btn-sm" 
                        dclass="team_image_preview" 
                        team_id="${team_id}" 
                        url="${imageUrl}"
                        title="预览图片 Preview Image">
                    <i class="bi bi-eye"></i>
                </button>
                <button id="team_btn_upload_${team_id}" class="btn btn-outline-secondary btn-sm" 
                        dclass="team_image_reupload" 
                        team_id="${team_id}" 
                        title="重新上传 Re-upload">
                    <i class="bi bi-arrow-clockwise"></i>
                </button>
                <button id="team_btn_del_${team_id}" class="btn btn-outline-danger btn-sm" 
                        dclass="team_image_del" 
                        team_id="${team_id}" 
                        title="双击删除 Double click to delete">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
            <input class="form-control form-control-sm d-none team_image_upload_input" 
                   type="file" 
                   accept="image/jpg,image/png,image/jpeg,image/bmp" 
                   dclass="team_image_upload_input" 
                   name="${team_id}.jpg" 
                   id="team_image_input_${team_id}" 
                   team_id="${team_id}">
        </div>
    `;
}

function TeamUploadButtonHtml(team_id) {
    return `
        <div class="d-flex flex-column gap-1 align-items-center">
            <input class="form-control form-control-sm team_image_upload_input" 
                   type="file" 
                   accept="image/jpg,image/png,image/jpeg,image/bmp" 
                   dclass="team_image_upload_input" 
                   name="${team_id}.jpg" 
                   id="team_image_input_${team_id}" 
                   team_id="${team_id}"
                   style="width: 120px;">
        </div>
    `;
}

function TeamPhotoFormatter(value, row, index) {
    let info_dom = '';
    if (row.team_id in team_photo_map) {
        info_dom = TeamImageButtonHtml(row.team_id);
    } else {
        info_dom = TeamUploadButtonHtml(row.team_id);
    }
    return `<div id="team_image_container_${row.team_id}">${info_dom}</div>`;
}

// 数据加载完成检查
function LoadReady() {
    flag_ready_cnt++;
    if (flag_ready_cnt >= 2) {
        // 转换 team 数据格式为对象
        const team_list_obj = team_list.map(team => {
            if (Array.isArray(team)) {
                return {
                    contest_id: team[0] || '',
                    team_id: team[1] || '',
                    name: team[2] || '',
                    name_en: team[3] || '',
                    coach: team[4] || '',
                    tmember: team[5] || '',
                    school: team[6] || '',
                    region: team[7] || '',
                    tkind: team[8] || 0,
                    room: team[9] || '',
                    privilege: team[10] || '',
                    team_global_code: team[11] || ''
                };
            }
            return team;
        });
        
        team_image_table.bootstrapTable('load', team_list_obj);
        
        // 刷新 tooltip
        if (window.autoTooltips) {
            window.autoTooltips.refresh();
        }
    }
}

// 初始化数据
$(document).ready(function() {
    flag_ready_cnt = 0;
    
    // 加载队伍数据
    $.get(RANK_TEAM_CONFIG.contest_data_url + '?without_solution=1&cid=' + RANK_TEAM_CONFIG.cid, function(ret) {
        if (ret.code == 1) {
            // 过滤掉管理员、气球员、打印员
            team_list = ret.data.team.filter((a) => {
                if (Array.isArray(a)) {
                    // 数组格式：[0]contest_id, [1]team_id, [2]name, ..., [10]privilege
                    const privilege = a[10] || '';
                    return !(privilege in {'admin': true, 'balloon': true, 'printer': true});
                } else {
                    // 对象格式
                    const privilege = a.privilege || '';
                    return !(privilege in {'admin': true, 'balloon': true, 'printer': true});
                }
            });
            
            // 构建 team_map
            team_map = {};
            for (let i in team_list) {
                const team = team_list[i];
                const team_id = Array.isArray(team) ? team[1] : team.team_id;
                if (team_id) {
                    team_map[team_id] = team;
                }
            }
            
            LoadReady();
        } else {
            alerty.error(ret.msg || '加载队伍数据失败', ret.msg_en || 'Failed to load team data');
        }
    }).fail(function(xhr, status, error) {
        alerty.error('网络错误，无法加载队伍数据', 'Network error, unable to load team data');
        console.error('Failed to load team data:', error);
    });
    
    // 加载图片列表
    $.get(RANK_TEAM_CONFIG.list_url + '?cid=' + RANK_TEAM_CONFIG.cid, function(ret) {
        if (ret.code == 1) {
            team_photo_map = {};
            for (let i in ret.data) {
                const file_name = ret.data[i].file_name || ret.data[i];
                const team_id = file_name.replace('.jpg', '').replace('.JPG', '');
                team_photo_map[team_id] = ret.data[i];
            }
            LoadReady();
        } else {
            alerty.error(ret.msg || '加载图片列表失败', ret.msg_en || 'Failed to load image list');
        }
    }).fail(function() {
        alerty.error('网络错误，无法加载图片列表', 'Network error, unable to load image list');
    });
});

// 文件处理函数
function FileProcess(file, team_id, loading_show = true) {
    if (!file || !file.type) {
        alerty.error('文件无效', 'Invalid file');
        return;
    }
    
    if (!file.type.startsWith("image/")) {
        alerty.alert({
            message: '请选择图片文件！',
            message_en: 'Please select an image file!'
        });
        return;
    }
    
    if (loading_show) {
        showOverlay({
            message: '上传中...',
            message_en: 'Uploading...',
            type: 'text'
        });
    }
    
    let reader = new FileReader();
    reader.addEventListener("load", function(e) {
        let data = e.target.result;
        let image = new Image();
        image.addEventListener("load", function(e) {
            let width = e.target.width;
            let height = e.target.height;
            let targetWidth = 1080;
            let ratio = width > targetWidth ? targetWidth / width : 1;
            let targetHeight = parseInt(Math.min(720, height * ratio));
            let mapping_height = targetHeight / ratio;
            let canvas = document.createElement("canvas");
            canvas.width = width * ratio;
            canvas.height = targetHeight;
            let context = canvas.getContext("2d");
            let sy = mapping_height < height ? Math.floor((height - mapping_height) * 0.5) : 0;
            context.drawImage(image, 0, sy, width, targetHeight / ratio, 0, 0, width * ratio, targetHeight);
            let jpgData = canvas.toDataURL("image/jpeg");
            
            $.ajax({
                url: RANK_TEAM_CONFIG.upload_url,
                type: 'post',
                data: {
                    'team_photo': jpgData,
                    'cid': RANK_TEAM_CONFIG.cid,
                    'team_id': team_id
                },
                success: function(ret) {
                    if (ret.code == 1) {
                        // 更新图片映射
                        team_photo_map[team_id] = { file_name: team_id + '.jpg' };
                        
                        // 刷新表格中该行的照片列，触发 formatter 重新渲染
                        team_image_table.bootstrapTable('updateCellByUniqueId', {
                            id: team_id,
                            field: 'team_photo',
                            value: '',  // 值不重要，formatter 会根据 team_photo_map 渲染
                            reinit: false
                        });
                        
                        cnt_success++;
                        
                        if (loading_show) {
                            hideOverlay();
                            alerty.success('队伍图片已更新', 'Team photo updated successfully');
                        }
                        
                        // 刷新 tooltip
                        if (window.autoTooltips) {
                            window.autoTooltips.refresh();
                        }
                    } else {
                        if (loading_show) {
                            hideOverlay();
                            alerty.error(ret.msg || '上传失败', ret.msg_en || 'Upload failed');
                        } else {
                            batch_error_list.push(`${file.name}: ${ret.msg || '上传失败'}`);
                        }
                    }
                    
                    // 只有批量上传时才继续处理下一个文件
                    if (!loading_show) {
                        batch_ith++;
                        BatchProcessIth();
                    }
                },
                error: function(xhr, status, error) {
                    const errorMsg = xhr.responseJSON?.msg || xhr.statusText || '网络错误';
                    if (loading_show) {
                        hideOverlay();
                        alerty.error(errorMsg, 'Network error');
                    } else {
                        batch_error_list.push(`${file.name}: ${errorMsg}`);
                        batch_ith++;
                        BatchProcessIth();
                    }
                }
            });
        });
        
        image.addEventListener("error", function() {
            if (loading_show) {
                hideOverlay();
                alerty.error('图片加载失败', 'Failed to load image');
            } else {
                batch_error_list.push(`${file.name}: 图片加载失败`);
                batch_ith++;
                BatchProcessIth();
            }
        });
        
        image.src = data;
    });
    
        reader.addEventListener("error", function() {
            if (loading_show) {
                hideOverlay();
                alerty.error('文件读取失败', 'Failed to read file');
            } else {
                batch_error_list.push(`${file.name}: 文件读取失败`);
                batch_ith++;
                BatchProcessIth();
            }
        });
    
    reader.readAsDataURL(file);
}

// 图片预览
function TeamImagePreview(btn_obj) {
    const team_id = btn_obj.getAttribute('team_id');
    let image_url = btn_obj.getAttribute('url');
    const tparam = `?t=${new Date().getTime()}`; // 是否允许缓存图片
    
    // 确保 URL 是绝对路径
    if (image_url && !image_url.startsWith('http://') && !image_url.startsWith('https://') && !image_url.startsWith('/')) {
        image_url = '/' + image_url;
    }
    
    const full_url = image_url + tparam;
    const timestamp = Date.now();
    const imgDivId = 'img_preview_div_' + timestamp;
    const imgId = 'img_preview_img_' + timestamp;
    
    // 转义 URL 中的特殊字符用于 HTML 属性
    const escapedUrl = full_url.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    
    alerty.alert({
        title: `队伍图片预览：${team_id}<span class="en-text">Team Photo Preview: ${team_id}</span>`,
        message: `<div id="${imgDivId}" style="width:100%;max-width:720px;height:480px;overflow:hidden;margin:auto;text-align:center;background:#f8f9fa;display:flex;align-items:center;justify-content:center;">
            <img id="${imgId}" style="max-width:100%;max-height:100%;height:auto;object-fit:contain;" 
                 src="${escapedUrl}" 
                 alt="Team Photo">
        </div>`,
        width: '900px',
        callback: function() {
            // 等待模态框完全显示后再绑定错误处理
            setTimeout(function() {
                const img = document.getElementById(imgId);
                const imgDiv = document.getElementById(imgDivId);
                
                if (img && imgDiv) {
                    // 绑定错误处理
                    img.onerror = function() {
                        console.error('Image load error:', full_url);
                        imgDiv.innerHTML = `
                            <p class="text-danger">图片加载失败<span class="en-text">Image load failed</span></p>
                            <p style="word-break:break-all;font-size:0.8rem;">${image_url}</p>
                        `;
                    };
                    
                    // 如果图片已经加载失败，触发错误处理
                    if (img.complete && img.naturalHeight === 0) {
                        img.onerror();
                    }
                }
            }, 100);
        }
    });
}

// 删除图片
function TeamImageDel(btn_obj) {
    const team_id = btn_obj.getAttribute('team_id');
    
    alerty.confirm({
        message: `确定要删除队伍 ${team_id} 的图片吗？`,
        message_en: `Are you sure you want to delete the photo for team ${team_id}?`,
        callback: function() {
            $.post(RANK_TEAM_CONFIG.delete_url, {
                'cid': RANK_TEAM_CONFIG.cid,
                'team_id': team_id
            }, function(ret) {
                if (ret.code == 1) {
                    // 从映射中移除
                    delete team_photo_map[team_id];
                    
                    // 刷新表格中该行的照片列，触发 formatter 重新渲染
                    team_image_table.bootstrapTable('updateCellByUniqueId', {
                        id: team_id,
                        field: 'team_photo',
                        value: '',  // 值不重要，formatter 会根据 team_photo_map 渲染
                        reinit: false
                    });
                    
                    alerty.success(`队伍 ${team_id} 图片已删除`, `Team ${team_id} photo deleted successfully`);
                    
                    // 刷新 tooltip
                    if (window.autoTooltips) {
                        window.autoTooltips.refresh();
                    }
                } else {
                    alerty.error(ret.msg || '删除失败', ret.msg_en || 'Delete failed');
                }
            }).fail(function() {
                alerty.error('网络错误，删除失败', 'Network error, delete failed');
            });
        }
    });
}

// 批量处理
function BatchProcessIth() {
    if (batch_ith >= batch_file_list.length) {
        hideOverlay();
        
        if (batch_error_list.length > 0) {
            const success_count = cnt_success;
            const fail_count = batch_file_list.length - cnt_success;
            alerty.alert({
                title: '上传情况<span class="en-text">Upload Summary</span>',
                message: `成功: ${success_count}，失败: ${fail_count}<span class="en-text">Success: ${success_count}, Failed: ${fail_count}</span><br/><br/>${batch_error_list.join('<br/>')}`,
                width: '600px'
            });
        } else if (cnt_success > 0) {
            alerty.success(`批量上传完成，共成功 ${cnt_success} 个`, `Batch upload completed, ${cnt_success} files uploaded successfully`);
            // 重新加载图片列表并刷新表格
            $.get(RANK_TEAM_CONFIG.list_url + '?cid=' + RANK_TEAM_CONFIG.cid, function(ret) {
                if (ret.code == 1) {
                    team_photo_map = {};
                    for (let i in ret.data) {
                        const file_name = ret.data[i].file_name || ret.data[i];
                        const team_id = file_name.replace('.jpg', '').replace('.JPG', '');
                        team_photo_map[team_id] = ret.data[i];
                    }
                    // 刷新表格
                    team_image_table.bootstrapTable('refresh');
                }
            });
        }
        
        // 重置状态
        $('#team_image_batch').val('');
        $('#team_image_batch_btn').html('<span class="cn-text"><i class="bi bi-cloud-upload me-1"></i>批量上传</span><span class="en-text">Batch Upload</span>');
        batch_file_list = [];
        batch_error_list = [];
        batch_ith = 0;
        cnt_success = 0;
        
        return;
    }
    
    const file = batch_file_list[batch_ith];
    const filename = file.name;
    const team_id = filename.substring(0, filename.lastIndexOf('.'));
    const progress = parseInt((batch_ith / batch_file_list.length) * 100);
    const percentage = parseInt((batch_ith * 100 / batch_file_list.length));
    
    updateOverlay({
        message: `${percentage}%. 正在处理：${filename}`,
        message_en: `Processing: ${filename}`,
        type: 'text'
    }, progress);
    
    if (!(team_id in team_map)) {
        batch_error_list.push(`${filename}: 文件名不在队伍ID中<span class="en-text">File name not in team IDs</span>`);
        batch_ith++;
        BatchProcessIth();
    } else {
        FileProcess(file, team_id, false);
    }
}


// 使用事件委托处理所有事件
$(document).ready(function() {
    // 单文件上传
    $(document).on('change', '.team_image_upload_input', function(e) {
        const team_id = $(this).attr('team_id');
        const file = this.files[0];
        
        if (file) {
            // 不在这里显示 overlay，让 FileProcess 统一处理
            batch_file_list = [];
            batch_error_list = [];
            batch_ith = 0;
            cnt_success = 0;
            FileProcess(file, team_id, true);
        }
        
        // 清空 input，允许重复上传同一文件
        this.value = '';
    });
    
    // 预览按钮
    $(document).on('click', '[dclass="team_image_preview"]', function(e) {
        e.preventDefault();
        TeamImagePreview(this);
    });
    
    // 重新上传按钮
    $(document).on('click', '[dclass="team_image_reupload"]', function(e) {
        e.preventDefault();
        const team_id = $(this).attr('team_id');
        $(`#team_image_input_${team_id}`).click();
    });
    
    // 删除按钮（双击）
    $(document).on('dblclick', '[dclass="team_image_del"]', function(e) {
        e.preventDefault();
        TeamImageDel(this);
    });
    
    // 批量上传按钮点击处理 - 只触发文件选择
    $('#team_image_batch_btn').on('click', function(e) {
        e.preventDefault();
        // 直接触发文件选择
        $('#team_image_batch').click();
    });
    
    // 批量上传文件选择 - 选择后自动开始上传
    $('#team_image_batch').on('change', function() {
        const files = this.files;
        if (files && files.length > 0) {
            // 自动开始批量上传
            batch_file_list = Array.from(files);
            batch_error_list = [];
            batch_ith = 0;
            cnt_success = 0;
            
            // 更新按钮文字显示文件数量
            $('#team_image_batch_btn').html(`<span class="cn-text"><i class="bi bi-cloud-upload me-1"></i>批量上传 (${files.length})</span><span class="en-text">Batch Upload (${files.length})</span>`);
            
            // 开始处理
            showOverlay({
                message: '开始处理...',
                message_en: 'Starting...',
                type: 'text'
            });
            BatchProcessIth();
        } else {
            // 恢复默认文字
            $('#team_image_batch_btn').html('<span class="cn-text"><i class="bi bi-cloud-upload me-1"></i>批量上传</span><span class="en-text">Batch Upload</span>');
        }
    });
});
</script>

<style>
/* 表格操作列样式 */
.team_image_container {
    min-width: 180px;
}

/* 照片操作列中的 badge 样式 */
#team_image_table .badge {
    font-size: 0.75rem;
    padding: 0.35em 0.65em;
    font-weight: 500;
}

#team_image_table .badge .en-text {
    font-size: 0.85em;
    margin-left: 4px;
}

/* 文件输入样式 - 仅在未上传时显示 */
.team_image_upload_input {
    cursor: pointer;
    font-size: 0.875rem;
}

.team_image_upload_input:not(.d-none) {
    border: 1px dashed #6c757d;
    border-radius: 0.375rem;
    padding: 0.375rem 0.5rem;
    transition: all 0.2s ease;
}

.team_image_upload_input:not(.d-none):hover {
    border-color: #0d6efd;
    background-color: rgba(13, 110, 253, 0.05);
}

/* 响应式设计 */
@media (max-width: 768px) {
    .table-toolbar .d-flex {
        flex-direction: column;
        align-items: stretch;
    }
    
    .input-group {
        max-width: 100% !important;
    }
    
    .btn-group {
        flex-direction: column;
    }
    
    .btn-group .btn {
        width: 100%;
        margin-bottom: 0.25rem;
    }
}
</style>
