<div class="alert alert-info mb-4">
    <div class="d-flex align-items-start">
        <i class="bi bi-info-circle me-2 mt-1"></i>
        <div>
            <div class="mb-1">
                您只能查看 <strong class="text-warning">自己的</strong> 提问和 <strong class="text-warning">管理员设为公开的</strong> 提问。
            </div>
            <div class="text-muted small">
                You can only see <strong class="text-warning">your own</strong> topics and the topics <strong class="text-warning">set to public by administrator</strong>.
            </div>
        </div>
    </div>
</div>

<div id="topic_toolbar" class="table-toolbar">
    <div class="d-flex align-items-center gap-2" role="form">
        <button id="topic_refresh" type="button" class="btn btn-outline-secondary toolbar-btn" title="刷新 (Refresh)">
            <i class="bi bi-arrow-clockwise"></i>
        </button>
        <button id="topic_clear" type="button" class="btn btn-outline-secondary toolbar-btn" title="清空筛选条件 (Clear)">
            <i class="bi bi-eraser"></i>
        </button>
        <button id="topic_mark_all_read" type="button" class="btn btn-outline-primary bilingual-button toolbar-btn" title="全部已读 (Mark All Read)" disabled>
            <span class="cn-text"><i class="bi bi-check-all"></i> 全部已读</span><span class="en-text">Mark All Read</span>
        </button>
        <div class="toolbar-group">
            <input id="topic_id_input" name="topic_id" class="form-control toolbar-input topic_filter" type="text" title="提问ID (Topic ID)" placeholder="Topic ID">
        </div>
        <div class="toolbar-group">
            <input id="user_id_input" name="user_id" class="form-control toolbar-input topic_filter" type="text" title="账号 (UserID)" placeholder="User">
        </div>
        <div class="toolbar-group">
            <input id="title_input" name="title" class="form-control toolbar-input topic_filter" type="text" title="标题 (Title)" placeholder="Title">
        </div>
        <div class="toolbar-group">
            <select id='topic-apid' name="apid" class="form-select toolbar-select topic_filter" title="题号 (Problem ID)">
                <option value="-1">
                    All
                </option>
                {foreach($abc2id as $k => $val) }
                <option value="{$k}">
                    {$k}
                </option>
                {/foreach}
            </select>
        </div>
    </div>
</div>
<div id="topic_table_list_table_div">
<table     id="topic_table_list_table"
          data-toggle="table"
          data-url="/{$module}/{$contest_controller}/{$action}_ajax?cid={$contest['contest_id']}"
          data-pagination="true"
          data-page-list="[20,50,100]"
          data-page-size="20"
          data-side-pagination="client"
          data-method="get"
          data-striped="true"
          data-sort-name="topic_id"
          data-sort-order="desc"
          data-pagination-v-align="bottom"
          data-pagination-h-align="left"
          data-pagination-detail-h-align="right"
          data-toolbar-align="left"
          data-toolbar="#topic_toolbar"
          data-filter-control="true"
          data-filter-show-clear="true"

>
    <thead>
    <tr>
        <th data-field="topic_id" data-align="center" data-valign="middle"  data-sortable="false" data-width="70">ID<span class="en-text">ID</span></th>
        <th data-field="pid_abc" data-align="center" data-valign="middle"  data-sortable="false" data-width="70" data-formatter="FormatterTopicProblem">题目<span class="en-text">Problem</span></th>
        <th data-field="title" data-align="left" data-valign="middle"  data-sortable="false" data-formatter="FormatterTopicTitle">标题<span class="en-text">Title</span></th>
        <th data-field="user_id" data-align="center" data-valign="middle"  data-sortable="false" data-width="100" data-formatter="FormatterTopicUser">用户<span class="en-text">User</span></th>
        {if IsAdmin('contest', $contest['contest_id']) }
        <th data-field="public_show" data-align="center" data-valign="middle"  data-sortable="false" data-width="70" data-formatter="FormatterTopicStatus">状态<span class="en-text">Status</span></th>
        {/if}
        <th data-field="reply" data-align="center" data-valign="middle"  data-sortable="false" data-width="70" data-formatter="FormatterTopicReply">回复<span class="en-text">Reply</span></th>
        <th data-field="in_date" data-align="center" data-valign="middle"  data-sortable="false"  data-width="80" data-formatter="FormatterTime">时间<span class="en-text">Time</span></th>
    </tr>
    </thead>
</table>
</div>
<script type="text/javascript">
    // 传递模板变量给JavaScript - 使用命名空间避免全局污染
    window.TopicListConfig = {
        contest_id: "{$contest['contest_id']}",
        user_id: "{$contest_user}",
        module: "{$module}",
        contest_controller: "{$contest_controller}",
        action: "{$action}"
    };
</script>
{css href="__STATIC__/csgoj/contest/topic.css" /}
{js href="__STATIC__/csgoj/contest/topic.js" /}

<style type="text/css">
</style>

