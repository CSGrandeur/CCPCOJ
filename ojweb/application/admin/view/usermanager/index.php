<div id="user_toolbar" class="table-toolbar">
    <div class="d-flex align-items-center gap-2" role="form">
        <button id="user_refresh" type="submit" class="btn btn-outline-secondary toolbar-btn" title="刷新 (Refresh)">
            <i class="bi bi-arrow-clockwise"></i>
        </button>
        <button id="user_clear" type="submit" class="btn btn-outline-secondary toolbar-btn" title="清空筛选条件 (Clear)">
            <i class="bi bi-eraser"></i>
        </button>
        <div class="toolbar-group">
            <span class="toolbar-label-inline"><span>搜索</span><span class="toolbar-label en-text">Search</span></span>
            <input id="user_search_input" name="search" class="form-control toolbar-input user_filter" type="text" placeholder="用户ID/昵称/学校/邮箱" style="width: 250px;">
        </div>
    </div>
</div>

<table
        class="bootstraptable_refresh_local"
        id="user_list_table"
        data-toggle="table"
        data-url="__ADMIN__/usermanager/user_list_ajax"
        data-pagination="true"
        data-page-list="[25, 50, 100]"
        data-page-size="25"
        data-side-pagination="server"
        data-method="get"
        data-query-params="queryParams"
        data-search="false"
        data-sort-name="reg_time"
        data-sort-order="desc"
        data-pagination-v-align="both"
        data-pagination-h-align="left"
        data-pagination-detail-h-align="right"
        data-toolbar="#user_toolbar"
        data-cookie="true"
        data-cookie-id-table="{$OJ_SESSION_PREFIX}admin-userlist"
        data-cookie-expire="5mi"
>
    <thead>
    <tr>
        <th data-field="idx" data-align="right" 	data-valign="middle"  data-width="30" 	data-formatter="AutoId">序号<span class="en-text">Idx</span></th>
        <th data-field="user_id" 	data-align="left" 	data-valign="middle"  data-width="120"	data-formatter="UserIdFormatter">用户ID<span class="en-text">UserID</span></th>
        <th data-field="nick" 		data-align="left" 	data-valign="middle"  data-width="120">昵称<span class="en-text">Nick</span></th>
        <th data-field="school" 	data-align="left" 	data-valign="middle"  data-width="150">学校<span class="en-text">School</span></th>
        <th data-field="email" 		data-align="left" 	data-valign="middle"  data-width="150">邮箱<span class="en-text">Email</span></th>
        <th data-field="submit" 	data-align="right" 	data-valign="middle"  data-width="60">提交<span class="en-text">Submit</span></th>
        <th data-field="solved" 	data-align="right" 	data-valign="middle"  data-width="60">解决<span class="en-text">Solved</span></th>
        <th data-field="reg_time" 	data-align="center" data-valign="middle"  data-width="150">注册时间<span class="en-text">Reg Time</span></th>
        <th data-field="edit" 		data-align="center" data-valign="middle"  data-width="80"	data-formatter="EditFormatter">编辑<span class="en-text">Edit</span></th>
        <th data-field="del" 		data-align="center" data-valign="middle"  data-width="80"	data-formatter="DelFormatter">删除<span class="en-text">Del(DbClick)</span></th>
    </tr>
    </thead>
</table>
{js href="__STATIC__/csgoj/general_formatter.js" /}
{js href="__JS__/refresh_in_table.js" /}

<script>
let user_list_table = $('#user_list_table');

function AutoId(value, row, index, field) {
    return index + 1;
}

function UserIdFormatter(value, row, index, field) {
    return `<a href='/csgoj/user/userinfo?user_id=${value}'>${value}</a>`;
}

function EditFormatter(value, row, index, field) {
    return "<a href='/csgoj/user/modify?user_id=" + row['user_id'] + "'><button class='btn btn-primary btn-sm'>编辑<span class='en-text'>Edit</span></button></a>";
}

function DelFormatter(value, row, index, field) {
    return "<button class='btn btn-danger btn-sm' user_id='" + row['user_id'] + "' title='双击删除 / Double Click to Delete'><i class='bi bi-trash'></i></button>";
}

// 使用工厂函数生成queryParams处理器
window.queryParams = window.makeQueryParams('user', 'user_search_input');

// 初始化管理后台用户列表工具栏
initBootstrapTableToolbar({
    tableId: 'user_list_table',
    prefix: 'user',
    filterSelectors: [],
    searchInputId: 'user_search_input'
});

$(document).ready(function(){
    user_list_table.on('dbl-click-cell.bs.table', function(e, field, value, row, $element){
        if (field === 'del') {
            // 确认删除
            alerty.confirm({
                message: '确认删除用户？',
                message_en: 'Confirm to delete user?',
                callback: function() {
                    $.get(
                        '__ADMIN__/usermanager/user_del_ajax', 
                        {'user_id': row.user_id},
                        function(ret) {
                            if(ret['code'] == 1) {
                                alerty.success(`用户 ${row.user_id} 删除成功`, `User ${row.user_id} deleted successfully`);
                                user_list_table.bootstrapTable('remove', {field: 'user_id', values: [row.user_id]});
                            }
                            else {
                                alerty.error(ret['msg']);
                            }
                        }
                    );
                },
                callbackCancel: function() {
                    alerty.message('操作已取消', 'Operation cancelled');
                }
            });
        }
    });
});
</script>

