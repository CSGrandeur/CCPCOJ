<div class="admin-page-header">
    <div class="admin-page-header-left">
        <div class="admin-page-header-icon">
            <i class="bi bi-download"></i>
        </div>
        <h1 class="admin-page-header-title">
            <div class="admin-page-header-title-main">
                题目导出
            </div>
            <div class="admin-page-header-title-right">
                <span class="en-text">Problem Export</span>
            </div>
        </h1>
    </div>
    <div class="admin-page-header-actions">
        <button type="button" 
                class="btn btn-success btn-sm" 
                id="attachfile"
                data-modal-url="__ADMIN__/problemexport/problem_export_filemanager?item=problemexport"
                data-modal-title="题目导入/导出文件管理 / Problem Import/Export File Manager">
            <span class="cn-text"><i class="bi bi-folder-check me-1"></i>
            已导出题目文件管理</span><span class="en-text">Exported Problem Files Manager</span>
        </button>
    </div>
</div>

<div class="container">
    <form id="problem_export_form" method='post' action="__ADMIN__/{$controller}/problem_export_ajax?item=problemexport">
        <div class="row g-4">
            <!-- 导出选项区域 -->
            <div class="col-lg-3">
                <div class="card h-100">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">
                            导出选项<span class="en-text text-muted fs-6">Export Options</span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- 附件文件开关 -->
                        <div class="mb-4">
                            <div class="mb-2">
                                <div class="bilingual-label fw-semibold">题目附件</div>
                                <div class="text-muted small en-text">Problem Attachments</div>
                            </div>
                            <div class="csg-switch csg-switch-md" style="width: 160px;">
                                <input type="checkbox" 
                                       id="attach_file_check" 
                                       name="attach_file_check" 
                                       class="csg-switch-input"
                                       data-csg-text-on="包含"
                                       data-csg-text-on-en="Include"
                                       data-csg-text-off="不包含"
                                       data-csg-text-off-en="Exclude">
                            </div>
                        </div>

                        <!-- 测试数据开关 -->
                        <div class="mb-4">
                            <div class="mb-2">
                                <div class="bilingual-label fw-semibold">评测数据</div>
                                <div class="text-muted small en-text">Test Data</div>
                            </div>
                            <div class="csg-switch csg-switch-md" style="width: 160px;">
                                <input type="checkbox" 
                                       id="test_data_check" 
                                       name="test_data_check" 
                                       class="csg-switch-input"
                                       data-csg-text-on="包含"
                                       data-csg-text-on-en="Include"
                                       data-csg-text-off="不包含"
                                       data-csg-text-off-en="Exclude">
                            </div>
                        </div>

                        <!-- 提交按钮 -->
                        <div class="mt-auto pt-3">
                            <button type="submit" id="submit_button" class="btn btn-primary bilingual-button w-100">
                                <span class="cn-text"><i class="bi bi-download me-2"></i>
                                导出</span><span class="en-text">Export</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 导出方式区域 -->
            <div class="col-lg-9">
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">
                            导出方式<span class="en-text text-muted fs-6">Export Methods</span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- 方式一：题目ID范围 -->
                        <div class="mb-4">
                            <label class="bilingual-label fw-bold mb-3">
                                <span class="cn-text"><i class="bi bi-1-circle me-2"></i>
                                方式一：题目ID范围</span><span class="en-text">Type 1: Problem ID Range</span>
                            </label>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="start_pid" class="form-label bilingual-label">
                                        起始题目ID<span class="en-text">Start Problem ID</span>
                                    </label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="start_pid" 
                                           placeholder="起始ID / Start..." 
                                           name="start_pid">
                                </div>
                                <div class="col-md-6">
                                    <label for="end_pid" class="form-label bilingual-label">
                                        结束题目ID<span class="en-text">End Problem ID</span>
                                    </label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="end_pid" 
                                           placeholder="结束ID（包含） / End Inclusive..." 
										   title="留空则导出单个题目 / Leave Empty to Export Single Problem"
                                           name="end_pid">
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <!-- 方式二：题目ID列表 -->
                        <div class="mb-4">
                            <label class="bilingual-label fw-bold mb-3">
                                <span class="cn-text"><i class="bi bi-2-circle me-2"></i>
                                方式二：题目ID列表</span><span class="en-text">Type 2: Problem ID List</span>
                            </label>
                            <!-- 题目输入组件容器 -->
                            <div id="problem_export_input"></div>
                            <!-- 隐藏输入框，用于表单提交 -->
                            <input type="hidden" id="pid_list" name="pid_list">
                        </div>

                        <hr class="my-4">

                        <!-- 方式三：比赛题目 -->
                        <div class="mb-4">
                            <label class="bilingual-label fw-bold mb-3">
                                <span class="cn-text"><i class="bi bi-3-circle me-2"></i>
                                方式三：比赛题目</span><span class="en-text">Type 3: Contest Problems</span>
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="ex_cid" 
                                   placeholder="比赛ID / A Contest ID" 
                                   name="ex_cid">
                            <div class="form-text">
                                导出指定比赛中的所有题目 / Export all problems in the specified contest
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

