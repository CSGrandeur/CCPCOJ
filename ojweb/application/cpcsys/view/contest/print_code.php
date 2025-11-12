<form role="form" id="print_code_form" action="__CPC__/contest/print_code_ajax" method="POST">
    <div class="mb-3">
        <label for="source" class="form-label bilingual-inline">源代码<span class="en-text">Source Code</span></label>
        <textarea class="form-control" id="source" rows="25" name="source" spellcheck="false" style="max-width:900px;" placeholder="节约能源，保护环境，不要浪费纸张。&#10;Save energy and protect the environment, don't waste paper."></textarea>
    </div>
    <input type="hidden" id="contest_id_input" name="cid" value="{$contest['contest_id']}">
    <div class="mb-3" id="fn-nav">
        <button type="submit" id="submit_button" class="btn btn-primary">提交打印请求<span class="en-text">Submit Print Request</span></button>
    </div>
</form>

<script type="text/javascript">
    (function(){
        var form = document.getElementById('print_code_form');
        var submitBtn = document.getElementById('submit_button');

        // 使用自研校验库
        var fieldConfigs = {
            source: {
                rules: { required: true, minlength: 6, maxlength: 16384 },
                messages: {
                    required: FormValidationTip.createBilingualMessage('请粘贴或输入源代码', 'Source code is required'),
                    minlength: FormValidationTip.createBilingualMessage('代码过短（≥6）', 'Code too short (≥6)'),
                    maxlength: FormValidationTip.createBilingualMessage('代码过长（≤16384）', 'Code too long (≤16384)')
                }
            }
        };

        FormValidationTip.initFormValidation('#print_code_form', fieldConfigs, function(){
            // 提交处理
            submitBtn.disabled = true;

            // 使用 jQuery 提交（保持与原有代码兼容）
            $.ajax({
                url: form.action,
                type: 'POST',
                data: $(form).serialize(),
                dataType: 'json'
            }).done(function(ret){
                if (ret && ret.code == 1) {
                    alerty.success('提交成功', 'Submitted successfully');
                    setTimeout(function(){
                        location.href = '__CPC__/contest/print_status?cid=' + (ret.data && ret.data.contest_id) + '#team_id=' + (ret.data && ret.data.team_id);
                    }, 1000);
                } else {
                    alerty.error(ret && ret.msg ? ret.msg : '提交失败', 'Submit failed');
                    submitBtn.disabled = false;
                }
            }).fail(function(){
                alerty.error('网络错误', 'Network error');
                submitBtn.disabled = false;
            });
        });
    })();
</script>