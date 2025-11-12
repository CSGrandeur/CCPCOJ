<?php $controller = strtolower(request()->controller()); ?>
<h2>
    Data Download - 
    {$problem['problem_id_show']}
    {if array_key_exists("show_real_id", $problem) }
        (<a href="__OJ__/problemset/problem?pid={$problem['problem_id']}">{$problem['problem_id']}</a>)
    {/if}
    : {$problem['title']}
</h2>
<span class="inline_span">Time Limit:     <span class="inline_span text-warning">{$problem['time_limit']} Sec</span>&nbsp;&nbsp;&nbsp;&nbsp;</span>
<span class="inline_span">Memory Limit: <span class="inline_span text-warning">{$problem['memory_limit']} Mb</span>&nbsp;&nbsp;&nbsp;&nbsp;</span>
<br/>

Only <strong class="text-danger">one pair</strong>  of test data can be downloaded <strong class="text-danger">every {$downloadWaitTime} minutes</strong>, please consider carefully!!!

{if $problem['spj'] == 1 }
<span class="text-red">SpecialJudge</span>
{/if}
<hr/>
<input type="hidden" id="contest_status" value="{$contestStatus}">


<div style="max-width:1140px; margin:auto;">
    {if(isset($attach_notify))}<span class="alert alert-info" style="display: inline-block;">{$attach_notify}</span>{/if}

    <table
        class="bootstraptable_refresh_local"
        id="testdata_table"
        data-toggle="table"
        data-url="testdata_ajax?cid={$contest['contest_id']}&pid={$apid}"
        data-toolbar="#upload_toolbar"
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
            <th data-field="file_serial" data-align="center" data-valign="middle" data-sortable="true" data-width="60" data-formatter="AutoId">ID</th>
            <th data-field="file_name" data-align="left" data-valign="middle" data-sortable="true" data-formatter="FileDownload">Name</th>
            <th data-field="file_size" data-align="right" data-valign="middle" data-sortable="true" data-width="120">Size(Kb)</th>
            <th data-field="file_type" data-align="center" data-valign="middle" data-width="120">File Type</th>
        </tr>
        </thead>
    </table>
</div>
<input type="hidden" id="page_info" module="{$module}" controller="{$controller}">
<script>
let page_info = $('#page_info');
let module_name = page_info.attr('module');
let controller_name = page_info.attr('controller');
function FileDownload(value, row, index) {
    return `<a href='/${module_name}/${controller_name}/testdata_download?cid={$contest['contest_id']}&pid={$apid}&filename=${value}' class='testdown'>${value}</a>`;
}
</script>

{if !IsAdmin()}
<script>
    
$('#testdata_table').on('load-success.bs.table', function(){
    $('.testdown').unbind('click').click(function(e){
        let last_test_download_num = localStorage.getItem('last_test_download_num');
        let last_test_download_time = localStorage.getItem('last_test_download_time');
        if(typeof(last_test_download_num) == 'undefined'){
            last_test_download_num = 0;
        } else {
            last_test_download_num = parseInt(last_test_download_num);
        }
        let DOWNLOAD_WAIT_TIME = 70;
        if(typeof(last_test_download_time) != 'undefined')
        {
            last_test_download_time = parseFloat(last_test_download_time);
            let now = new Date().getTime() / 1000;
            if(last_test_download_num >= 2 && now - last_test_download_time < DOWNLOAD_WAIT_TIME) {
				alertify.error("Don't download test data too frequently. " + parseInt(DOWNLOAD_WAIT_TIME - now  + last_test_download_time) + " seconds left.");
                e.preventDefault();
                return;
            }
            if(now - last_test_download_time > DOWNLOAD_WAIT_TIME) {
                last_test_download_num = 0;
            }
            last_test_download_num ++;
        }
        localStorage.setItem('last_test_download_time', new Date().getTime() / 1000, {expires: 1});
        localStorage.setItem('last_test_download_num', last_test_download_num, {expires: 1});
    })
});
</script>
{/if}
{js href="__JS__/refresh_in_table.js" /}