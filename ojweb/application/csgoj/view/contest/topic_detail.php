<!-- 提问详情头部 -->
<div class="mb-3">
    <div class="d-flex justify-content-between align-items-start mb-2">
        <div class="flex-grow-1">
            <h2 class="mb-1" title="{$topic['title']}">
                <i class="bi bi-chat-quote me-2 text-primary"></i>
                #{$topic['topic_id']}: {$topic['title']|htmlspecialchars}
            </h2>
            <div class="d-flex align-items-center gap-2 text-muted small">
                <span class="badge bg-warning text-dark">
                    题目 <span id="topic_problem_display">{$topic['pid_abc']}</span><span class="en-text">Problem <span id="topic_problem_display_en">{$topic['pid_abc']}</span></span>
                </span>
                {if $topic['public_show'] == 0}
                <span class="badge bg-secondary">
                    <span class="cn-text"><i class="bi bi-lock me-1"></i>私有</span><span class="en-text">Private</span>
                </span>
                {else}
                <span class="badge bg-success">
                    <span class="cn-text"><i class="bi bi-unlock me-1"></i>公开</span><span class="en-text">Public</span>
                </span>
                {/if}
                <span class="text-muted">
                    <i class="bi bi-clock me-1"></i>{$topic['in_date']}
                </span>
                <span class="text-muted">
                    <i class="bi bi-person me-1"></i>
                    <a href="#" id="topic_user_link" class="text-decoration-none">{$topic['user_id']}</a>
                </span>
            </div>
        </div>
        <div class="d-flex gap-2 flex-shrink-0">
            {if $running}
            <a href="#topic_reply_form" id="reply_button" class="btn btn-primary btn-sm">
                <span class="cn-text"><i class="bi bi-reply me-1"></i>回复</span><span class="en-text">Reply</span>
            </a>
            {/if}
            {if IsAdmin('contest', $contest['contest_id']) || (isset($proctorAdmin) && $proctorAdmin)}
                <div id="topic_status_button" 
                     data-topic-id="{$topic['topic_id']}" 
                     data-status="{$topic['public_show']}" 
                     data-field="public_show">
                    <!-- 状态按钮将由JavaScript动态生成 -->
                </div>
                {if IsAdmin('contest', $contest['contest_id'])}
                <button type="button" class="btn btn-outline-danger btn-sm delete_topic_button" topic_id="{$topic['topic_id']}">
                    <span class="cn-text"><i class="bi bi-trash me-1"></i>删除</span><span class="en-text">Delete</span>
                </button>
                {/if}
            {/if}
        </div>
    </div>
</div>
<!-- 提问内容 -->
<div class="mb-4">
    <div class="md_display_div topic_content_div p-3 border rounded">
        {$topic['content']|htmlspecialchars|nl2br}
    </div>
</div>

<!-- 回复列表 -->
<div id="reply_list_content_div" class="mb-4">
    {if count($replyList) > 0}
    <div class="mb-3">
        <h4 class="bilingual-inline">
            <span class="cn-text"><i class="bi bi-chat-dots me-2"></i>
            回复列表</span><span class="en-text">Replies</span>
            <span class="badge bg-primary ms-2"><?php echo count($replyList); ?></span>
        </h4>
    </div>
    {foreach($replyList as $index => $reply)}
    <div class="border-bottom pb-3 mb-3 reply_display_div">
        <div class="d-flex justify-content-between align-items-start mb-2">
            <div class="d-flex align-items-center">
                <div class="me-2">
                    <div class="hash-img-placeholder bg-light border rounded-circle d-flex align-items-center justify-content-center" data-hash-source="{$reply['user_id']}" data-hash-img style="width: 32px; height: 32px;">
                        <i class="bi bi-person text-muted" style="font-size: 16px;"></i>
                    </div>
                </div>
                <div>
                    <div class="d-flex align-items-center text-muted small">
                        <a href="#" class="reply-user-link text-decoration-none fw-bold text-primary me-2" data-user-id="{$reply['user_id']}">{$reply['user_id']}</a>
                        <span class="text-muted">{$reply['in_date']}</span>
                    </div>
                </div>
            </div>
            {if IsAdmin('contest', $contest['contest_id']) || (isset($proctorAdmin) && $proctorAdmin)}
            <button type="button" class="btn btn-sm btn-outline-danger delete_reply_button" topic_id="{$reply['topic_id']}">
                <span class="cn-text"><i class="bi bi-trash me-1"></i>删除</span><span class="en-text">Delete</span>
            </button>
            {/if}
        </div>
        <div class="reply-content ps-4 border-start border-2 border-primary w-100">
            {$reply['content']|htmlspecialchars|nl2br}
        </div>
    </div>
    {/foreach}
    {else}
    <div class="text-center py-4 text-muted">
        <i class="bi bi-chat-dots me-2"></i>
        <span class="bilingual-inline">暂无回复<span class="en-text">No Replies Yet</span></span>
    </div>
    {/if}
