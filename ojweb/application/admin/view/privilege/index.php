<div class="admin-page-header">
    <div class="admin-page-header-left">
        <div class="admin-page-header-icon">
            <i class="bi bi-shield-check"></i>
        </div>
        <h1 class="admin-page-header-title">
            <div class="admin-page-header-title-main">
                权限管理
            </div>
            <div class="admin-page-header-title-right">
                <span class="en-text">
                    Privilege Management
                </span>
            </div>
        </h1>
    </div>
</div>

<div class="container">
    <form id="privilege_add_form" method='post' action="__ADMIN__/privilege/privilege_add_ajax">
        <!-- 权限数据存储 -->
        <input type="hidden" id="privilege_data" value='{$allowAdmin|json_encode}'>
        
        <div class="row g-4">
            <!-- 用户ID输入区域 -->
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header bg-light">
                        <h4 class="mb-0">用户信息<span class="en-text text-muted fs-6">User Information</span></h4>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="user_id" class="form-label">用户ID<span class="en-text text-muted d-block">User ID</span></label>
                            <input type="text" class="form-control" name="user_id" placeholder="输入用户ID / Enter User ID">
                        </div>
                        
                        <!-- 提交按钮 -->
                        <div class="d-grid">
                            <button type="submit" id="submit_button" class="btn btn-primary">
                                <span><i class="bi bi-plus-circle me-2"></i>添加权限</span>
								<span class="en-text">Add Privilege</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 权限选择区域 -->
            <div class="col-lg-8">
                <div class="card h-100">
                    <div class="card-header bg-light">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="mb-0">权限类型<span class="en-text text-muted fs-6">Privilege Types</span></h4>
                            <!-- 快速选择按钮 -->
                            <div class="btn-group privilege-quick-buttons" role="group">
                                <button type="button" class="btn btn-outline-primary btn-sm" id="check_priv_all" title="全选">
                                    <span><i class="bi bi-check-all"></i> 全选</span><span class="en-text">All</span>
                                </button>
                                <button type="button" class="btn btn-outline-success btn-sm" id="check_priv_non" title="清空">
                                    <span><i class="bi bi-x-square"></i> 清空</span><span class="en-text">None</span>
                                </button>
                                <button type="button" class="btn btn-outline-warning btn-sm" id="check_priv_rev" title="反选">
                                    <span><i class="bi bi-arrow-repeat"></i> 反选</span><span class="en-text">Reverse</span>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body" id="privilege_selection_area">
                        <!-- 权限选择区域将由JavaScript动态生成 -->
                    </div>
                </div>
            </div>
        </div>
    </form>
	
	<div id="privilege_toolbar" class="table-toolbar">
		<div class="d-flex align-items-center gap-2" role="form">
			<button id="privilege_refresh" type="submit" class="btn btn-outline-secondary toolbar-btn" title="刷新 (Refresh)">
				<i class="bi bi-arrow-clockwise"></i>
			</button>
			<button id="privilege_clear" type="submit" class="btn btn-outline-secondary toolbar-btn" title="清空筛选条件 (Clear)">
				<i class="bi bi-eraser"></i>
			</button>
			<div class="toolbar-group">
				<span class="toolbar-label-inline"><span>权限类型</span><span class="toolbar-label en-text">Privilege Type</span></span>
				<select id="privilege_type_filter" class="form-select toolbar-select" style="width: 180px;">
					<option value="">全部权限</option>
				</select>
			</div>
			<div class="toolbar-group">
				<span class="toolbar-label-inline"><span>搜索</span><span class="toolbar-label en-text">Search</span></span>
				<input id="privilege_search_input" name="search" class="form-control toolbar-input privilege_filter" type="text" placeholder="用户ID/权限" style="width: 200px;">
			</div>
		</div>
	</div>
	
	<table
			class="bootstraptable_refresh_local"
		id="privilege_edit_table"
		data-toggle="table"
		data-url="__ADMIN__/privilege/privilege_list_ajax"
		data-pagination="true"
		data-page-list="[15,50,100]"
		data-page-size="15"
		data-side-pagination="client"
		data-method="get"
		data-search="false"
		data-pagination-v-align="both"
		data-pagination-h-align="left"
		data-pagination-detail-h-align="right"
		data-toolbar="#privilege_toolbar"
		data-sortable="false"
		data-unique-id="user_id"
		data-classes="table table-hover table-striped"
	>
		<thead>
		<tr>
			<th data-field="serial" data-align="center" data-valign="middle"  data-width="55" data-formatter="FormatterIndex">#<span class="en-text">#</span></th>
			<th data-field="user_id" data-align="left" data-valign="middle"  data-width="80" data-formatter="FormatterUserId">用户ID<span class="en-text">User ID</span></th>
			<th data-field="privilege" data-align="left" data-valign="middle"  data-width="80" data-formatter="FormatterPrivilege">权限<span class="en-text">Privilege</span></th>
			<th data-field="delete" data-align="center" data-valign="middle"  data-width="80" data-formatter="FormatterDelete">删除<span class="en-text">Delete</span></th>
		</tr>
		</thead>
	</table>
