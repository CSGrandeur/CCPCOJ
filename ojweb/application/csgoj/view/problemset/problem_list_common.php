
<?php
// 题目列表模式：'admin' | 'frontend' | 'selection'
$prolist_mode = $prolist_mode ?? 'frontend';
?>

{if $prolist_mode == 'admin'}
<h1 class="page-title bilingual-inline">{$page_title}<span class="en-text">{$page_title_en}</span></h1>
{/if}

<div id="{$table_prefix}_toolbar" class="table-toolbar">
    <div class="d-flex align-items-center gap-2" role="form">
        <button id="{$table_prefix}_refresh" type="button" class="btn btn-outline-secondary toolbar-btn" title="刷新 (Refresh)">
            <i class="bi bi-arrow-clockwise"></i>
        </button>
        <button id="{$table_prefix}_clear" type="button" class="btn btn-outline-secondary toolbar-btn" title="清空筛选条件 (Clear)">
            <i class="bi bi-eraser"></i>
        </button>

        <div class="toolbar-group">
            <span class="toolbar-label-inline"><span>题目类型</span><span class="toolbar-label en-text">Problem Type</span></span>
            <select name="spj" class="form-select toolbar-select {$table_prefix}_filter">
                <option value="-1" {if $search_spj == -1}selected="true"{/if}>
                    All
                </option>
                <option value="0" {if $search_spj == 0}selected="true"{/if}>
                    标准 <span class="en-text">Standard</span>
                </option>
                <option value="1" {if $search_spj == 1}selected="true"{/if}>
                    特判 <span class="en-text">Special</span>
                </option>
                <option value="2" {if $search_spj == 2}selected="true"{/if}>
                    交互 <span class="en-text">Interactive</span>
                </option>
            </select>
        </div>
        {if $prolist_mode === 'admin'}
        <div class="toolbar-group">
            <span class="toolbar-label-inline"><span>题目状态</span><span class="toolbar-label en-text">Problem Status</span></span>
            <select name="defunct" class="form-select toolbar-select {$table_prefix}_filter">
                <option value="-1" selected>
                    All
                </option>
                <option value="0">
                    公开 <span class="en-text">Public</span>
                </option>
                <option value="1">
                    隐藏 <span class="en-text">Hidden</span>
                </option>
            </select>
        </div>
        {/if}
        <div class="toolbar-group">
            <span class="toolbar-label-inline"><span>搜索</span><span class="toolbar-label en-text">Search</span></span>
            <input id="{$table_prefix}_search_input" name="search" class="form-control toolbar-input {$table_prefix}_filter" type="text" placeholder="{$search_placeholder}" style="width: 200px;">
        </div>
        {if $prolist_mode === 'selection'}
        <div class="text-muted">
            已选择: <span id="selectedCount">0</span> 题
        </div>
        {/if}
        {if $prolist_mode === 'frontend'}
        <div class="ms-auto toolbar-btn-group">
            <span class="toolbar-label-inline"><span>页码</span><span class="toolbar-label en-text">Page</span></span>
            <input id="page_jump_input" name="page_jump" class="form-control toolbar-input" type="text" value="1" style="width: 80px;" placeholder="页码">
            <button id="toobar_ok" type="button" class="btn btn-outline-secondary toolbar-btn" title="跳转到指定页码 (Jump to Page)">
                <i class="bi bi-arrow-right-circle"></i>
            </button>
        </div>
        {/if}
    </div>
</div>

<table
        class="bootstraptable_refresh_local"
        id="{$table_id}"
        data-toggle="table"
        data-url="{$ajax_url}"
        data-pagination="true"
        data-page-list="[25,50,100]"
        data-page-size="{$page_size}"
        data-side-pagination="server"
        data-method="get"
        data-search="false"
        data-sort-name="problem_id"
        data-sort-order="asc"
        data-pagination-v-align="{if $prolist_mode === 'admin'}both{else}bottom{/if}"
        data-pagination-h-align="left"
        data-pagination-detail-h-align="right"
        data-toolbar="#{$table_prefix}_toolbar"
          data-query-params="queryParams"
        {if $prolist_mode === 'selection'}
            data-multiple-select-row="true"
            data-click-to-select="true"
            data-maintain-meta-data="true"
            data-unique-id="problem_id"
            data-search-highlight="true"
        {/if}
        data-classes="table table-no-bordered table-hover table-striped"
