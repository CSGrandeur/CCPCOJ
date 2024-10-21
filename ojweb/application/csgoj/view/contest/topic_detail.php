<h2 title="{$topic['title']}" class="article-title">Topic {$topic['topic_id']}: {$topic['title']|htmlspecialchars}</h2>
<strong class="text-warning">Problem {$topic['problem_id']}</strong>
<p class="help-block">
{if($running) }
<a href="#topic_reply_form" id="reply_button" class="a_noline">
    <button type="button" class="btn btn-sm btn-default">Reply</button>
</a>
&nbsp;
{/if}
{if IsAdmin('contest', $contest['contest_id']) || isset($proctorAdmin) && $proctorAdmin }
    {if $topic['public_show'] == 0}
    <button type="button" field="public_show" topic_id="{$topic['topic_id']}" class="change_status btn btn-sm btn-warning" status="{$topic['public_show']}">Private</button>
    {else/}
    <button type="button" field="public_show" topic_id="{$topic['topic_id']}" class="change_status btn btn-sm btn-success" status="{$topic['public_show']}">Public</button>
    {/if}
    &nbsp;
    {if IsAdmin('contest', $contest['contest_id']) }
    <button type="button" class="btn btn-sm btn-danger delete_topic_button" topic_id="{$topic['topic_id']}" >Delete</button>
    &nbsp;
    {/if}
{/if}
    <span class="inline_span">Create Time: <span class="inline_span text-warning">{$topic['in_date']}</span></span>&nbsp;&nbsp;&nbsp;&nbsp;
    <span class="inline_span">Creator: <span class="inline_span text-default"><a href="{$userInfoUrlPrefix}{$topic['user_id']}">{$topic['user_id']}</a></span></span>&nbsp;&nbsp;&nbsp;&nbsp;
</p>
<article class="md_display_div topic_content_div">
    <p>
    {$topic['content']|htmlspecialchars|nl2br}
    </p>
    <hr/>
</article>

<div id="reply_list_content_div">
    {foreach($replyList as $reply)}
        <article class="md_display_div reply_display_div">
            <p class="help-block">
                {if IsAdmin('contest', $contest['contest_id']) || isset($proctorAdmin) && $proctorAdmin}
                <button type="button" class="btn btn-sm btn-danger delete_reply_button" topic_id="{$reply['topic_id']}" >Delete</button>
                &nbsp;&nbsp;
                {/if}
                <span class="inline_span">Time: <span class="inline_span text-warning">{$reply['in_date']}</span></span>&nbsp;&nbsp;&nbsp;&nbsp;
                <span class="inline_span">User: <span class="inline_span text-default"><a href="{$userInfoUrlPrefix}{$topic['user_id']}">{$reply['user_id']}</a></span></span>&nbsp;&nbsp;&nbsp;&nbsp;
            </p>
            <p>
            {$reply['content']|htmlspecialchars|nl2br}
            </p>
        </article>
    {/foreach}
</div>
<hr/>
<input type="hidden" name="cid" id="contest_id_input" value="{$contest['contest_id']}" >
{include file="../../csgoj/view/public/code_highlight" /}
<style type="text/css">
    .article-title
    {
        color: #222222;
    }
    .topic_content_div, .reply_display_div
    {
        padding: 5px 15px;
        margin-left: 0;
    }
    .reply_display_div
    {
        background-color: #fcfcfc;
        border-top: none;
        border-radius: 0;
        border-left: solid;
        border-right: solid;
        border-bottom: solid;
        border-color: #ddd;
        border-width: 1px;
        box-shadow: none;
        box-sizing: border-box;
    }
    #reply_list_content_div .reply_display_div:first-child
    {
        border-top: 1px #ddd solid;
        border-top-left-radius: 4px;
        -moz-border-radius-topleft: 4px ;
        border-top-right-radius: 4px;
        -moz-border-radius-topright: 4px ;
    }
    #reply_list_content_div .reply_display_div:last-child
    {
        border-bottom-left-radius: 4px;
        -moz-border-radius-bottomleft: 4px ;
        border-bottom-right-radius: 4px;
        -moz-border-radius-bottomright: 4px ;
    }
</style>


{if($running) }
<form role="form" id="topic_reply_form" action="/{$module}/{$contest_controller}/topic_reply_ajax" method="POST">

    <div class="form-group">
        <label for="topic_content">Reply Content：</label>
        <textarea
                id="topic_reply_content"
                class="form-control"
                rows="5"
                name="topic_content"
                spellcheck="false"
                placeholder="^_^"
                style="max-width:900px;"
                {if isset($replyAvoid) && $replyAvoid == true} disabled="disabled"{/if}>{if isset($replyAvoid) && $replyAvoid == true}This topic has been changed to public, reply is forbidden to avoid information change between teams.{/if}</textarea>
    </div>
    <input type="hidden" id="cid_input" class="form-control" name="cid" value="{$contest['contest_id']}" >
    <input type="hidden" id="topic_id_input" class="form-control" name="topic_id" value="{$topic['topic_id']}" >
    <div class="btn-group">
        <button type="submit" id='submit_button' class="btn btn-primary" {if isset($replyAvoid) && $replyAvoid == true} disabled="disabled"{/if}>Submit Reply</button>
        <button type="button" id='clear_button' class="btn btn-warning">Clear</button>
        <!-- 常用回复语句的选项框 -->
        <select id="reply-select" class="form-control" onchange="insertReply()" style="width: 300px">
            <option value="">常用回复语句 / Common Reply Phrases</option>
            <option value="无回复 / No response">无回复 / No response</option>
            <option value="请认真读题 / Please read the problem carefully">请认真读题 / Please read the problem carefully</option>
            <option value="请查看公告 / Please check the announcement">请查看公告 / Please check the announcement</option>
            <option value="请查看通知 / Please check the message">请查看通知 / Please check the message</option>
            <option value="是的 / Yes">是的 / Yes</option>
            <option value="不是 / No">不是 / No</option>
            <option value="存在 / Exists">存在 / Exists</option>
            <option value="不存在 / Not exist">不存在 / Not exist</option>
        </select>
    </div>
