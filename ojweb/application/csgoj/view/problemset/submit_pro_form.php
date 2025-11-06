<form role="form" id="submit_common_form" action="{if $controller=='contest'}/{$module}/{$contest_controller}/submit_ajax{else/}__OJ__/{$controller}/submit_ajax{/if}" method="POST">
    <div class="row mb-3">
        <div class="col-md-3">
            <label for="submit_language_select" class="form-label bilingual-inline">编程语言<span class="en-text">Language</span></label>
            <select id="submit_language_select" name="language" class="form-select">
                {foreach($allowLanguage as $key=>$value)}
                <option value="{$key}">{$value}</option>
                {/foreach}
            </select>
        </div>
        {if $controller=='contest'}
        <div class="col-md-3">
            <label for="contest_pid_input" class="form-label bilingual-inline">题号<span class="en-text">Problem ID</span></label>
            <input type="text" id="contest_pid_input" class="form-control" name="pid" value="{$apid}" placeholder="如 A 或 AB" />
        </div>
        {/if}
    </div>

    <div class="mb-3">
        <label for="source" class="form-label bilingual-inline">源代码<span class="en-text">Source Code</span>:</label>
        <textarea class="form-control" id="source" rows="20" name="source" spellcheck="false" style="max-width:800px;"></textarea>
    </div>

    <div class="mb-3" id="fn-nav">
        {if $controller=='contest'}
        <input type="hidden" id="contest_id_input" name="cid" value="{$contest['contest_id']}">
        {else/}
        <input type="hidden" id="problem_id_input" name="pid" value="{$problem['problem_id']}">
        {/if}
        <button type="submit" id="submit_button_common" class="btn btn-primary">提交<span class="en-text">Submit</span></button>
    </div>
</form>

<script type="text/javascript">
    // 以 PHP 原生方式注入运行时配置，供 JS 使用
    window.PROBLEM_SUBMIT_CONFIG = {
        isContest: <?php echo ($controller == 'contest') ? 'true' : 'false'; ?>,
        statusUrl: '<?php echo ($controller == 'contest') ? ('/' . $module . '/' . $contest_controller . '/status') : ('/' . $module . '/status'); ?>'
    };
</script>

<script type="text/javascript">
    (function(){
        // 语言选择本地缓存（与历史逻辑兼容）
        var contestIdElem = document.getElementById('contest_id_input');
        var langSelect = document.getElementById('submit_language_select');
        var submitBtn = document.getElementById('submit_button_common');
        var form = document.getElementById('submit_common_form');

        var cookieName = contestIdElem ? ('lastlanguage' + contestIdElem.value) : 'global_lastlanguage';
        if (localStorage.getItem(cookieName)) {
            langSelect.value = localStorage.getItem(cookieName);
        }

        // 使用自研校验库
        var fieldConfigs = {
            language: {
                rules: { required: true },
                messages: { required: FormValidationTip.createBilingualMessage('请选择编程语言', 'Language is required') }
            },
            source: {
                rules: { required: true, minlength: 6, maxlength: 65536 },
                messages: {
                    required: FormValidationTip.createBilingualMessage('请粘贴或输入源代码', 'Source code is required'),
                    minlength: FormValidationTip.createBilingualMessage('代码过短（≥6）', 'Code too short (≥6)'),
                    maxlength: FormValidationTip.createBilingualMessage('代码过长（≤65536）', 'Code too long (≤65536)')
                }
            }
        };
        if (window.PROBLEM_SUBMIT_CONFIG && window.PROBLEM_SUBMIT_CONFIG.isContest) {
            fieldConfigs.pid = {
                rules: { required: true },
                messages: { required: FormValidationTip.createBilingualMessage('请输入题号', 'Problem ID is required') }
            };
        }

        FormValidationTip.initFormValidation('#submit_common_form', fieldConfigs, function(){
            // 提交处理
            localStorage.setItem(cookieName, langSelect.value);
            submitBtn.disabled = true;

            // 使用 jQuery 提交
            $.ajax({
                url: form.action,
                type: 'POST',
                data: $(form).serialize(),
                dataType: 'json'
            }).done(function(ret){
                if (ret && ret.code == 1) {
                    alerty.success(ret.msg || '提交成功');
                    setTimeout(function(){
                        if (window.PROBLEM_SUBMIT_CONFIG && window.PROBLEM_SUBMIT_CONFIG.isContest) {
                            location.href = window.PROBLEM_SUBMIT_CONFIG.statusUrl + '?cid=' + (ret.data && ret.data.contest_id) + '&user_id=' + (ret.data && ret.data.user_id);
                        } else {
                            location.href = window.PROBLEM_SUBMIT_CONFIG.statusUrl + '?user_id=' + (ret.data && ret.data.user_id);
                        }
                    }, 800);
                } else {
                    alerty.error(ret && ret.msg ? ret.msg : '提交失败');
                    submitBtn.disabled = false;
                }
            }).fail(function(){
                alerty.error('网络错误<span class="en-text">Network error</span>');
                submitBtn.disabled = false;
            });
        });
    })();
</script>

