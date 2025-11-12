{include file="../../csgoj/view/public/js_zip" /}
{js href="__JS__/overlay.js" /}
{js href="__JS__/polygon_parse.js" /}

<h1 class="page-title bilingual-inline">Polygon 解析工具<span class="en-text">Polygon Parser Tool</span></h1>

<div id="polygon_toolbar" class="table-toolbar mb-3">
    <div class="d-flex align-items-center gap-2" role="form">
        <button class="btn btn-primary bilingual-button" id="polygon_convert_btn">
            <span class="cn-text"><i class="bi bi-file-earmark-zip"></i> 解析Polygon压缩包</span><span class="en-text">Parse Polygon Zip</span>
        </button>
        <button class="btn btn-success bilingual-button" id="download_selected_btn">
            <span class="cn-text"><i class="bi bi-box-arrow-down"></i> 打包选中题目</span><span class="en-text">Pack Selected To CSGOJ</span>
        </button>
        <button class="btn btn-info bilingual-button" id="help_btn" data-bs-toggle="modal" data-bs-target="#helpModal">
            <span class="cn-text"><i class="bi bi-question-circle"></i> 帮助</span><span class="en-text">Help</span>
        </button>
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
    data-pagination-v-align="bottom"
    data-pagination-h-align="left"
    data-pagination-detail-h-align="right"
    data-search-align="right"
    data-checkbox-header="true">
    <thead>
        <tr>
            <th data-field="state" data-checkbox="true"></th>
            <th data-field="idx" data-align="left" data-valign="middle" data-width="55" data-formatter="FormatterProParserIdx">索引<span class="en-text">Idx</span></th>
            <th data-field="title" data-align="left" data-valign="middle" data-sortable="false" data-formatter="FormatterProParserTitle">标题<span class="en-text">Title</span></th>
            <th data-field="author" data-align="left" data-valign="middle" data-sortable="false" data-formatter="FormatterProParserAuthor">作者<span class="en-text">Author</span></th>
            <th data-field="testdata" data-align="left" data-valign="middle" data-sortable="false" data-formatter="FormatterProParserTestData">数据<span class="en-text">Data</span></th>
            <th data-field="spj" data-align="left" data-valign="middle" data-width="120" data-sortable="false" data-formatter="FormatterProParserSpj" title="是否添加 'tpj.cc' 到题目">特判<span class="en-text">SpecialJudge</span></th>
            <th data-field="hash" data-align="left" data-valign="middle" data-sortable="false" data-formatter="FormatterProParserHash">哈希<span class="en-text">Hash</span></th>
        </tr>
    </thead>
</table>

<!-- Help Modal -->
<div class="modal fade" id="helpModal" tabindex="-1" aria-labelledby="helpModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title bilingual-inline" id="helpModalLabel">帮助<span class="en-text">Help</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="关闭 Close"></button>
            </div>
            <div class="modal-body">
                <p >
                    <a href="https://polygon.codeforces.com" target="_blank">Polygon</a> 导出的 zip 中，每道题目需要有如下结构：
                    <span class="en-text">You need to prepare files with this structure for each problem:</span>
                </p>
                <div class="directory">
                    <ul>
                        <li class="file">problem.xml</li>
                        <li class="file">check.cpp</li>
                        <li class="folder">statements
                            <ul>
                                <li class="folder">[language]
                                    <ul>
                                        <li class="file">problem-properties.json</li>
                                        <li class="img">[picture_name].[picture_extend] <span class="text-muted">(like a.png)</span></li>
                                        <li class="img">[picture_name].[picture_extend] <span class="text-muted">...</span></li>
                                    </ul>
                                </li>
                            </ul>
                        </li>
                        <li class="folder">tests
                            <ul>
                                <li class="file">[data_name] <span class="text-muted">(as input)</span></li>
                                <li class="file">[data_name].a <span class="text-muted">(as output)</span></li>
                            </ul>
                        </li>
                    </ul>
                </div>
                <ul>
                    <li >
                        题目解析后，可勾选并打包为 CSGOJ 支持的导入格式。
                        <span class="en-text">After parsing the problem, you can select and pack it into a format supported by CSGOJ.</span>
                    </li>
                    <li >
                        Polygon 默认每道题带有 check.cpp，对应 CSGOJ 的 Special Judge，对应评测数据目录的 tpj.cc（基于 testlib.h），且题目设置中 Special Judge 处于勾选状态。如不需要，可关掉对应题目的开关，打包时将不包含 tpj.cc。
                        <span class="en-text">By default, each problem in Polygon includes check.cpp, which corresponds to the Special Judge in CSGOJ and the tpj.cc in the test data directory (based on testlib.h). The Special Judge option is checked in the problem settings. If not needed, you can turn off the switch for the corresponding problem, and tpj.cc will not be included in the package.</span>
                    </li>
                    <li >
                        插图转换基于 <code>\includegraphics</code> 匹配，不保证全部成功，文件名需为数字、字母、下划线、减号，后跟常见图像扩展名，<span class="text-danger">不支持 PDF 格式的插图</span>，导入后注意检查。
                        <span class="en-text">Image conversion is based on <code>\includegraphics</code> matching and is not guaranteed to be fully successful. The file name should consist of numbers, letters, underscores, hyphens, and common image extensions. <span class="text-danger">PDF format images are not supported</span>. Please check after import.</span>
                    </li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary bilingual-inline" data-bs-dismiss="modal">关闭<span class="en-text">Close</span></button>
            </div>
        </div>
    </div>
