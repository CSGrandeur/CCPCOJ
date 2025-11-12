<div class="page-header">
    <h1>Contest Summary Export</h1>
</div>
<div class="container">
    
<div class="row">
    <div class="col-md-12 col-lg-4 col-sm-12">
        <form id="contest_summary_form" method='post' action="__ADMIN__/contestsummary/contest_summary_ajax">
            <div class="form-group">
                <label for="cid_list">Contest ID List (Separated by "\n") ：</label>
                <textarea class="form-control" placeholder="1001&#10;1002&#10;..." rows="20" id="cid_list" name="cid_list" style="max-width:400px;"></textarea>
            </div>

            <button type="submit" id="submit_button" class="btn btn-primary">Export</button>
        </form>
    </div>
             
    <div class="col-md-12 col-lg-8 col-sm-12">           
        <div style="margin:auto;">
        
            <table
                class="bootstraptable_refresh_local"
                id="contest_summary_file_table"
                data-toggle="table"
                data-url="__ADMIN__/contestsummary/summary_file_list_ajax"
                data-pagination="true"
                data-page-list="[10, 25, 50]"
                data-page-size="50"
                data-method="get"
                data-search="true"
                data-search-align="left"
                data-side-pagination="client"
                data-unique-id="file_name"
                data-pagination-v-align="bottom"
                data-pagination-h-align="left"
                data-pagination-detail-h-align="right"
            >
                <thead>
                <tr>
                    <th data-field="file_serial" data-align="center" data-valign="middle" data-sortable="true" data-width="60" data-formatter="AutoId">Idx</th>
                    <th data-field="file_name" data-align="left" data-valign="middle" data-sortable="true" data-formatter="FilenameFormatter">Name</th>
                    <th data-field="file_size" data-align="right" data-valign="middle" data-sortable="true" data-width="120">Size(Kb)</th>
                    <th data-field="file_lastmodify" data-align="center" data-valign="middle" data-sortable="true" data-width="180">Last Modify</th>
                    <th data-field="file_delete" data-align="center" data-valign="middle" data-width="90" data-formatter="FileDeleteFormatter">Delete</th>
                </tr>
                </thead>
            </table>
        </div>
        {js href="__JS__/refresh_in_table.js" /}
    </div>
</div>
<script type="text/javascript">
let contest_summary_file_table = $('#contest_summary_file_table');
function AutoId(value, row, index) {
    return index + 1;
}
function FilenameFormatter(value, row, index) {
    return "<a href='__ADMIN__/contestsummary/download?file=" + value + "'>" + value + "</a>";
}
function FileDeleteFormatter(value, row, index) {
    return "<button class='delete_button btn btn-danger'>Delete</button>";
}
var submit_button = $('#submit_button');

$(document).ready(function()
{
    
	contest_summary_file_table.on('click-cell.bs.table', function(e, field, td, row){
		if(field == 'file_delete')
		{
			alertify.confirm
			(
				"Sure to delete this file?",
				function()
				{
					$.get(
						"__ADMIN__/contestsummary/delete",
						{
							'file':  row['file_name']
						},
						function(ret){
							if(ret['code'] == 1)
							{
								alertify.success(ret['msg']);
								contest_summary_file_table.bootstrapTable('removeByUniqueId', row['file_name']);
							}
							else
							{
								alertify.error(ret['msg']);
							}
							return false;
						}
					);
				},
				function()
				{
				}
			);
		}
    });
    $('#contest_summary_form').validate({
        rules:{
            cid_list:{
                required: true,
                minlength: 1,
                maxlength: 200
            }
        },
        submitHandler: function(form)
        {
            var cid_list = $('#cid_list').val().trim();
            if(!/^\d{1,10}(\n\d{1,10}){0,50}$/.test(cid_list))
                alertify.alert("Contest IDs should be separated by ',' and be limited Positive Integers.");
            else
                SubmitExport(form);
        }
    });
});
function SubmitExport(form)
{
    alertify.confirm("Contests export may take a long time."+
        "The process would stop if execute time exceed <strong class='text-danger'>180s</strong>.<br/>" +
        "Are you ready?",
        function(){
            DoExport(form);
        },
        function(){
            alertify.message("Canceled");
        }
    );
}
function DoExport(form)
{
    submit_button.attr('disabled', true);
    var button_text = submit_button.text();
    submit_button.text('Running...');
    $(form).ajaxSubmit({
        success: function(ret)
        {
            if(ret["code"] == 1)
            {
                contest_summary_file_table.bootstrapTable('refresh');
                alertify.alert(ret['msg'], function(){
                    var tmpInterval = setInterval(function(){
                        $("#attachfile").fadeOut(100).fadeIn(100);
                    },200);
                    setTimeout(function(){clearInterval(tmpInterval);$("#attachfile").fadeIn(); }, 1000);
                });
            }
            else
            {
                alertify.alert(ret['msg']);
            }
            button_delay(submit_button, 3, 'Export');
            return false;
        }
    });
}
</script>