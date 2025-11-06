<div class="card">
    <div class="card-header">
        <h4 class="card-title mb-0 bilingual-inline">
            <span class="cn-text"><i class="bi bi-plus-circle me-2"></i>
            发送提问</span><span class="en-text">Add Topic</span>
        </h4>
    </div>
    <div class="card-body">
        <form role="form" id="topic_add_form" action="/{$module}/{$contest_controller}/topic_add_ajax" method="POST">
            <div class="mb-3">
                <label for="apid" class="form-label bilingual-inline">
                    题目ID<span class="en-text">Problem ID</span>:
                </label>
                <select id='topic-apid' name="apid" class="form-select">
                    <option value="-1">
                        全部<span class="en-text">All</span>
                    </option>
                    {foreach($abc2id as $k => $val) }
                    <option value="{$k}">
                        {$k}
                    </option>
                    {/foreach}
                </select>
            </div>
            
            <div class="mb-3">
                <label for="topic_title" class="form-label bilingual-inline">
                    提问标题<span class="en-text">Topic Title</span>:
                </label>
                <input type="text" class="form-control" id="title" placeholder="1~64个字符 1~64 characters" name="topic_title" required>
            </div>

            <div class="mb-3">
                <label for="topic_content" class="form-label bilingual-inline">
                    提问内容<span class="en-text">Topic Content</span>:
                </label>
                <textarea class="form-control" rows="20" name="topic_content" spellcheck="false" placeholder="请输入您的提问内容... Please enter your question... " required></textarea>
            </div>
            
            <input type="hidden" id="contest_id_input" class="form-control" name="cid" value="{$contest['contest_id']}">
            
            <div class="d-flex gap-2" id="fn-nav">
                <button type="submit" id='submit_button' class="btn btn-primary">
                    <span class="cn-text"><i class="bi bi-send me-1"></i>
                    提交提问</span><span class="en-text">Submit Topic</span>
                </button>
                <button type="button" id='clear_button' class="btn btn-outline-secondary">
                    <span class="cn-text"><i class="bi bi-arrow-clockwise me-1"></i>
                    清空</span><span class="en-text">Clear</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script type="text/javascript">
    // 传递模板变量给JavaScript
    window.topicAddConfig = {
        module: "{$module}",
        contest_controller: "{$contest_controller}",
        contest_id: "{$contest['contest_id']}"
    };
</script>
{css href="__STATIC__/csgoj/contest/topic.css" /}
{js href="__STATIC__/csgoj/contest/topic.js" /}