</div>
{js href="__STATIC__/csgoj/general_formatter.js" /}
{js href="__JS__/refresh_in_table.js" /}
<script type="text/javascript">
	var table = $('#privilege_edit_table');
	
	// Formatter函数
	function FormatterUserId(value, row, index, field) {
		return `<a href='/csgoj/user/userinfo?user_id=${value}' target='_blank'>${value}</a>`;
	}
	
	function FormatterPrivilege(value, row, index, field) {
		// 从权限数据中获取显示信息
		const privilegeData = JSON.parse($('#privilege_data').val());
		if (privilegeData[value]) {
			const privilege = privilegeData[value];
			return `${privilege.cn}<span class="en-text text-muted">${privilege.en}</span>`;
		}
		return value || '-';
	}
	
	function FormatterDelete(value, row, index, field) {
		if (row.can_delete) {
			return `<button class='delete_button btn btn-outline-danger btn-sm' title="双击删除 / Double Click to Delete">
						<i class="bi bi-trash"></i>
					</button>`;
		}
		return '-';
	}
	
	// 初始化权限管理工具栏（客户端分页版本）
	initBootstrapTableClientToolbar({
		tableId: 'privilege_edit_table',
		prefix: 'privilege',
		filterSelectors: ['privilege_type_filter'],
		searchInputId: 'privilege_search_input',
		searchFields: {
			user_id: 'user_id',
			privilege: 'privilege'
		}
	});
    
	function AddPrivilege(form, enforce=0) {
		$(form).ajaxSubmit({
            data: {
                'enforce': enforce
            },
			success: function(ret) {
                if (ret.code == 1) {
                    alerty.success(ret['msg']);
                    button_delay($('#submit_button'), 3, '添加权限');
                    table.bootstrapTable('refresh');
                }
                else {
                    if(ret.data == 'nouser') {
                        alerty.confirm({
                            message: "用户不存在，是否强制添加？",
                            message_en: "No such user. Force to add?",
                            callback: function() {
                                AddPrivilege(form, 1);
							},
							callbackCancel: function() {
                                alerty.message("操作已取消。", "Nothing Happened.");
                            }
                        });
                    } else {
                        alerty.error(ret.msg);
                    }                    
                }
                return false;
            }
        });
	}

	// 动态生成权限选择界面
	function generatePrivilegeSelection() {
		const privilegeData = JSON.parse($('#privilege_data').val());
		const selectionArea = $('#privilege_selection_area');
		
		let html = '<div class="row g-2">';
		
		// 遍历所有权限，只显示允许的权限
		Object.keys(privilegeData).forEach(privilegeKey => {
			const privilege = privilegeData[privilegeKey];
			// 只显示允许的权限
			if (privilege.flg_allow === true) {
				html += `
					<div class="col-md-6">
						<div class="form-check form-check-sm">
							<input class="form-check-input privilege_check" type="checkbox" value="${privilegeKey}" id="privilege_${privilegeKey}" name="privilege[]">
							<label class="form-check-label" for="privilege_${privilegeKey}">
								${privilege.cn}<span class="en-text text-muted">${privilege.en}</span>
							</label>
						</div>
					</div>
				`;
			}
		});
		
		html += '</div>';
		selectionArea.html(html);
	}

	// 动态生成权限类型筛选器
	function generatePrivilegeTypeFilter() {
		const privilegeData = JSON.parse($('#privilege_data').val());
		const filterSelect = $('#privilege_type_filter');
		
		// 清空现有选项（保留"全部权限"选项）
		filterSelect.find('option:not(:first)').remove();
		
		// 添加权限类型选项 - 只显示允许的权限
		Object.keys(privilegeData).forEach(privilegeKey => {
			const privilege = privilegeData[privilegeKey];
			// 只显示允许的权限
			if (privilege.flg_allow === true) {
				filterSelect.append(`
					<option value="${privilegeKey}">${privilege.cn} - ${privilege.en}</option>
				`);
			}
		});
	}

	$(document).ready(function()
	{
		// 生成权限选择界面
		generatePrivilegeSelection();
		
		// 生成权限类型筛选器
		generatePrivilegeTypeFilter();
		
		// 权限类型筛选器事件 - 使用标准筛选
		$('#privilege_type_filter').change(function() {
			const selectedType = $(this).val();
			if (selectedType === '') {
				// 清空筛选
				table.bootstrapTable('filterBy', {});
			} else {
				// 直接筛选privilege字段
				table.bootstrapTable('filterBy', {
					privilege: selectedType
				});
			}
		});
		
		// 快速选择按钮事件
		$('#check_priv_all').click(function() {
			$('.privilege_check').each(function() {
				this.checked = true;
			});
		});

		$('#check_priv_non').click(function() {
			$('.privilege_check').each(function() {
				this.checked = false;
			});
		});

		$('#check_priv_rev').click(function() {
			$('.privilege_check').each(function() {
				this.checked = !this.checked;
			});
		});
		
		// 使用 form_validate_tip.js 进行表单验证
		FormValidationTip.initFormValidation('#privilege_add_form', {
			user_id: {
				rules: {
					required: true
				}
			}
		}, function(form) {
			// 提交前对用户ID输入框进行trim处理
			const $userIdInput = $(form).find('input[name="user_id"]');
			$userIdInput.val($userIdInput.val().trim());
			AddPrivilege(form, 0);
		});
	});
	table.on('click-cell.bs.table', function(e, field, td, row) {
		if (field == 'delete') {
		if(row.can_delete) {
			// 确认删除
			alerty.confirm({
				message: '确认删除权限？',
				message_en: 'Confirm to delete privilege?',
				callback: function() {
					$.post(
						'__ADMIN__/privilege/privilege_delete_ajax',
						{
							'user_id': row['user_id'],
							'privilege[]': row['privilege'],
							'enforce': 1
						},
						function (ret) {
							if (ret['code'] == 1) {
								alerty.success(ret.msg);
								table.bootstrapTable('removeByUniqueId', row.user_id);
							}
							else {
								alerty.error(ret['msg']);
							}
							return false;
						}
					);
				},
				callbackCancle: function() {
					alerty.message('操作已取消', 'Operation cancelled');
				}
			});
		}
		}
	});
	
