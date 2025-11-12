<?php $edit_mode = isset($problem); $copy_mode = isset($copy_mode) ? $copy_mode : false;?>
<link rel="stylesheet" type="text/css" href="__STATIC__/csgoj/oj_problem.css" />
<script type="text/javascript" src="__STATIC__/csgoj/oj_problem.js"></script>

<div class="admin-page-header">
    <div class="admin-page-header-left">
        <div class="admin-page-header-icon">
            <i class="bi bi-puzzle"></i>
        </div>
        <h1 class="admin-page-header-title">
            <div class="admin-page-header-title-main">
                {if $edit_mode }
                    {if $copy_mode}复制题目{else /}编辑题目{/if}
                {else /}
                    添加题目
                {/if}
            </div>
            <div class="admin-page-header-title-right">
                {if $edit_mode }
                <a href="__OJ__/problemset/problem?pid={$problem['problem_id']}" target="_blank" class="admin-page-header-id">
                    <i class="bi bi-hash"></i> {$problem['problem_id']}
                </a>
                {/if}
                {if $edit_mode }
                    {if $copy_mode}<span class="en-text">Copy Problem</span>{else /}<span class="en-text">Edit Problem</span>{/if}
                {else /}
                    <span class="en-text">Add Problem</span>
                {/if}
            </div>
        </h1>
    </div>
    
    {if $edit_mode }
    <div class="admin-page-header-actions">
        <button type="button" class="btn btn-success btn-sm" 
                data-modal-url="__ADMIN__/filemanager/filemanager?item=problem&id={$problem['problem_id']}" 
                data-modal-title="附件管理 - 题目 #{$problem['problem_id']} - {$problem['title']|mb_substr=0,150,'utf-8'}..."
                title="附件管理 (File Manager)">
            <span class="cn-text"><i class="bi bi-paperclip"></i> 附件</span><span class="en-text">Attach</span>
        </button>
        <button type="button" class="btn btn-info btn-sm" 
                data-modal-url="__ADMIN__/judge/judgedata_manager?item=problem&id={$problem['problem_id']}" 
                data-modal-title="测试数据管理 - 题目 #{$problem['problem_id']} - {$problem['title']|mb_substr=0,150,'utf-8'}..."
                title="测试数据管理 (Test Data Manager)">
            <span class="cn-text"><i class="bi bi-database"></i> 数据</span><span class="en-text">Data</span>
        </button>
        <?php $defunct = $problem['defunct']; $item_id = $problem['problem_id']; ?>
        {include file="admin/changestatus_button" /}
    </div>
    {/if}
</div>

