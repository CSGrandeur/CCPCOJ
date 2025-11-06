{include file="../../csgoj/view/user/mail_header" /}

<div id="table_list_table_div">
    <table     id="table_list_table"
              data-toggle="table"
              data-url="mail_ajax?type={$type}"
              data-pagination="true"
              data-page-list="[15,25,50]"
              data-page-size="15"
              data-side-pagination="server"
              data-method="get"
              data-striped="true"
              data-sort-name="mail_id"
              data-sort-order="desc"
              data-pagination-v-align="bottom"
              data-pagination-h-align="left"
              data-pagination-detail-h-align="right"
              data-toolbar-align="left"
              data-toolbar="#topic_toolbar"
              data-query-params="queryParams"
              data-cookie="true"
              data-cookie-id-table="{$OJ_SESSION_PREFIX}mail-{$type}-list-{$user_id}"
              data-cookie-expire="5mi"
    >
        <thead>
        <tr>
            <th data-field="topic_id" data-align="center" data-valign="middle"  data-sortable="false" data-width="70">ID</th>
            <th data-field="title" data-align="left" data-valign="middle"  data-sortable="false" >Title</th>
            <th data-field="user_id" data-align="center" data-valign="middle"  data-sortable="false" data-width="100">{if $type=='inbox'}From{else /}To{/if}</th>
            <th data-field="reply" data-align="center" data-valign="middle"  data-sortable="false" data-width="70" >Reply</th>
            <th data-field="in_date" data-align="center" data-valign="middle"  data-sortable="false"  data-width="160">Time</th>
        </tr>
        </thead>
    </table>
</div>
<input
    type="hidden"
    id="table_list_page_information"
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
</script>