</div>

{include file="../../csgoj/view/public/base_csg_switch" /}
<script>
    // ========================================
    // Polygon Parser 表格 Formatter 函数
    // ========================================
    
    // 索引 formatter
    function FormatterProParserIdx(value, row, index, field) {
        return value || (index + 1);
    }
    
    // 标题 formatter - 带下载链接
    function FormatterProParserTitle(value, row, index, field) {
        if (!value) return '-';
        const pid = row.idx || (index + 1);
        return `<a href="#" onclick="DownloadPro(${pid}); return false;" class="text-decoration-none text-primary" title="下载题目 (Download Problem)">${value}</a>`;
    }
    
    // 作者 formatter
    function FormatterProParserAuthor(value, row, index, field) {
        return value || '-';
    }
    
    // 测试数据 formatter - 带下载链接
    function FormatterProParserTestData(value, row, index, field) {
        if (!value) return '-';
        const pid = row.idx || (index + 1);
        return `<a href="#" onclick="handleDownloadTestData(${pid}); return false;" class="text-decoration-none text-success" title="下载测试数据 (Download Test Data)">${value}</a>`;
    }
    
    // 特判 formatter - 根据 spj 类型显示不同内容
    function FormatterProParserSpj(value, row, index, field) {
        const spjType = row.spjType || row.spj || "0";
        const pid = row.idx || (index + 1);
        
        if (spjType === "2") {
            // 交互评测：显示标识，不显示开关
            return `<button type="button" class="btn btn-success btn-sm disabled" title="交互评测 (Interactive Judge)" style="pointer-events: none;">
                <i class="fa fa-comments"></i> 交互<span class="en-text">Interactive</span>
            </button>`;
        } else if (spjType === "1") {
            // 特判评测：显示开关
            const isChecked = (row.spj_checked !== false && value !== '0'); // 默认选中，除非明确设置为 false 或 '0'
            return `<div class="csg-switch">
                <input type="checkbox" class="csg-switch-input" data-pid="${pid}" ${isChecked ? 'checked' : ''}>
            </div>`;
        } else {
            // 标准评测：显示标识
            return `<button type="button" class="btn btn-primary btn-sm disabled" title="标准评测 (Standard Judge)" style="pointer-events: none;">
                <i class="fa fa-check-circle"></i> 标准<span class="en-text">Standard</span>
            </button>`;
        }
    }
    
    // 哈希 formatter
    function FormatterProParserHash(value, row, index, field) {
        if (!value) return '-';
        // 哈希值可以用代码格式显示
        return `<code style="font-size: 0.85em;">${value}</code>`;
    }
    
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
                } else {
                    alerty.error('仅支持 .zip 文件', 'Only .zip files are supported');
                }
            });

            fileInput.click();
        });

        $('#download_selected_btn').click(() => {
            DownloadSelectedProblems();
        });

        polygon_parse_table.on('click-cell.bs.table', function(e, field, td, row) {
            if (field === 'spj') {
                e.stopPropagation();
            }
        });
        
        // 表格刷新后初始化 csg-switch
        polygon_parse_table.on('post-body.bs.table', function() {
            // 初始化 csg-switch
            if (window.csgSwitch) {
                const switches = document.querySelectorAll('.csg-switch-input:not([data-csg-initialized])');
                switches.forEach(switchEl => {
                    window.csgSwitch.initSwitch(switchEl);
                });
            }
        });
    });
</script>

<style>
    .directory {
        font-family: Arial, sans-serif;
        line-height: 1.5;
        background: #f8f9fa;
        padding: 1rem;
        border-radius: 0.375rem;
        border: 1px solid #dee2e6;
        margin: 1rem 0;
    }

    .directory ul {
        list-style-type: none;
        padding-left: 20px;
        margin: 0;
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

    .directory .img::before {
        content: "🏞️ ";
    }

    .modal-body p {
        margin-bottom: 1rem;
    }

    .modal-body ul {
        padding-left: 20px;
        list-style-type: disc;
    }

    .modal-body li {
        margin-bottom: 0.75rem;
    }

    .text-muted {
        color: #6c757d;
    }
    
    /* 工具栏按钮中的英文文本显示在下一行（上下结构） */
    #polygon_toolbar .btn .en-text {
        display: block !important;
        margin-top: 2px;
    }
</style>