>
    <thead>
    <tr>
        {if $prolist_mode === 'selection'}
        <th data-field="state" data-checkbox="true" data-width="50"></th>
        {/if}
        {if $prolist_mode === 'frontend'}
        <th data-field="ac" data-align="center" data-valign="middle"  data-sortable="false" data-width="30" data-formatter="FormatterProblemAc"></th>
        {/if}
        <th data-field="problem_id" data-align="center" data-valign="middle"  data-sortable="true" data-width="55">题号<span class="en-text">ID</span></th>
        <th data-field="title" data-align="left" data-valign="middle"  data-sortable="false" data-formatter="FormatterProblemTitle">标题<span class="en-text">Title</span></th>
        <th data-field="source" data-align="left" data-valign="middle"  data-sortable="false" data-formatter="FormatterSource">来源<span class="en-text">Source</span></th>
        {if $prolist_mode === 'admin'}
        <th data-field="author" data-align="left" data-valign="middle"  data-sortable="false" data-width="80">出题<span class="en-text">Author</span></th>
        {/if}
        {if $prolist_mode === 'admin'}
        <th data-field="defunct" data-align="center" data-valign="middle"  data-sortable="false" data-width="50" data-formatter="FormatterDefunctPro" title="是否在公开题目集展示">状态<span class="en-text">Status</span></th>
        {/if}
        {if $prolist_mode === 'admin' && $OJ_OPEN_ARCHIVE}
        <th data-field="archived" data-align="center" data-valign="middle"  data-sortable="false" data-width="80" data-formatter="FormatterProArchive" title="整理到归档目录">归档<span class="en-text">Archive</span></th>
        {/if}
        {if $prolist_mode === 'admin'}
        <th data-field="edit" data-align="center" data-valign="middle"  data-sortable="false" data-width="50" data-formatter="FormatterEdit">编辑<span class="en-text">Edit</span></th>
        <th data-field="copy" data-align="center" data-valign="middle"  data-formatter="FormatterCopy"  data-width="50">复制<span class="en-text">Copy</span></th>
        <th data-field="attach" data-align="center" data-valign="middle"  data-sortable="false" data-width="50" data-formatter="FormatterAttach">附件<span class="en-text">Attach</span></th>
        <th data-field="testdata" data-align="center" data-valign="middle"  data-sortable="false" data-width="50" data-formatter="FormatterTestData">数据<span class="en-text">Data</span></th>
        {/if}
        {if $prolist_mode === 'frontend'}
        <th data-field="accepted" data-align="right" data-valign="middle"  data-sortable="true" data-width="80">通过<span class="en-text">AC</span></th>
        <th data-field="submit" data-align="right" data-valign="middle"  data-sortable="true" data-width="100">提交<span class="en-text">Submit</span></th>
        {/if}
        {if $prolist_mode === 'admin'}
        <th data-field="in_date" data-align="center" data-valign="middle"  data-sortable="false" data-width="80" data-formatter="FormatterDate">日期<span class="en-text">Date</span></th>
        {/if}
    </tr>
    </thead>
</table>

{js href="__STATIC__/csgoj/oj_problem.js"}
{if $prolist_mode === 'admin'}
{include file="../../admin/view/admin/js_changestatus" /}
{/if}
{js href="__JS__/refresh_in_table.js" /}