</form>

<script type="text/javascript">
    var submit_button = $('#submit_button');
    var reply_list_content_div = $('#reply_list_content_div');
    var cid = $('#contest_id_input').val();
    var topic_reply_content = $('#topic_reply_content');
    $(document).ready(function(){
        $('#topic_reply_form').validate({
            rules:{
                topic_content: {
                    required: true,
                    minlength: 2,
                    maxlength: 16384
                }
            },
            submitHandler: function(form)
            {
                submit_button.attr('disabled', true);
                $(form).ajaxSubmit(function(ret)
                {
                    if(ret.code == 1)
                    {
                        location.reload();
                        // var data = ret['data'];
                        // alertify.success(ret['msg']);
                        // var reply = "<article class='md_display_div reply_display_div'>\n" +
                        //     "<p class='help-block'>\n" +
                        //     "<span class='inline_span'>Time: <span class='inline_span text-warning'>" + data['in_date'] + "</span></span>&nbsp;&nbsp;&nbsp;&nbsp;\n" +
                        //     "<span class='inline_span'>User: <span class='inline_span text-default'>\n" +
                        //     "<a href='/" + data['module'] + "/user/userinfo?user_id=" + data['user_id'] + "'>" + data['user_id'] + "</a></span></span>&nbsp;&nbsp;&nbsp;&nbsp;\n" +
                        //     "</p>\n" +
                        //     data['content'] + "\n" +
                        //     "</article>";
                        // reply_list_content_div.append(reply);
                        // topic_reply_content.val('');
                        // button_delay(submit_button, 10, 'Submit');
                        // return true;
                    }
                    else
                    {
                        alertify.alert(ret["msg"]);
                        button_delay(submit_button, 3, 'Submit');
                    }
                    return false;
                });
                return false;
            }
        });
        $('#clear_button').click(() => {
            topic_reply_content.val("");
        })
    });
    $('#reply_button').on('click', function(){
        setTimeout("topic_reply_content.focus()", 100);
    });
    $('.delete_topic_button').on('click', function(){
        var button = $(this);
        alertify.confirm(
            "Related reply will also be deleted. Sure to delete?",
            function(){
                button.attr('disabled', true);
                $.get(
                    'topic_del_ajax',
                    {
                        'cid': cid,
                        'topic_id': button.attr('topic_id')
                    },
                    function(ret)
                    {
                        if(ret['code'] == 1)
                        {
                            alertify.success(ret['msg']);
                            setTimeout(function(){location.href='topic_list?cid=' + cid}, 500);
                            button_delay(button, 3, 'Delete');
                        }
                        else
                        {
                            alertify.error(ret['msg']);
                            button_delay(button, 3, 'Delete');
                        }
                    },
                    'json'
                );
            },
            function()
            {
                alertify.message("Canceled");
            }
        );
    });
    $('.delete_reply_button').on('click', function(){
        var button = $(this);
        alertify.confirm(
            "Sure to delete?",
            function(){
                button.attr('disabled', true);
                $.get(
                    'topic_del_ajax',
                    {
                        'cid': cid,
                        'topic_id': button.attr('topic_id')
                    },
                    function(ret)
                    {
                        if(ret['code'] == 1)
                        {
                            alertify.success(ret['msg']);
                            button.parents('article').remove();
                            button_delay(button, 3, 'Delete');
                        }
                        else
                        {
                            alertify.error(ret['msg']);
                            button_delay(button, 3, 'Delete');
                        }
                    },
                    'json'
                );
            },
            function()
            {
                alertify.message("Canceled");
            }
        );
    });
    $("textarea").on('keydown',function(e){
        if(e.keyCode == 9){
            e.preventDefault();
            var indent = '    ';
            var start = this.selectionStart;
            var end = this.selectionEnd;
            var selected = window.getSelection().toString();
            selected = indent + selected.replace(/\n/g,'\n'+indent);
            this.value = this.value.substring(0,start) + selected + this.value.substring(end);
            this.setSelectionRange(start+indent.length,start+selected.length);
        }
    });
    // **************************************************

    // 将选中的回复语句填入 #topic_reply_content
    function insertReply() {
        var replySelect = document.getElementById('reply-select');
        var selectedReply = replySelect.value;

        if (selectedReply) {
            topic_reply_content.val(selectedReply);
            replySelect.value = ""; // 重置选择框
        }
    }
</script>
{/if}
{include file="../../csgoj/view/contest/topic_change_status" /}