<div class="container">
    <form id="problem_edit_form" class="admin-form" method='post' action="__ADMIN__/problem/problem_edit_ajax">
        <!-- Judge Type Selection - Top Priority -->
        <div class="form-group">
            <label class="bilingual-label">评测类型：<span class="en-text">Judge Type</span></label>
            <div class="d-grid gap-2">
                <div class="row g-2">
                    <div class="col-4">
                        <button type="button" class="btn btn-primary judge-type-btn active bilingual-button w-100" data-spj="0" aria-pressed="true">
                            <i class="bi bi-check-circle">标准评测</i> <span class="en-text">Standard Judge</span>
                        </button>
                    </div>
                    <div class="col-4">
                        <button type="button" class="btn btn-warning judge-type-btn bilingual-button w-100" data-spj="1" aria-pressed="false">
                            <i class="bi bi-gear">特判评测</i> <span class="en-text">Test Program Judge</span>
                        </button>
                    </div>
                    <div class="col-4">
                        <button type="button" class="btn btn-success judge-type-btn bilingual-button w-100" data-spj="2" aria-pressed="false">
                            <i class="bi bi-chat-dots">交互评测</i> <span class="en-text">Interactive Judge</span>
                        </button>
                    </div>
                </div>
            </div>
            <input type="hidden" name="spj" id="spj_value" value="{if $edit_mode}{$problem['spj']}{else/}0{/if}">
        </div>

        <div class="form-group">
            <label for="title" class="bilingual-label">题目标题：<span class="en-text">Problem Title</span></label>
            <input type="text" class="form-control" placeholder="Problem Title..." name="title" {if $edit_mode}value="{$problem['title']}"{/if}>
            <label for="time_limit" class="bilingual-label">时间限制(秒)：<span class="en-text">Time Limit (Seconds)</span></label>
            <input type="text" class="form-control" placeholder="Time Limit..." name="time_limit" value="{if $edit_mode}{$problem['time_limit']}{else/}1{/if}">
            <label for="memory_limit" class="bilingual-label">内存限制(MB)：<span class="en-text">Memory Limit (MB)</span></label>
            <input type="text" class="form-control" placeholder="Memory Limit..." name="memory_limit" value="{if $edit_mode}{$problem['memory_limit']}{else/}128{/if}">
        </div>

        <label for="description" class="bilingual-label">题目描述 (markdown)：<span class="en-text">Description (Markdown)</span></label>
        <textarea class="form-control" placeholder="Description..." rows="5" cols="50" name="description" >{if $edit_mode}{$problem['description']|htmlspecialchars}{/if}</textarea>

        <label for="input" class="bilingual-label">输入描述 (markdown)：<span class="en-text">Input Description (Markdown)</span></label>
        <textarea class="form-control" placeholder="Input description..." rows="4" cols="50" name="input" >{if $edit_mode}{$problem['input']|htmlspecialchars}{/if}</textarea>

        <label for="output" class="bilingual-label">输出描述 (markdown)：<span class="en-text">Output Description (Markdown)</span></label>
        <textarea class="form-control" placeholder="Output description..." rows="4" cols="50" name="output" >{if $edit_mode}{$problem['output']|htmlspecialchars}{/if}</textarea>

        <div id="real_sample_textarea" style="display: none;">
            <textarea name="sample_input" id="sample_input_hidden" style="display: none;"><?php if($edit_mode) echo htmlspecialchars($problem["sample_input"], ENT_NOQUOTES, 'UTF-8'); ?></textarea>
            <textarea name="sample_output" id="sample_output_hidden" style="display: none;"><?php if($edit_mode) echo htmlspecialchars($problem["sample_output"], ENT_NOQUOTES, 'UTF-8'); ?></textarea>
        </div>
        <div class="form-group">
            <label class="bilingual-label mb-3">样例 (非测试数据)：<span class="en-text">Samples (Not Test Data)</span></label>
            <div id="fake_sample_div" class="sample-container">
                <!-- 样例将在这里动态生成 -->
            </div>
            <button type="button" class="btn btn-outline-primary w-100 mt-3" id="sample_add_btn">
                <i class="bi bi-plus-circle me-2"></i><span class="bilingual-inline">添加样例 <span class="en-text">Add Sample</span></span>
            </button>
        </div>

        <label for="hint" class="bilingual-label">提示 (markdown)：<span class="en-text">Hint (Markdown)</span></label>
        <textarea class="form-control" placeholder="Hint ..." rows="2" cols="50" name="hint" >{if $edit_mode}{$problem['hint']|htmlspecialchars}{/if}</textarea>

        <label for="source" class="bilingual-label">来源 (markdown)：<span class="en-text">Source (Markdown)</span></label>
        <input type="text" class="form-control" placeholder="Source ..." name="source" {if $edit_mode}value="{$problem['source']|htmlspecialchars}"{/if}>
        <label for="author" class="bilingual-label">出题：<span class="en-text">Author</span></label>
        <input type="text" class="form-control" placeholder="Author ..." name="author" {if $edit_mode}value="{$problem['author']|htmlspecialchars}"{/if}>
        {if $edit_mode && !$copy_mode}
            <input type="hidden" value="{$problem['problem_id']}" name="problem_id">
        {/if}
        {if $copy_mode}
            <input type="hidden" value="{$problem['problem_id']}" name="problem_copy_id">
        {/if}
        
        <div class="admin-form-actions">
            <button type="submit" id="submit_button" class="btn btn-primary bilingual-button">
                <span><i class="bi bi-check-circle"></i>
                {if $edit_mode}
                修改题目</span><span class="en-text">Modify Problem</span>
                {else /}
                添加题目</span><span class="en-text">Add Problem</span>
                {/if}
            </button>
        </div>
    </form>
