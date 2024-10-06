{include file="../../csgoj/view/public/js_zip" /}
{js href="__JS__/polygon_parse.js" /}

<div id="polygon_toolbar">
    <div class="form-inline fake-form" role="form">
        <button class="btn btn-info" id="polygon_convert_btn">Parse Polygon Zip</button>
        <button class="btn btn-success" id="download_selected_btn">Pack Selected To CSGOJ</button>
    </div>
</div>
<table
    class="bootstraptable_refresh_local"
    id="polygon_parse_table"
    data-toggle="table"
    data-pagination="true"
    data-toolbar="#polygon_toolbar"
    data-page-size="100"
    data-side-pagination="client"
    data-method="get"
    data-search="true"
    data-pagination-v-align="both"
    data-pagination-h-align="left"
    data-pagination-detail-h-align="right"
    data-search-align="right"
    data-click-to-select="true"
    data-checkbox-header="true">
    <thead>
        <tr>
            <th data-field="state" data-checkbox="true"></th>
            <th data-field="idx" data-align="left" data-valign="middle" data-width="55" data-formatter="FormatterIdx">Idx</th>
            <th data-field="title" data-align="left" data-valign="middle" data-sortable="false">Title</th>
            <th data-field="author" data-align="left" data-valign="middle" data-sortable="false" data-width="80">Author</th>
            <th data-field="testdata" data-align="left" data-valign="middle" data-sortable="false">Data</th>
            <th data-field="spj" data-align="left" data-valign="middle" data-width="55" data-sortable="false" title="Wheter to add 'tpj.cc' to problem">SpecialJudge</th>
            <th data-field="hash" data-align="left" data-valign="middle" data-sortable="false">Hash</th>
        </tr>
    </thead>
</table>

<script>
    let polygon_parse_table = null;
    $(document).ready(() => {
        polygon_parse_table = $("#polygon_parse_table");
        $('#polygon_convert_btn').click(() => {
            let fileInput = $('<input type="file" accept=".zip">');
            fileInput.on('change', async function(event) {
                var file = event.target.files[0];
                if (file && file.name.endsWith('.zip')) {
                    const tableData = await HandlePolygonZipFile(file);
                    polygon_parse_table.bootstrapTable("load", tableData);
                    // 初始化 Bootstrap Switch
                    $('.spj-switch').bootstrapSwitch();
                } else {
                    alertify.error('Only .zip');
                }
            });

            fileInput.click();
        });

        $('#download_selected_btn').click(() => {
            downloadSelectedProblems();
        });
        polygon_parse_table.on('click-cell.bs.table', function(e, field, td, row) {
            if (field === 'spj') {
                e.stopPropagation();
            }
        });
    })
</script>