<?php 
$edit_mode = $module == 'admin' ? $edit_mode : true;
$copy_mode = isset($copy_mode) ? $copy_mode : false;
?>
<div class="admin-page-header">
    <div class="admin-page-header-left">
        <div class="admin-page-header-icon">
            <i class="bi bi-trophy"></i>
        </div>
        <h1 class="admin-page-header-title">
            <div class="admin-page-header-title-main">
                {if $edit_mode && !$copy_mode }
                    编辑比赛
                {else /}
                    添加比赛
                {/if}
            </div>
            <div class="admin-page-header-title-right">
                {if $edit_mode && !$copy_mode }
                <a href="__OJ__/contest/problemset?cid={$contest['contest_id']}" target="_blank" class="admin-page-header-id">
                    <i class="bi bi-hash"></i> {$contest['contest_id']}
                </a>
                {/if}
                {if $edit_mode && !$copy_mode }
                <span class="en-text">Edit Contest</span>
                {else /}
                <span class="en-text">Add Contest</span>
                {/if}
            </div>
        </h1>
    </div>
    {if $module == 'admin'}
        {if $edit_mode && !$copy_mode }
        <div class="admin-page-header-actions">
            <button type="button" class="btn btn-success btn-sm" 
                    data-modal-url="__ADMIN__/filemanager/filemanager?item={$controller}&id={$contest['contest_id']}" 
                    data-modal-title="附件管理 - 比赛 #{$contest['contest_id']} - {$contest['title']|mb_substr=0,150,'utf-8'}..."
                    title="附件管理 (File Manager)">
                <span><i class="bi bi-paperclip"></i> 附件</span><span class="en-text">Attach</span>
            </button>
            <?php $defunct = $contest['defunct']; $item_id = $contest['contest_id']; ?>
            {include file="../../admin/view/admin/changestatus_button" /}
        </div>
        {/if}
    {/if}
</div>

{if $module == 'admin'}
    {include file="../../admin/view/contest/problem_selection" /}

    <script>
    // 初始化比赛编辑页面的题号输入组件
    document.addEventListener('DOMContentLoaded', function() {
        // 创建题号输入组件
        const problemInput = createProblemInput('contest_problem_input', {
            max: 26,
            allowDuplicates: false,
            allowInvalid: false,
            showCount: true,
            showActions: true,
            onChange: function(csv, component) {
                // 可以在这里添加额外的变化处理逻辑
            }
        });
        
        // 设置题目选择器确认回调
        window.onProblemSelectionConfirm = function(problemIds) {
            problemInput.addProblems(problemIds);
        };
        
        // 如果有初始值（编辑模式），设置到组件中
        const initialValue = '{if $edit_mode}{$problems}{/if}';
        if (initialValue && initialValue !== '') {
            problemInput.setValue(initialValue);
        }
        
        // 确保初始化后触发气球颜色更新
        setTimeout(() => {
            if (typeof window.InitBalloonColorPreview === 'function') {
                window.InitBalloonColorPreview();
            }
        }, 100);
    });
    </script>
{/if}

<?php 
$action_url = $module == 'admin' ? '/admin/contest/' : '/' . $module . '/admin/';
$cid_suffix = $module == 'admin' ? '' : '?cid=' . $contest['contest_id'];
$action_url .= ($edit_mode && !$copy_mode ? 'contest_edit_ajax' : 'contest_add_ajax') . $cid_suffix;
?>

