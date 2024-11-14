You can only see <strong class="text-warning">your own</strong> topics and the topics <strong class="text-warning">set to public by administrator</strong>.
<div id="topic_toolbar">
    <div class="form-inline fake-form" role="form">
        <button id="topic_refresh" type="submit" class="btn btn-default"><i class="glyphicon glyphicon-refresh icon-refresh"></i></button>
        <button id="topic_ok" type="submit" class="btn btn-default">Search</button>
        <div class="form-group">
            <span>Problem ID:</span>
            <select id='topic-apid' name="apid" class="form-control" style="width:70px;" >
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
        <div class="form-group">
            <span>User ID:</span>
            <input id="user_id_input" name="user_id" class="form-control" type="text" style="width:150px;" >
        </div>
        <div class="form-group">
            <span>Topic ID:</span>
            <input id="topic_id_input" name="topic_id" class="form-control" type="text" style="width:80px;" >
        </div>
        <div class="form-group">
            <span>Title:</span>
            <input id="title_input" name="title" class="form-control" type="text" >
        </div>
    </div>
</div>
<div id="table_list_table_div">
<table     id="table_list_table"
          data-toggle="table"
          data-url="/{$module}/{$contest_controller}/{$action}_ajax?cid={$contest['contest_id']}"
          data-pagination="true"
          data-page-list="[15]"
          data-page-size="15"
          data-side-pagination="server"
          data-method="get"
          data-striped="true"
          data-sort-name="topic_id"
          data-sort-order="desc"
          data-pagination-v-align="bottom"
          data-pagination-h-align="left"
          data-pagination-detail-h-align="right"
          data-toolbar-align="left"
          data-toolbar="#topic_toolbar"
          data-query-params="queryParams"
          data-cookie="true"
          data-cookie-id-table="{$OJ_SESSION_PREFIX}topic-list-{$contest['contest_id']}-{$contest_user}"
          data-cookie-expire="5mi"
>
    <thead>
    <tr>
        <th data-field="topic_id" data-align="center" data-valign="middle"  data-sortable="false" data-width="70">ID</th>
        <th data-field="problem_id" data-align="center" data-valign="middle"  data-sortable="false" data-width="70">Problem</th>
        <th data-field="title" data-align="left" data-valign="middle"  data-sortable="false" >Title</th>
        <th data-field="user_id" data-align="center" data-valign="middle"  data-sortable="false" data-width="100">User</th>
        {if IsAdmin('contest', $contest['contest_id']) }
        <th data-field="public_show" data-align="center" data-valign="middle"  data-sortable="false" data-width="70" >Status</th>
        {/if}
        <th data-field="reply" data-align="center" data-valign="middle"  data-sortable="false" data-width="70" data-formatter="FormatterTopicReply">Reply</th>
        <th data-field="in_date" data-align="center" data-valign="middle"  data-sortable="false"  data-width="160">Time</th>
    </tr>
    </thead>
</table>
</div>
<input
    type="hidden"
    id="table_list_page_information"
    cid="{$contest['contest_id']}"
    user_id="{$contest_user}"
>
<script type="text/javascript">
    //table related
    var table_list_table_div = $('#table_list_table_div');
    var table_list_table = $('#table_list_table');
    var table_list_page_information = $('#table_list_page_information');
    var user_id = table_list_page_information.attr('user_id');
    var cid = table_list_page_information.attr('cid');
    var topic_refresh = $('#topic_refresh');
    var topic_ok = $('#topic_ok');
    var topic_toolbar = $('#topic_toolbar');
    var lastQuery = [];
    $(window).keydown(function(e) {
        if (e.keyCode == 116 && !e.ctrlKey) {
            if(window.event){
                try{e.keyCode = 0;}catch(e){}
                e.returnValue = false;
            }
            e.preventDefault();
            RefreshTable();
        }
    });
    function RefreshTable()
    {
        topic_toolbar.find('input[name]').each(function () {
            $(this).val(lastQuery[$(this).attr('name')]);
        });
        topic_toolbar.find('select[name]').each(function () {
            $(this).val(lastQuery[$(this).attr('name')]);
        });
        table_list_table.bootstrapTable('refresh');

    }
    function queryParams(params) {
        topic_toolbar.find('input[name]').each(function () {
            params[$(this).attr('name')] = $(this).val();
            lastQuery[$(this).attr('name')] = $(this).val();
        });
        topic_toolbar.find('select[name]').each(function () {
            params[$(this).attr('name')] = $(this).val();
            lastQuery[$(this).attr('name')] = $(this).val();
        });
        return params;
    }
    $('.fake-form').on('keypress', function(e){
        // it'ts not a real form, so overload 'enter' to take effect.
        if(event.keyCode == 13){
            table_list_table.bootstrapTable('refresh', {pageNumber: 1});
        }
    });
    topic_ok.on('click', function () {
        table_list_table.bootstrapTable('refresh', {pageNumber: 1});
    });
    topic_refresh.on('click', function(){
        RefreshTable();
    });
    function FormatterTopicReply(value, row, index, field) {
        let reply_store_key = `topic_reply#${row.topic_id}`;
        let reply_cache = csg.store(reply_store_key);
        if(reply_cache === null || reply_cache != value) {
            return `<strong style="color:red;">${value}</strong>`;
        }
        return value;
    }
</script>
{include file="../../csgoj/view/contest/topic_change_status" /}