</script>

<style type="text/css">
    /* 权限管理页面样式优化 - 参考problem_rejudge_page.php */
    
    /* 权限复选框样式增强 - 双列布局优化 */
    .form-check {
        margin-bottom: 0.5rem;
        padding-left: 1.5rem;
    }

    .form-check-sm {
        margin-bottom: 0.25rem;
        padding-left: 1.25rem;
    }

    .form-check-input {
        margin-top: 0.125rem;
        margin-left: -1.5rem;
    }

    .form-check-sm .form-check-input {
        margin-left: -1.25rem;
    }

    .form-check-label {
        cursor: pointer;
        font-weight: 500;
        transition: all 0.15s ease-in-out;
    }

    .form-check-sm .form-check-label {
        font-size: 0.9rem;
        line-height: 1.4;
    }

    .form-check-sm .en-text {
        font-size: 0.8rem;
    }

    .form-check-input:checked+.form-check-label {
        font-weight: 600;
    }

    /* 快速选择按钮组样式 */
    .btn-group .btn {
        border-radius: 0.375rem;
        margin-right: 0.25rem;
        transition: all 0.15s ease-in-out;
    }

    .btn-group .btn:last-child {
        margin-right: 0;
    }

    .btn-group .btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    /* 卡片样式增强 */
    .card {
        border: 1px solid rgba(0, 0, 0, 0.125);
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        transition: all 0.15s ease-in-out;
    }

    .card:hover {
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    }

    .card-header {
        background-color: #f8f9fa;
        border-bottom: 1px solid rgba(0, 0, 0, 0.125);
        font-weight: 600;
    }

    /* 响应式优化 */
    @media (max-width: 768px) {
        .btn-group {
            flex-direction: column;
        }

        .btn-group .btn {
            margin-right: 0;
            margin-bottom: 0.25rem;
        }

        .btn-group .btn:last-child {
            margin-bottom: 0;
        }

        .form-check {
            padding-left: 1.25rem;
        }

        .form-check-input {
            margin-left: -1.25rem;
        }

        /* 双列布局在小屏幕上变为单列 */
        .row.g-2 .col-md-6 {
            flex: 0 0 100%;
            max-width: 100%;
        }
    }

    /* 表格样式优化 */
    .table-hover tbody tr:hover {
        background-color: rgba(0, 0, 0, 0.075);
    }

    /* 删除按钮样式 */
    .btn-outline-danger.btn-sm {
        border-radius: 0.375rem;
        transition: all 0.15s ease-in-out;
    }

    .btn-outline-danger.btn-sm:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(220, 53, 69, 0.3);
    }

    /* 权限快速选择按钮样式 - 更小尺寸但保持字体大小 */
    .btn-group.privilege-quick-buttons .btn {
        line-height: 1.2;
		height: 40px;
    }

    .btn-group.privilege-quick-buttons .btn i {
        margin-right: 0.25rem;
    }
</style>