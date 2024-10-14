<table
        id="contest_list_table"
        data-toggle="table"
          data-url="/{$module}/{$contest_controller}/contest_list_ajax"
          data-pagination="true"
          data-page-list="[25,50,100]"
          data-page-size="25"
          data-side-pagination="client"
          data-method="get"
          data-striped="true"
          data-search="true"
          data-search-align="left"
          data-sort-name="contest_id"
          data-sort-order="desc"
          data-pagination-v-align="bottom"
          data-pagination-h-align="left"
          data-pagination-detail-h-align="right"
          data-classes="table-no-bordered table table-hover"
>
    <thead>
    <tr>
        <th data-field="contest_id" data-align="center" data-valign="middle"  data-sortable="false" data-width="55">ID</th>
        <th data-field="title" data-align="left" data-valign="middle"  data-sortable="false" data-formatter="FormatterContestTitle">Title</th>
        <th data-field="status" data-align="center" data-valign="middle"  data-sortable="false" data-width="80" data-formatter="FormatterContestStatus">Status</th>
        <th data-field="start_time" data-align="center" data-valign="middle"  data-sortable="false" data-width="180">Start</th>
        <th data-field="end_time" data-align="center" data-valign="middle"  data-sortable="false" data-width="180">End</th>
        <th data-field="kind" data-align="center" data-valign="middle"  data-sortable="false" data-width="60" data-formatter="FormatterContestKind">Type</th>
    </tr>
    </thead>
</table>
<input type="hidden" id="page_info" page_module="{$module}" page_controller="{$contest_controller}" >
<script type="text/javascript">
let page_info = $('#page_info');
var page_module = page_info.attr('page_module');
function FormatterContestTitle(value, row, index, field) {
    return "<a href='/" + page_module + "/contest/problemset?cid=" + row['contest_id'] + "'>" + value + "</a>";
}
function FormatterContestStatus(value, row, index, field) {
    return value == -1 ? "<strong class='text-success'>Coming</strong>" : (value == 2 ? "<strong class='text-info'>Ended</strong>" : "<strong class='text-danger'>Running</strong>");
}
function FormatterContestKind(value, row, index, field) {
    if(row['private'] % 10 == 0) {
        return row['has_pass'] ? "<strong class='text-warning'>Encrypted</strong>" : "<strong class='text-success'>Public</strong>";
    } else if(row['private'] % 10 == 1) {
        return "<strong class='text-danger'>Private</strong>";
    } else if(row['private'] % 10 == 2) {
        return "<strong class='text-primary'>Standard</strong>";
    } else if(row['private'] % 10 == 5) {
        return "<strong class='text-info'>Exam</strong>";
    }
}
    $(window).keydown(function(e) {
        if (e.keyCode == 116 && !e.ctrlKey) {
            if(window.event){
                try{e.keyCode = 0;}catch(e){}
                e.returnValue = false;
            }
            e.preventDefault();
            $('#contest_list_table').bootstrapTable('refresh');
        }
    });
</script>