<form id="contest_edit_form" class="admin-form" method='post' action="{$action_url}">
    <div class="container">
        {if $module == 'admin'}
            <!-- Contest Type Selection - Top Priority -->
            <div class="form-group mb-4">
                <label for="private" class="bilingual-label">比赛类型：<span class="en-text">Contest Type</span></label>
                <div class="d-grid gap-2">
                    <div class="row g-2">
                        <div class="col-3">
                            <button type="button" class="btn btn-success contest-type-btn active bilingual-button w-100" data-private="0" aria-pressed="true">
                                <i class="bi bi-unlock">公开</i> <span class="en-text">Public</span>
                            </button>
                        </div>
                        <div class="col-3">
                            <button type="button" class="btn btn-warning contest-type-btn bilingual-button w-100" data-private="0" data-encrypted="true" aria-pressed="false">
                                <i class="bi bi-shield-lock">加密</i> <span class="en-text">Encrypted</span>
                            </button>
                        </div>
                        <div class="col-3">
                            <button type="button" class="btn btn-danger contest-type-btn bilingual-button w-100" data-private="1" aria-pressed="false">
                                <i class="bi bi-lock">私有</i> <span class="en-text">Private</span>
                            </button>
                        </div>
                        <div class="col-3">
                            <button type="button" class="btn btn-primary contest-type-btn bilingual-button w-100" data-private="2" aria-pressed="false">
                                <i class="bi bi-award">标准</i> <span class="en-text">Standard</span>
                            </button>
                        </div>
                    </div>
                </div>
                <input type="hidden" name="private" id="private_value" value="{if $edit_mode}{$private}{else/}0{/if}">
            </div>
        {/if}
        <div class="form-group mb-4" id="password_group" style="display: none;">
            <label for="password" class="bilingual-label">密码：<span class="en-text">Password</span></label>
            <input type="text" class="form-control" id="password" name="password" placeholder="输入比赛密码..." {if $edit_mode}value="{$contest['password']}"{/if}>
        </div>

        <div class="row">
            <div class="col-md-9" id="main_content_column">
                {if $module == 'admin'}
                    <div class="form-group mb-3">
                        <label for="title" class="bilingual-label">比赛标题：<span class="en-text">Contest Title</span></label>
                        <input type="text" class="form-control" id="title" placeholder="输入比赛标题..." name="title" {if $edit_mode && !$copy_mode}value="{$contest['title']}"{/if}>
                    </div>
                {/if}
                <!-- 时间设置 -->
                <div class="form-group mb-3">
                    <label class="bilingual-label">开始时间：<span class="en-text">Start Time</span></label>
                    <div class="row g-2">
                        <div class="col-2">
                            <input type="text" class="form-control form-control-sm" id="start_year" name="start_year" 
                                    title="年 (Year)" placeholder="年" value="{$start_year}">
                        </div>
                        <div class="col-2">
                            <input type="text" class="form-control form-control-sm" id="start_month" name="start_month" 
                                    title="月 (Month)" placeholder="月" value="{$start_month}">
                        </div>
                        <div class="col-2">
                            <input type="text" class="form-control form-control-sm" id="start_day" name="start_day" 
                                    title="日 (Day)" placeholder="日" value="{$start_day}">
                        </div>
                        <div class="col-2">
                            <input type="text" class="form-control form-control-sm" id="start_hour" name="start_hour" 
                                    title="时 (Hour)" placeholder="时" value="{$start_hour}">
                        </div>
                        <div class="col-2">
                            <input type="text" class="form-control form-control-sm" id="start_minute" name="start_minute" 
                                    title="分 (Minute)" placeholder="分" value="{$start_minute}">
                        </div>
                    </div>
                </div>
                
                <div class="form-group mb-3">
                    <label class="bilingual-label">结束时间：<span class="en-text">End Time</span></label>
                    <div class="row g-2">
                        <div class="col-2">
                            <input type="text" class="form-control form-control-sm" id="end_year" name="end_year" 
                                    title="年 (Year)" placeholder="年" value="{$end_year}">
                        </div>
                        <div class="col-2">
                            <input type="text" class="form-control form-control-sm" id="end_month" name="end_month" 
                                    title="月 (Month)" placeholder="月" value="{$end_month}">
                        </div>
                        <div class="col-2">
                            <input type="text" class="form-control form-control-sm" id="end_day" name="end_day" 
                                    title="日 (Day)" placeholder="日" value="{$end_day}">
                        </div>
                        <div class="col-2">
                            <input type="text" class="form-control form-control-sm" id="end_hour" name="end_hour" 
                                    title="时 (Hour)" placeholder="时" value="{$end_hour}">
                        </div>
                        <div class="col-2">
                            <input type="text" class="form-control form-control-sm" id="end_minute" name="end_minute" 
                                    title="分 (Minute)" placeholder="分" value="{$end_minute}">
                        </div>
                    </div>
                </div>
                <!-- 奖项比例设置 -->
                <div class="form-group mb-3">
                    <label class="bilingual-label">奖项比例 (%)：<span class="en-text">Award Ratio (%)</span></label>
                    <div class="row g-2">
                        <div class="col-3">
                            <input type="text" class="form-control form-control-sm" id="ratio_gold" name="ratio_gold" 
                                    placeholder="金牌" title="金奖比例 (Ratio of Gold)" value="{$ratio_gold}">
                        </div>
                        <div class="col-3">
                            <input type="text" class="form-control form-control-sm" id="ratio_silver" name="ratio_silver" 
                                    placeholder="银牌" title="银奖比例 (Ratio of Silver)" value="{$ratio_silver}">
                        </div>
                        <div class="col-3">
                            <input type="text" class="form-control form-control-sm" id="ratio_bronze" name="ratio_bronze" 
                                    placeholder="铜牌" title="铜奖比例 (Ratio of Bronze)" value="{$ratio_bronze}">
                        </div>
                    </div>
                </div>
                
                <!-- 封榜时间设置 -->
                <div class="form-group mb-3">
                    <label class="bilingual-label">封榜时间 (分钟)：<span class="en-text">Frozen Minutes</span></label>
                    <div class="row g-2">
                        <div class="col-3">
                            <input type="text" class="form-control form-control-sm" id="frozen_minute" name="frozen_minute" 
                                    placeholder="结束前" title="结束前 (Before End)" value="{$frozen_minute}">
                        </div>
                        <div class="col-3">
                            <input type="text" class="form-control form-control-sm" id="frozen_after" name="frozen_after" 
                                    placeholder="结束后" title="结束后 (After End)" value="{$frozen_after}">
                        </div>
                    </div>
                </div>
                
                <div class="form-group mb-3">
                    <label for="topteam" class="bilingual-label">前 N 队合计学校排名：<span class="en-text">Top N Teams for School Rank</span></label>
                    <input type="text" class="form-control" id="topteam" name="topteam" placeholder="输入数字..." title="前 N 队题目罚时之和作为学校排名依据" value="{$topteam}">
                </div>
                <!-- 编程语言选择 -->
                <div class="form-group mb-3">
                    <label for="language" class="bilingual-label">允许的编程语言：<span class="en-text">Allowed Programming Languages</span></label>
                    <?php 
                    $ojLang = config('CsgojConfig.OJ_LANGUAGE');
                    $selectedLanguages = [];
                    if ($edit_mode) {
                        foreach($ojLang as $k => $val) {
                            if (($contest['langmask'] >> $k) & 1) {
                                $selectedLanguages[] = $k;
                            }
                        }
                    }
                    ?>
                    <select name="language[]" class="form-select" multiple size="6">
                        {foreach($ojLang as $k=>$val) }
                        <option value="{$k}" {if $edit_mode && (($contest['langmask'] >> $k) & 1)}selected="selected"{/if}>
                            {$val}
                        </option>
                        {/foreach}
                    </select>
                    <div class="form-text">
                        <span class="bilingual-inline">
                            <span>按住 Ctrl 键选择多个语言</span>
                            <span class="en-text">Hold Ctrl to select multiple languages</span>
                        </span>
                    </div>
                </div>
                
            {if $module == 'admin'}
                <!-- 题目设置 -->
                <div class="form-group mb-3">
                    <div id="contest_problem_input"></div>
                </div>
                
                {include file="../../admin/view/contest/balloon_color_selection" /}
            {/if}
                <!-- 描述和公告 -->
                <div class="form-group mb-3">
                    <label for="contest_description" class="bilingual-label">描述或公告 (支持 Markdown)：<span class="en-text">Description & Announcement (Markdown supported)</span></label>
                    <textarea id="contest_description" class="form-control" placeholder="输入比赛描述或公告..." rows="6" name="description">{if $edit_mode}{$contest['description']|htmlspecialchars}{/if}</textarea>
                </div>
            </div>
            
            {if $module == 'admin'}
                <!-- 参赛用户列 -->
                <div class="col-md-3" id="users_column" style="display: none;">
                    <div class="form-group mb-3">
                        <label for="users" class="bilingual-label">参赛用户 (每行一个用户ID)：<span class="en-text">Contest Users (one user ID per line)</span></label>
                        <textarea class="form-control" id="users" placeholder="team001&#10;team002&#10;..." rows="20" name="users">{if $edit_mode && !$copy_mode}{$users}{/if}</textarea>
                        <div class="form-text">
                            <span class="bilingual-inline">
                                <span>每行输入一个用户ID</span>
                                <span class="en-text">One user ID per line</span>
                            </span>
                        </div>
                    </div>
                </div>
            {/if}
        </div>
        
        <!-- 提交按钮 -->
        {if $edit_mode && !$copy_mode }
        <input type="hidden" id="id_input" value="{$contest['contest_id']}" name="contest_id">
        {/if}
        
        <div class="admin-form-actions">
            <button type="submit" id="submit_button" class="btn btn-primary bilingual-button">
                <span><i class="bi bi-check-circle"></i>
                {if $edit_mode && !$copy_mode}
                修改比赛</span><span class="en-text">Modify Contest</span>
                {else}
                添加比赛</span><span class="en-text">Add Contest</span>
                {/if}
            </button>
        </div>
    </div>
</form>

<script>
    window.CONTEST_EDIT_CONFIG = {
        edit_mode: <?php echo $module != 'admin' || $edit_mode && !$copy_mode ? 1 : 0; ?>,
    }
</script>

    
{js file="__STATIC__/csgoj/contest/admin_contest_edit.js" /}
{if $module == 'admin'}
    {css file="__STATIC__/csgoj/admin/contest.css" /}
    {js file="__STATIC__/csgoj/admin/contest.js" /}
{/if}