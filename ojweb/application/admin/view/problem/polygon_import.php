{include file="../../csgoj/view/public/js_zip" /}
{js href="__JS__/polygon_parse.js" /}


<div id="polygon_toolbar">
    <div class="form-inline fake-form" role="form">
        <button class="btn btn-primary" id="polygon_convert_btn">Parse Polygon Zip</button>
        <button class="btn btn-success" id="download_selected_btn">Pack Selected To CSGOJ</button>
        <button class="btn btn-info" id="help_btn" data-toggle="modal" data-target="#helpModal">Help</button>
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

<!-- Help Modal -->
<div class="modal fade" id="helpModal" tabindex="-1" role="dialog" aria-labelledby="helpModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="helpModalLabel">Help</h4>
            </div>
            <div class="modal-body">
                <p><a href="https://polygon.codeforces.com" target="_blank">Polygon</a>导出的zip中，每道题目需要有如下结构:</p>
                <p>You need to prepare this structure for each problem:</p>
                <div class="directory">
                    <ul>
                        <li class="file">problem.xml</li>
                        <li class="file">check.cpp</li>
                        <li class="folder">statements
                            <ul>
                                <li class="folder">lang
                                    <ul>
                                        <li class="file">problem-properties.json</li>
                                    </ul>
                                </li>
                            </ul>
                        </li>
                        <li class="folder">tests
                            <ul>
                                <li class="file">[data_name] (as input)</li>
                                <li class="file">[data_name].a (as output)</li>
                            </ul>
                        </li>
                    </ul>
                </div>
                <li>题目解析后，可勾选并打包为CSGOJ支持的导入格式。<p>After parsing the problem, you can select and pack it into a format supported by CSGOJ.</p></li>
                <li>Polygon 默认每道题带有check.cpp，对应CSGOJ的Special Judge，对应评测数据目录的 tpj.cc（基于testlib.h），且题目设置中 Special Judge 处于勾选状态。如不需要，可关掉对应题目的开关，打包时将不包含tpj.cc。
                <p>By default, each problem in Polygon includes check.cpp, which corresponds to the Special Judge in CSGOJ and the tpj.cc in the test data directory (based on testlib.h). The Special Judge option is checked in the problem settings. If not needed, you can turn off the switch for the corresponding problem, and tpj.cc will not be included in the package.</p>

                </li>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

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

<style>
    .directory {
        font-family: Arial, sans-serif;
        line-height: 1.5;
    }
    .directory ul {
        list-style-type: none;
        padding-left: 20px;
    }
    .directory li {
        margin: 5px 0;
    }
    .directory .folder::before {
        content: "📁 ";
    }
    .directory .file::before {
        content: "📄 ";
    }
</style>