<script type="text/javascript">
// 题目列表配置
var PROBLEM_LIST_CONFIG = {
    tableId: '<?php echo $table_id; ?>',
    prefix: '<?php echo $table_prefix; ?>',
    filterSelectors: <?php echo json_encode($filter_selectors); ?>,
    searchInputId: '<?php echo $table_prefix; ?>_search_input',
    scene: '<?php echo $prolist_mode; ?>',
    customHandlers: '<?php echo $custom_handlers; ?>'
};
</script>

<script type="text/javascript">
// 使用工厂函数生成queryParams处理器，根据模式处理不同逻辑
window.queryParams = window.makeQueryParams(PROBLEM_LIST_CONFIG.prefix, PROBLEM_LIST_CONFIG.searchInputId, function(params) {
    // 选择器模式：只传递基本参数
    if (PROBLEM_LIST_CONFIG.scene === 'selection') {
        return params;
    }
    
    // 前台和管理后台模式：处理完整的筛选和搜索逻辑
    // 处理筛选条件
    PROBLEM_LIST_CONFIG.filterSelectors.forEach(function(selector) {
        var value = $('select[name="' + selector + '"]').val();
        if (value != -1) {
            params[selector] = value;
        }
    });
    
    return params;
});

// 初始化工具栏功能
$(function() {
    if (typeof initBootstrapTableToolbar === 'function') {
        var toolbarConfig = {
            tableId: PROBLEM_LIST_CONFIG.tableId,
            prefix: PROBLEM_LIST_CONFIG.prefix,
            filterSelectors: PROBLEM_LIST_CONFIG.filterSelectors,
            searchInputId: PROBLEM_LIST_CONFIG.searchInputId
        };
        
        // 前台特有功能：URL搜索同步
        if (PROBLEM_LIST_CONFIG.scene === 'frontend' && PROBLEM_LIST_CONFIG.customHandlers) {
            toolbarConfig.customHandlers = {
                initUrlSearch: function() {
                    let problemset_table = $('#' + PROBLEM_LIST_CONFIG.tableId);
                    let search_cookie_name = problemset_table.attr('data-cookie-id-table') + ".bs.table.searchText";
                    let search_input = $('#' + PROBLEM_LIST_CONFIG.searchInputId);
                    
                    // 使用全局的 GetAnchor/SetAnchor 函数
                    let search_str = GetAnchor("search");
                    if(search_str !== null) {
                        document.cookie = [
                            search_cookie_name, '=', search_str
                        ].join('');
                    }
                    
                    search_input.on('input', function() {
                        SetAnchor(search_input.val(), 'search');
                    });
                    
                    $(window).on('hashchange', function(e) {
                        let search_str = GetAnchor("search");
                        search_input.val(search_str).trigger('keyup');
                    });
                }
            };
        }
        
        initBootstrapTableToolbar(toolbarConfig);
    }
});

// 前台特有功能初始化
$(function() {
    if (PROBLEM_LIST_CONFIG.scene === 'frontend') {
        // 初始化页码跳转功能
        if (typeof initPageJump === 'function') {
            initPageJump(PROBLEM_LIST_CONFIG.tableId, 'toobar_ok', 'page_jump_input');
        }
        
        // F5刷新处理
        $(window).keydown(function(e) {
            if (e.keyCode == 116 && !e.ctrlKey) {
                if(window.event){
                    try{e.keyCode = 0;}catch(e){}
                    e.returnValue = false;
                }
                e.preventDefault();
                $('#' + PROBLEM_LIST_CONFIG.tableId).bootstrapTable('refresh');
            }
        });
        
        // 初始化页码输入框
        $('#page_jump_input').val($('#' + PROBLEM_LIST_CONFIG.tableId).bootstrapTable('getOptions')['pageNumber']);
    }
});
</script>

{if $prolist_mode !== 'admin'}
<style type="text/css">
.fixed-table-toolbar {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.fixed-table-toolbar .columns {
    order: -1;
}
.bootstrap-table .fixed-table-toolbar {
    padding: 0.5rem 0;
}
</style>
{/if}