</div>
<input type="hidden" id='page_info' edit_mode="{if $edit_mode}1{else/}0{/if}" copy_mode="{if isset($copy_mode) && $copy_mode}1{else /}0{/if}">
<script type="text/javascript">
const sample_num_max = 10;
const sample_length_max = 1024;
const hlevel = 5;
let real_sample_textarea;
let real_sample_in;
let real_sample_out;
let sample_in_str;
let sample_out_str;
let fake_sample_div;
function GetSampleNum() {
    return $('.sample-item').length;
}
function GetJointSampleTxt(name='input') {
    let textareas = document.getElementsByClassName(`sample_${name}_area`);
    let values = Array.prototype.map.call(textareas, function(textarea) {
        JudgeSampleLenth(textarea);
        return textarea.value;
    });
    // 返回包装对象格式：{data_type: 'json', data: [...]}
    return JSON.stringify({
        data_type: 'json',
        data: values
    });
}
function UpdateRealSample() {
    real_sample_in.val(GetJointSampleTxt('input'));
    real_sample_out.val(GetJointSampleTxt('output'));
}
function SwapDiv(div1, div2) {
    let tmp_div = document.createElement("div");
    div1.parentNode.insertBefore(tmp_div, div1);
    div2.parentNode.insertBefore(div1, div2);
    tmp_div.parentNode.insertBefore(div2, tmp_div);
    tmp_div.remove();
}
function JudgeSampleLenth(target_obj) {
    if(target_obj.value.length > sample_length_max) {
        alerty.warning(`Sample ${target_obj.getAttribute('stype')} ${target_obj.getAttribute('cs')} too long.<br/>Content truncated.`)
        target_obj.value = target_obj.value.substring(0, 1024);
    }
}
// sample process
$(document).ready(function() {
    real_sample_textarea = $('#real_sample_textarea');
    real_sample_in = $('#sample_input_hidden');
    real_sample_out = $('#sample_output_hidden');
    sample_in_str = real_sample_in.val() || '';
    sample_out_str = real_sample_out.val() || '';
    
    // ProblemSampleHtml 内部会调用 ParseSampleData 来处理兼容性
    // 支持 JSON 格式（新格式）和 ##CASE## 分隔格式（旧格式）
    fake_sample_div = $('#fake_sample_div');
    fake_sample_div.html(ProblemSampleHtml(sample_in_str, sample_out_str, hlevel, true));
    // 更新添加按钮状态
    function updateAddButtonState() {
        const currentCount = GetSampleNum();
        const addBtn = $('#sample_add_btn');
        
        if (currentCount >= sample_num_max) {
            addBtn.prop('disabled', true);
            addBtn.html(`<i class="bi bi-check-circle me-2"></i><span class="bilingual-inline">已达到最大样例数量 (${sample_num_max}) <span class="en-text">Max Samples Reached</span></span>`);
        } else {
            addBtn.prop('disabled', false);
            addBtn.html(`<span><i class="bi bi-plus-circle me-2"></i><span class="bilingual-inline">添加样例</span> <span class="en-text">Add Sample</span></span>`);
        }
    }
    
    $('#sample_add_btn').click(function() {
        let sample_num = GetSampleNum();
        if(GetSampleNum() >= sample_num_max) {
            alerty.error({
                message: `最多只能添加 ${sample_num_max} 个样例`,
                message_en: `Maximum ${sample_num_max} samples allowed`
            });
        } else {
            fake_sample_div.append($(OneSample(sample_num, '', '', hlevel, true))[0]);
            updateAddButtonState();
            // 添加样例后滚动到新添加的样例
            setTimeout(() => {
                const newSample = fake_sample_div.find('.sample-item').last();
                if (newSample.length) {
                    newSample[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }, 100);
        }
    });
    
    // 初始化按钮状态
    updateAddButtonState();
    document.addEventListener('click', (e) => {
        if(e.target.classList.contains('up_sample_btn') || e.target.closest('.up_sample_btn')) {
            let sample_item_this = e.target.closest('.sample-item');
            let sample_item_pre = sample_item_this.previousElementSibling;
            if(sample_item_pre !== null) {
                SwapDiv(sample_item_this, sample_item_pre);
                UpdateRealSample();
                ResetSampleIdx(fake_sample_div);
            }
        } else if(e.target.classList.contains('down_sample_btn') || e.target.closest('.down_sample_btn')) {
            let sample_item_this = e.target.closest('.sample-item');
            let sample_item_next = sample_item_this.nextElementSibling;
            if(sample_item_next !== null) {
                SwapDiv(sample_item_this, sample_item_next);
                UpdateRealSample();
                ResetSampleIdx(fake_sample_div);
            }
        }
    });
    document.addEventListener('dblclick', (e) => {
        if(e.target.classList.contains('del_sample_btn') || e.target.closest('.del_sample_btn')) {
            if(GetSampleNum() <= 1) {
                alerty.error({
                    message: '至少需要保留一个样例',
                    message_en: 'At least 1 sample is required'
                });
            } else {
                const sampleItem = e.target.closest('.sample-item');
                if (sampleItem) {
                    // 添加删除动画效果
                    sampleItem.style.transition = 'all 0.3s ease';
                    sampleItem.style.transform = 'scale(0.8)';
                    sampleItem.style.opacity = '0';
                    
                    setTimeout(() => {
                        sampleItem.remove();
                        ResetSampleIdx(fake_sample_div);
                        UpdateRealSample();
                        updateAddButtonState();
                    }, 300);
                }
            }
        }
    });
    document.addEventListener('change', (e) => {
        if(e.target.classList.contains('sample_text_input')) {
            UpdateRealSample();
        }
    });
});

// form
    var page_info = $('#page_info');
    var edit_mode = page_info.attr('edit_mode');
    let copy_mode = page_info.attr('copy_mode');
    var submit_button = $('#submit_button');
    var submit_button_texts = window.Bilingual.getBilingualText(submit_button);
    var submit_button_text = submit_button_texts.chinese;
    var submit_button_en_text = submit_button_texts.english;
    $(document).ready(function() {
        // Initialize judge type selection
        var currentSpj = $('#spj_value').val();
        $('.judge-type-btn').removeClass('active').attr('aria-pressed', 'false');
        $('.judge-type-btn[data-spj="' + currentSpj + '"]').addClass('active').attr('aria-pressed', 'true');
        
        // Handle judge type button clicks (radio-like behavior)
        $('.judge-type-btn').click(function() {
            // Remove active state from all buttons
            $('.judge-type-btn').removeClass('active').attr('aria-pressed', 'false');
            
            // Add active state to clicked button
            $(this).addClass('active').attr('aria-pressed', 'true');
            
            // Update hidden input value
            $('#spj_value').val($(this).data('spj'));
            
            // Visual feedback
            $(this).blur(); // Remove focus after click
        });
        
        // 使用简化的表单验证工具
        if (typeof window.FormValidationTip === 'undefined') {
            console.error('FormValidationTip not loaded');
            return;
        }
        
        window.FormValidationTip.initCommonFormValidation('#problem_edit_form', {
            title: {
                rules: { required: true, maxlength: 200 },
                messages: {
                    required: window.FormValidationTip.createBilingualMessage("题目标题不能为空", "Problem title is required"),
                    maxlength: window.FormValidationTip.createBilingualMessage("题目标题不能超过200个字符", "Problem title cannot exceed 200 characters")
                }
            },
            time_limit: {
                rules: { required: true, maxlength: 200 },
                messages: {
                    required: window.FormValidationTip.createBilingualMessage("时间限制不能为空", "Time limit is required"),
                    maxlength: window.FormValidationTip.createBilingualMessage("时间限制不能超过200个字符", "Time limit cannot exceed 200 characters")
                }
            },
            memory_limit: {
                rules: { required: true, maxlength: 200 },
                messages: {
                    required: window.FormValidationTip.createBilingualMessage("内存限制不能为空", "Memory limit is required"),
                    maxlength: window.FormValidationTip.createBilingualMessage("内存限制不能超过200个字符", "Memory limit cannot exceed 200 characters")
                }
            },
            description: {
                rules: { required: true, maxlength: 65536 },
                messages: {
                    required: window.FormValidationTip.createBilingualMessage("题目描述不能为空", "Problem description is required"),
                    maxlength: window.FormValidationTip.createBilingualMessage("题目描述不能超过65536个字符", "Problem description cannot exceed 65536 characters")
                }
            },
            input: {
                rules: { maxlength: 65536 },
                messages: {
                    maxlength: window.FormValidationTip.createBilingualMessage("输入描述不能超过65536个字符", "Input description cannot exceed 65536 characters")
                }
            },
            output: {
                rules: { maxlength: 65536 },
                messages: {
                    maxlength: window.FormValidationTip.createBilingualMessage("输出描述不能超过65536个字符", "Output description cannot exceed 65536 characters")
                }
            },
            sample_input: {
                rules: { required: true, minlength: 1, maxlength: 16384 },
                messages: {
                    required: window.FormValidationTip.createBilingualMessage("样例输入不能为空", "Sample input is required"),
                    minlength: window.FormValidationTip.createBilingualMessage("样例输入不能为空", "Sample input cannot be empty"),
                    maxlength: window.FormValidationTip.createBilingualMessage("样例输入不能超过16384个字符", "Sample input cannot exceed 16384 characters")
                }
            },
            sample_output: {
                rules: { required: true, minlength: 1, maxlength: 16384 },
                messages: {
                    required: window.FormValidationTip.createBilingualMessage("样例输出不能为空", "Sample output is required"),
                    minlength: window.FormValidationTip.createBilingualMessage("样例输出不能为空", "Sample output cannot be empty"),
                    maxlength: window.FormValidationTip.createBilingualMessage("样例输出不能超过16384个字符", "Sample output cannot exceed 16384 characters")
                }
            },
            hint: {
                rules: { maxlength: 65536 },
                messages: {
                    maxlength: window.FormValidationTip.createBilingualMessage("提示不能超过65536个字符", "Hint cannot exceed 65536 characters")
                }
            },
            source: {
                rules: { maxlength: 100 },
                messages: {
                    maxlength: window.FormValidationTip.createBilingualMessage("来源不能超过100个字符", "Source cannot exceed 100 characters")
                }
            },
            author: {
                rules: { maxlength: 100 },
                messages: {
                    maxlength: window.FormValidationTip.createBilingualMessage("作者不能超过100个字符", "Author cannot exceed 100 characters")
                }
            }
        }, function(form) {
            // 提交处理函数
            submit_button.attr('disabled', true);
            submit_button.text('Waiting...');
            $('.sample_input_area').prop('disabled', true);
            UpdateRealSample();
            $(form).ajaxSubmit({
                success: function(ret) {
                    if(ret['code'] == 1) {
                        if(typeof(ret['data']['alert']) != 'undefined' && ret['data']['alert'] == true){
                            alerty.alert(ret['msg']);
                        }else{
                            alerty.success(ret['msg']);
                        }
                        button_delay(submit_button, 3, submit_button_text, null, submit_button_en_text);
                        if(edit_mode != 1 || copy_mode == 1) {
                            setTimeout(function(){location.href='problem_edit?id='+ret.data.problem_id}, 500);
                        }
                    }
                    else {
                        alerty.alert(ret['msg']);
                        button_delay(submit_button, 3, submit_button_text, null, submit_button_en_text);
                    }
                    $('.sample_input_area').prop('disabled', false);
                    return false;
                }
            });
            return false;
        });
        
    });
    $(window).keydown(function(e) {
        if (e.keyCode == 83 && e.ctrlKey) {
            e.preventDefault();
            var a=document.createEvent("MouseEvents");
            a.initEvent("click", true, true);
            $('#submit_button')[0].dispatchEvent(a);
        }
    });
</script>


<style>
/* Judge Type Button Group - Enhanced Radio-like styling */
.judge-type-btn {
    transition: all 0.3s ease;
    border: 3px solid transparent !important;
    position: relative;
    overflow: hidden;
    padding: 0;
}

/* 确保图标正确显示在中文左侧，并垂直对齐 */
.judge-type-btn i,
.judge-type-btn .bi {
    display: inline-block !important;
    margin-right: 0.375rem;
    vertical-align: middle;
    line-height: 1;
    font-size: 1em;
    width: auto;
    height: auto;
}

/* 确保中文文字部分（图标后的内容）与图标垂直对齐 */
.judge-type-btn i + *:not(.en-text),
.judge-type-btn .bi + *:not(.en-text) {
    display: inline;
    vertical-align: middle;
    line-height: 1;
}

.judge-type-btn:not(.active) {
    opacity: 0.6;
    border: 3px solid #ddd !important;
    transform: scale(0.95);
    box-shadow: 0 1px 2px rgba(0,0,0,0.1);
}

.judge-type-btn.active {
    opacity: 1;
    border: 3px solid #000 !important;
    box-shadow: 0 4px 8px rgba(0,0,0,0.3), 0 0 0 2px rgba(255,255,255,0.8);
    transform: scale(1.05) translateY(-2px);
    z-index: 10;
}

.judge-type-btn:hover:not(.active) {
    opacity: 0.8;
    border: 3px solid #999 !important;
    transform: scale(0.98);
}

/* 激活状态的发光效果 */
.judge-type-btn.active::before {
    content: '';
    position: absolute;
    top: -2px;
    left: -2px;
    right: -2px;
    bottom: -2px;
    background: linear-gradient(45deg, rgba(255,255,255,0.3), rgba(255,255,255,0.1));
    border-radius: inherit;
    z-index: -1;
    animation: glow 2s ease-in-out infinite alternate;
}

/* 激活状态的后置指示器 */
.judge-type-btn.active::after {
    content: '✓';
    position: absolute;
    top: 5px;
    right: 5px;
    background: rgba(0,0,0,0.8);
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: bold;
    z-index: 5;
    animation: checkmark 0.5s ease-out;
}

@keyframes checkmark {
    0% {
        transform: scale(0) rotate(180deg);
        opacity: 0;
    }
    50% {
        transform: scale(1.2) rotate(90deg);
        opacity: 1;
    }
    100% {
        transform: scale(1) rotate(0deg);
        opacity: 1;
    }
}

@keyframes glow {
    from {
        box-shadow: 0 0 5px rgba(255,255,255,0.5);
    }
    to {
        box-shadow: 0 0 15px rgba(255,255,255,0.8);
    }
}

/* 激活状态的图标效果 */
.judge-type-btn.active i {
    animation: pulse 1.5s ease-in-out infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

/* 按钮组整体效果 */
.btn-group-justified .judge-type-btn.active {
    margin: -2px;
    z-index: 10;
}

/* 非激活按钮的淡化效果 */
.judge-type-btn:not(.active) {
    filter: grayscale(20%) brightness(0.8);
}

/* 激活按钮的增强效果 */
.judge-type-btn.active {
    filter: brightness(1.1) saturate(1.2);
}

/* 悬停时的预览效果 */
.judge-type-btn:hover:not(.active) {
    filter: grayscale(10%) brightness(0.9);
    transform: scale(0.98) translateY(-1px);
}

/* 移动端优化 */
@media (max-width: 768px) {
    .judge-type-btn.active::after {
        width: 16px;
        height: 16px;
        font-size: 10px;
        top: 3px;
        right: 3px;
    }
    
    .judge-type-btn.active {
        transform: scale(1.02) translateY(-1px);
    }
    
    .judge-type-btn:not(.active) {
        transform: scale(0.98);
    }
}

/* 表单验证错误样式 */
.is-invalid {
    border-color: #dc3545 !important;
    box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25) !important;
}

.is-valid {
    border-color: #28a745 !important;
    box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25) !important;
}

/* 样例样式已移至 oj_problem.css 中，实现前后台样式统一 */
</style>