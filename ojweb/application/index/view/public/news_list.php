

<div id="article_list_div">
<table
    id="article_list_table"
    data-toggle="table"
    data-url="__HOME__/{$controller}/category_news_list_ajax"
    data-pagination="true"
    data-page-list="[10, 25, 50]"
    data-page-size="10"
    data-side-pagination="client"
    data-method="get"
    data-search="true"
    data-striped="true"
    data-classes="table table-striped table-hover"
    data-pagination-h-align="left"
    data-pagination-detail-h-align="right"
    data-search-align="center"
>
    <thead class="table-light">
    <tr>
        <th data-field="news_id" data-align="center" data-valign="middle" data-sortable="true" data-width="55">ID<span class="en-text">ID</span></th>
        <th data-field="title" data-align="left" data-valign="middle" data-sortable="false" data-formatter="FormatterTitle">标题<span class="en-text">Title</span></th>
        <th data-field="tags" data-align="center" data-valign="middle" data-sortable="false" data-cell-style="TagCellStyle" data-formatter="FormatterTags">标签<span class="en-text">Tags</span></th>
        <th data-field="user_id" data-align="center" data-valign="middle" data-sortable="false" data-width="80" data-formatter="FormatterUser">创建者<span class="en-text">Creator</span></th>
        <th data-field="modify_user_id" data-align="center" data-valign="middle" data-sortable="false" data-width="60" data-formatter="FormatterUser">编辑<span class="en-text">Editor</span></th>
        <th data-field="modify_time" data-align="center" data-valign="middle" data-sortable="false" data-width="160">更新时间<span class="en-text">Update Time</span></th>
    </tr>
    </thead>
        </table>
        </div>
        <input type="hidden" id="page_info" category="{$category}">
        
        {css href="__STATIC__/csgoj/news/news.css" /}
        {js href="__STATIC__/csgoj/news/news.js" /}
        
        <script type="text/javascript">
        let page_info = $('#page_info');
        let page_category = page_info.attr('category');
        function FormatterUser(value, row, index, field) {
            return `<a href='/csgoj/user/userinfo?user_id=${value}' target='_blank'>${value}</a>`;
        }
        function FormatterTitle(value, row, index, field) {
            return `<a href='/index/${page_category}/detail?nid=${row.news_id}' title='${value}' class='article-title-in-table'>${value}</a>`;
        }
        function FormatterTags(value, row, index, field) {
            if(value == null) {
                value = '';
            }
            return `<span title='${value}' class='tags-in-table'>${value}</span>`;
        }
        </script>