</div>
<input type="hidden" name="cid" id="contest_id_input" value="{$contest['contest_id']}" >
{include file="../../csgoj/view/public/code_highlight" /}
{include file="../../csgoj/view/public/js_hash_img" /}
{css href="__STATIC__/csgoj/contest/topic.css" /}

<script>
var reply_cnt = <?php echo count($replyList); ?>;
var topic_id = <?php echo $topic['topic_id']; ?>;
// 进入 topic_detail 再更新 reply_cnt cache
let reply_store_key = `topic_reply#${topic_id}`;
csg.store(reply_store_key, reply_cnt);

</script>

{if $running}
<!-- 回复表单 -->
<div class="mb-4">
    <h4 class="bilingual-inline mb-3">
        <span class="cn-text"><i class="bi bi-reply me-2"></i>
        添加回复</span><span class="en-text">Add Reply</span>
    </h4>
    <form role="form" id="topic_reply_form" action="/{$module}/{$contest_controller}/topic_reply_ajax" method="POST">
        <div class="mb-3">
            {if isset($contest_user) && $contest_user}
            <textarea
                    id="topic_reply_content"
                    class="form-control w-100"
                    rows="4"
                    name="topic_content"
                    spellcheck="false"
                    placeholder="请输入您的回复内容...Please enter your reply..."
                    {if isset($replyAvoid) && $replyAvoid == true} disabled="disabled"{/if}>{if isset($replyAvoid) && $replyAvoid == true}此提问已设为公开，为避免队伍间信息泄露，禁止回复。<span class="en-text">This topic has been changed to public, reply is forbidden to avoid information change between teams.</span>{/if}</textarea>
            {else}
                <textarea disabled class="form-control w-100" rows="4" placeholder="需登录比赛内账号才可回复 (Need to login with contest inner account to reply)">
                    需登录比赛内账号才可回复 (Need to login with contest inner account to reply)
                </textarea>
            {/if}
        </div>
        
        <input type="hidden" id="cid_input" class="form-control" name="cid" value="{$contest['contest_id']}">
        <input type="hidden" id="topic_id_input" class="form-control" name="topic_id" value="{$topic['topic_id']}">
        
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <button type="submit" {if !(isset($contest_user) && $contest_user)}disabled{/if}
            id='submit_button' class="btn btn-primary" {if isset($replyAvoid) && $replyAvoid == true} disabled="disabled"{/if}>
                <span class="cn-text"><i class="bi bi-send me-1"></i>提交</span><span class="en-text">Submit</span>
            </button>
            <button type="button" {if !(isset($contest_user) && $contest_user)}disabled{/if}
            id='clear_button' class="btn btn-outline-secondary">
                <span class="cn-text"><i class="bi bi-arrow-clockwise me-1"></i>清空</span><span class="en-text">Clear</span>
            </button>
            {if IsAdmin('contest', $contest['contest_id']) || (isset($proctorAdmin) && $proctorAdmin)}
            <button type="button" {if !(isset($contest_user) && $contest_user)}disabled{/if}
            id='quick_reply_button' class="btn btn-outline-info" {if isset($replyAvoid) && $replyAvoid == true} disabled="disabled"{/if}>
                <span class="cn-text"><i class="bi bi-lightning me-1"></i>快捷回复</span><span class="en-text">Quick Reply</span>
            </button>
            {/if}
        </div>
    </form>
</div>

<!-- 快捷回复Modal -->
<div class="modal fade" id="quickReplyModal" tabindex="-1" aria-labelledby="quickReplyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title bilingual-inline" id="quickReplyModalLabel">
                    <span class="cn-text"><i class="bi bi-lightning me-2"></i>
                    快捷回复</span><span class="en-text">Quick Reply</span>
                </h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row" id="quick-reply-list">
                    <!-- 快捷回复选项将由JavaScript动态生成 -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <span class="cn-text"><i class="bi bi-x-circle me-1"></i>取消</span><span class="en-text">Cancel</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    // 传递模板变量给JavaScript
    window.topicDetailConfig = {
        module: "{$module}",
        contest_controller: "{$contest_controller}",
        contest_id: "{$contest['contest_id']}",
        topic_id: "{$topic['topic_id']}",
        reply_cnt: <?php echo count($replyList); ?>,
        problem_id: "{$topic['problem_id']}",
        pid_abc: "{$topic['pid_abc']}",
        topic_user_id: "{$topic['user_id']}"
    };
</script>
{/if}
{js href="__STATIC__/csgoj/contest/topic.js" /}