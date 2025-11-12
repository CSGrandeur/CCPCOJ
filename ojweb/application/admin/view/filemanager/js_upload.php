

<div class="container-fluid px-3">
    <!-- 隐藏的表单 -->
    <form id="upload_form" method="post" action="{$upload_url}" enctype="multipart/form-data" style="display:none">
        <input type="file" id="upload_input" name="upload_file[]" multiple style="display:none">
        <input type="hidden" name="item" id='item_input' value="{$inputinfo['item']}">
        <input
            type="hidden"
            name="id"
            id='id_input'
            maxfilesize={$maxfilesize}
            delete_url="{$delete_url}"
            rename_url="{$rename_url}"
            maxfilenum="{$maxFileNum}"
            fire_url="{if(isset($fire_url))}{$fire_url}{else/}null{/if}"
            value="{$inputinfo['id']}">
    </form>
    
    <!-- 通知信息 -->
    {if(isset($attach_notify))}
    <div class="alert alert-info alert-dismissible fade show mb-3" role="alert">
        <i class="bi bi-info-circle me-2"></i>
        <span >{$attach_notify}</span>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    {/if}
    
    <!-- 工具栏 -->
    <div id="upload_toolbar" class="mb-3">
        <button id="upload_button" class="btn btn-primary" title="上传文件 (Upload File)">
            <i class="bi bi-cloud-upload"></i>
        </button>
        {if isset($addition_tools) && !empty($addition_tools)}
        <div class="vr d-inline-block mx-2"></div>
        {$addition_tools}
        {/if}
    </div>
    
    <!-- 文件列表表格 -->
    <table
        class="bootstraptable_refresh_local"
        id="upload_table"
        data-toggle="table"
        data-url="{$file_url}"
        data-toolbar="#upload_toolbar"
        data-pagination="true"
        data-sort-name="file_name"
        data-sort-order="asc"
        data-page-list="[10, 25, 100]"
        data-page-size="25"
        data-method="get"
        data-search="true"
        data-search-align="left"
        data-side-pagination="client"
        data-unique-id="file_name"
        data-pagination-v-align="bottom"
        data-pagination-h-align="left"
        data-pagination-detail-h-align="right"
        data-cookie="true"
        data-cookie-id-table="{$OJ_SESSION_PREFIX}-file-{$inputinfo['item']}"
        data-cookie-expire="5mi"
        data-cookie-enabled="['bs.table.sortOrder', 'bs.table.sortName', 'bs.table.pageList']"
        data-classes="table table-no-bordered table-hover table-striped"
        style="width: 100% !important; min-width: 100%;">
        <thead class="table-light">
            <tr>
                <th data-field="file_serial" data-align="center" data-valign="middle" data-sortable="true" data-width="60" data-formatter="FormatterIdx">
                    <span class="bilingual-label">序号<span class="en-text">ID</span></span>
                </th>
                <th data-field="file_name" data-align="left" data-valign="middle" data-sortable="true" data-formatter="FormatterFileName">
                    <span class="bilingual-label">文件名<span class="en-text">Name</span></span>
                </th>
                <th data-field="file_size" data-align="right" data-valign="middle" data-sortable="true" data-width="120">
                    <span class="bilingual-label">大小(KB)<span class="en-text">Size(KB)</span></span>
                </th>
                <th data-field="file_type" data-align="center" data-valign="middle" data-width="60" data-formatter="FormatterFileType">
                    <span class="bilingual-label">链接<span class="en-text">Url</span></span>
                </th>
                <th data-field="file_rename" data-align="center" data-valign="middle" data-width="60" data-formatter="FormatterFileRename">
                    <span class="bilingual-label">重命名<span class="en-text">Rename</span></span>
                </th>
                <th data-field="file_lastmodify" data-align="center" data-valign="middle" data-sortable="true" data-width="100" data-formatter="FormatterDate">
                    <span class="bilingual-label">修改时间<span class="en-text">Last Modify</span></span>
                </th>
                <th data-field="file_delete" data-align="center" data-valign="middle" data-width="90" data-formatter="FormatterFileDelete">
                    <span class="bilingual-label">删除<span class="en-text">Delete</span></span>
                </th>
            </tr>
        </thead>
    </table>
</div>
<input type="hidden" id="input_hidden_page_info" action="{$action}">
<script type="text/javascript">
    // 设置全局变量供外部 JS 使用
    window.uploadUrl = "<?php echo $upload_url; ?>";
</script>


{js href="__JS__/refresh_in_table.js" /}

{js href="__JS__/overlay.js" /}
{js href="__JS__/chunk_upload.js" /}
{include file="../../csgoj/view/public/js_zip" /}
{css href="__STATIC__/csgoj/upload_page.css" /}
{js href="__STATIC__/csgoj/upload_page.js" /}