{include file="../../csgoj/view/public/base_csg_switch" /}
{include file="../../admin/view/contest/problem_selection" /}

<script type="text/javascript">
	var attach_file_check = $('#attach_file_check');
	var test_data_check = $('#test_data_check');
	var submit_button = $('#submit_button');
	var submit_button_texts = window.Bilingual ? window.Bilingual.getBilingualText(submit_button) : {chinese: '导出', english: 'Export'};
	var submit_button_text = submit_button_texts.chinese;
	var submit_button_en_text = submit_button_texts.english;

	$(document).ready(function()
	{
		// 初始化 csg-switch（csg-switch 会自动初始化，这里确保初始化）
		if (window.csgSwitch) {
			const attachSwitch = document.getElementById('attach_file_check');
			const testDataSwitch = document.getElementById('test_data_check');
			if (attachSwitch && !attachSwitch.dataset.csgInitialized) {
				window.csgSwitch.initSwitch(attachSwitch);
			}
			if (testDataSwitch && !testDataSwitch.dataset.csgInitialized) {
				window.csgSwitch.initSwitch(testDataSwitch);
			}
		}
		
		// 初始化题目输入组件（用于方式二：题目ID列表选择）
		if (typeof createProblemInput !== 'undefined') {
			const problemInput = createProblemInput('problem_export_input', {
				max: 30,  // 导出可以支持更多题目
				allowDuplicates: false,
				allowInvalid: false,
				showCount: true,
				showActions: true,
				onChange: function(csv, component) {
					// 同步到隐藏输入框，用于表单提交
					$('#pid_list').val(csv);
				}
			});
			
			// 设置题目选择器确认回调
			window.onProblemSelectionConfirm = function(problemIds) {
				problemInput.addProblems(problemIds);
			};
		}
		
		// 使用 FormValidationTip 进行表单验证
		if (typeof window.FormValidationTip === 'undefined') {
			console.error('FormValidationTip not loaded');
			return;
		}
		
		window.FormValidationTip.initCommonFormValidation('#problem_export_form', {
			start_pid: {
				rules: { 
					number: true,
					custom: [
						function(value, element) {
							if (!value || value.trim() === '') return true; // 非必填，如果为空则跳过
							const num = Number(value);
							return /^\d{1,10}$/.test(value) && num >= 1000;
						}
					]
				},
				messages: {
					number: window.FormValidationTip.createBilingualMessage("请输入有效的数字", "Please enter a valid number"),
					custom: window.FormValidationTip.createBilingualMessage("题目ID应该是1000以上的有限正整数", "Problem ID should be a limited Positive integer >= 1000")
				}
			},
			end_pid: {
				rules: { 
					number: true
				},
				messages: {
					number: window.FormValidationTip.createBilingualMessage("请输入有效的数字", "Please enter a valid number")
				}
			},
			pid_list: {
				rules: {
					custom: [
						function(value, element) {
							if (!value || value.trim() === '') return true; // 非必填
							return /^\d{1,10}(,\d{1,10}){0,20}$/.test(value.trim());
						}
					]
				},
				messages: {
					custom: window.FormValidationTip.createBilingualMessage("题目ID列表应该用逗号分隔，且为有限的正整数", "Problem IDs should be separated by ',' and be limited Positive Integers")
				}
			},
			ex_cid: {
				rules: {
					custom: [
						function(value, element) {
							if (!value || value.trim() === '') return true; // 非必填
							return /^\d{1,10}$/.test(value.trim());
						}
					]
				},
				messages: {
					custom: window.FormValidationTip.createBilingualMessage("应该只提交一个比赛ID，且为有限的正整数", "Should submit only one contest ID and should be a limited Positive Integer")
				}
			}
		}, function(form) {
			// 提交处理函数
			var start_pid = Number($('#start_pid').val()) || 0;
			var end_pid = Number($('#end_pid').val()) || 0;
			var pid_list = $('#pid_list').val().trim();
			var ex_cid = $('#ex_cid').val().trim();
			
			if(end_pid === 0) end_pid = start_pid;
			
			// 验证至少选择一种方式
			if(start_pid === 0 && pid_list === '' && ex_cid === '') {
				alerty.alert(
					"需要输入三种方式中的一种",
					"Need to input one of the three types"
				);
				return false;
			}
			
			if(start_pid !== 0) {
				if(!/^\d{1,10}$/.test(start_pid)) {
					alerty.alert(
						"题目ID应该是有限的正整数",
						"Problem ID should be a limited Positive integer"
					);
					return false;
				}
				
				if(end_pid - start_pid > 30) {
					alerty.alert(
						"建议不要一次性导出这么多题目（超过30个）",
						"You'd better not export so many problems once (more than 30)"
					);
					return false;
				}
				
				// 检查开关状态（csg-switch）
				const testDataChecked = test_data_check[0] ? test_data_check[0].checked : false;
				const attachFileChecked = attach_file_check[0] ? attach_file_check[0].checked : false;
				
				if(end_pid - start_pid > 30 && (testDataChecked || attachFileChecked)) {
					alerty.alert(
						"包含相关文件时，不能导出这么多题目",
						"Together with related files, you cannot export so many problems"
					);
					return false;
				}
				
				if(end_pid < start_pid) {
					alerty.alert(
						"结束题目ID应该大于起始题目ID",
						"End problem ID should be bigger than start problem ID"
					);
					return false;
				}
				
				SubmitExport(form);
			} else if(pid_list !== '') {
				// 验证题目ID列表格式（支持逗号或空格分隔）
				const pidArray = pid_list.split(/[,\s]+/).filter(p => p.trim() !== '');
				if(pidArray.length === 0) {
					alerty.alert(
						"请输入至少一个题目ID",
						"Please enter at least one Problem ID"
					);
					return false;
				}
				// 验证每个ID都是有效的正整数
				const invalidPids = pidArray.filter(pid => !/^\d{1,10}$/.test(pid.trim()));
				if(invalidPids.length > 0) {
					alerty.alert(
						`题目ID列表包含无效的ID：${invalidPids.join(', ')}`,
						`Problem ID list contains invalid IDs: ${invalidPids.join(', ')}`
					);
					return false;
				}
				if(pidArray.length > 1000) {
					alerty.alert(
						"一次最多导出1000个题目",
						"Maximum 1000 problems can be exported at once"
					);
					return false;
				}
				SubmitExport(form);
			} else if(ex_cid !== '') {
				if(!/^\d{1,10}$/.test(ex_cid)) {
					alerty.alert(
						"应该只提交一个比赛ID，且为有限的正整数",
						"Should submit only one contest ID and should be a limited Positive Integer"
					);
					return false;
				}
				SubmitExport(form);
			}
			
			return false;
		});
	});
	
	function SubmitExport(form)
	{
		// 检查开关状态（csg-switch）
		const testDataChecked = test_data_check[0] ? test_data_check[0].checked : false;
		const attachFileChecked = attach_file_check[0] ? attach_file_check[0].checked : false;
		
		if(testDataChecked || attachFileChecked) {
			alerty.confirm({
				message: "题目导出可能需要较长时间。" +
					"请确保所有数据不会太大（大约200MB是可接受的）<br/>" +
					"如果执行时间超过 <strong class='text-danger'>180秒</strong>，过程将会停止。<br/>" +
					"在这种情况下，您可以导出题目 <strong class='text-danger'>不包含测试数据</strong>，然后单独上传到其他OJ<br/>" +
					"您准备好了吗？",
				message_en: "Problem export may take a long time." +
					"Please make sure that all the data would be not too large (perhaps 200MB around is acceptable)<br/>" +
					"The process would stop if execute time exceed <strong class='text-danger'>180s</strong>.<br/>" +
					"In this case, you can export problem <strong class='text-danger'>WITHOUT test datas</strong> and upload them to the other OJ separately<br/>" +
					"Are you ready?",
				callback: function(){
					DoExport(form);
				},
				callbackCancel: function(){
					alerty.message('已取消', 'Canceled');
				}
			});
			return false;
		} else {
			DoExport(form);
		}
		return false;
	}
	
	function DoExport(form)
	{
		submit_button.attr('disabled', true);
		var button_text = submit_button.text();
		submit_button.html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>运行中...');
		$(form).ajaxSubmit({
			success: function(ret)
			{
				if(ret["code"] == 1)
				{
					alerty.alert({
						message: ret['msg'],
						callback: function(){
							// 闪烁提示按钮已更新（如果按钮存在）
							var attachBtn = $("#attachfile");
							if (attachBtn.length) {
								var tmpInterval = setInterval(function(){
									attachBtn.fadeOut(100).fadeIn(100);
								},200);
								setTimeout(function(){
									clearInterval(tmpInterval);
									attachBtn.fadeIn();
								}, 1000);
							}
						}
					});
				}
				else
				{
					alerty.alert(ret['msg']);
				}
				button_delay(submit_button, 3, submit_button_text, null, submit_button_en_text);
				return false;
			}
		});
	}
</script>