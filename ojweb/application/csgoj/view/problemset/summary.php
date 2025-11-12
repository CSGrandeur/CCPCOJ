{include file="problemset/problem_header" /}
<div id="summary_div">
    <div class="md_display_div" id="summary_statistic">
        <table class="table table-sm">
            <h3>统计<span class="en-text">Statistic</span></h3>
            <thead>
            <tr><th></th><th></th></tr>
            </thead>
            <tbody>
            <tr>
                <td>
                    <a href="__OJ__/status?problem_id={$problem['problem_id']}">总提交<span class="en-text">Total Submissions</span></a>
                </td>
                <td class="text-end">{$statistic['total_submissions']}</td>
            </tr>
            <tr>
                <td>提交用户<span class="en-text">Users Submitted</span></td>
                <td class="text-end">{$statistic['users_submitted']}</td>
            </tr>
            <tr>
                <td>通过用户<span class="en-text">Users Solved</span></td>
                <td class="text-end">{$statistic['users_solved']}</td>
            </tr>
            <?php foreach($ojResultsHtml as $key=>$value):
                 if($key == 13) break;
            ?>
            <tr>
                <td>
                    <a href="__OJ__/status?problem_id={$problem['problem_id']}&result={$key}">{$value[1]}</a>
                </td>
                <td class="text-end">
                    {$statistic[$key]}
                </td>
            </tr>
            <?php endforeach;?>
            </tbody>
        </table>
    </div>
    <div id="summary_rank">
        <div id="summary_toolbar">
            <div class="d-flex align-items-center" role="form">
                <h3 class="me-3 mb-0">解题排行<span class="en-text">Solution Rank</span></h3>
                <div class="input-group" style="max-width:200px;">
                    <span class="input-group-text">语言<span class="en-text">Language</span>:</span>
                    <select id="language_select" name="language" class="form-select">
                        <option value="-1" selected="true">
                            全部<span class="en-text">All</span>
                        </option>
                        {foreach($allowLanguage as $key=>$value)}
                        <option value="{$key}">
                            {$value}
                        </option>
                        {/foreach}
                    </select>
                </div>
            </div>
        </div>
        <div id="summary_table_div">
            <table
                    class="bootstraptable_refresh_local"
                    id="summary_table"
                      data-toggle="table"
                      data-url="__OJ__/problemset/summary_ajax?pid={$problem['problem_id']}"
                      data-pagination="true"
                      data-page-list="[20]"
                      data-page-size="20"
                      data-side-pagination="server"
                      data-method="get"
                      data-striped="true"
                      data-sort-name="time"
                      data-sort-order="asc"
                      data-pagination-v-align="bottom"
                      data-pagination-h-align="left"
                      data-pagination-detail-h-align="right"
                      data-toolbar-align="left"
                      data-toolbar="#summary_toolbar"
                      data-query-params="queryParams"
                      data-classes="table table-borderless table-hover table-striped"
            >
                <thead>
                <tr>
                    <th data-field="rank" data-align="center" data-valign="middle"  data-sortable="false" data-width="20">排名<span class="en-text">Rank</span></th>
                    <th data-field="solution_id" data-align="center" data-valign="middle"  data-sortable="false" data-width="20">运行ID<span class="en-text">RunID</span></th>
                    <th data-field="user_id" data-align="center" data-valign="middle"  data-sortable="false" >用户<span class="en-text">User</span></th>
                    <th data-field="memory" data-align="right" data-valign="middle"  data-sortable="true" data-width="80">内存<span class="en-text">Memory</span>(kB)</th>
                    <th data-field="time" data-align="right" data-valign="middle"  data-sortable="true" data-width="80">时间<span class="en-text">Time</span>(ms)</th>
                    <th data-field="language" data-align="center" data-valign="middle"  data-sortable="false" data-width="80">语言<span class="en-text">Language</span></th>
                    <th data-field="code_length" data-align="right" data-valign="middle"  data-sortable="true" data-width="80">长度<span class="en-text">Length</span></th>
                    <th data-field="in_date" data-align="center" data-valign="middle"  data-sortable="false"  data-width="160">提交时间<span class="en-text">Submit Time</span></th>
                </tr>
                </thead>
            </table>
        </div>
    </div>

</div>
<style type="text/css">
    .problem_statistic td
    {
        white-space:nowrap;
    }
    #summary_div
    {
        position: relative;
        min-height: 400px;
    }
    #summary_statistic
    {
        position: absolute;
        left: 0;
    }
    #summary_rank
    {
        margin-left: 260px;
    }
    .bootstrap-table .fixed-table-toolbar {
        padding: 0.5rem 0;
    }
    .bootstrap-table .fixed-table-toolbar .d-flex {
        gap: 0.5rem;
    }
</style>
<script type="text/javascript">

    var summary_table = $('#summary_table');
    var summary_table_div = $('#summary_table_div');
    var summary_toolbar = $('#summary_toolbar');
    summary_table.on('post-body.bs.table', function(){
        if(summary_table[0].scrollWidth > summary_table_div.width())
            summary_table_div.width(summary_table[0].scrollWidth + 20);
    });
    function queryParams(params) {
        summary_toolbar.find('input[name]').each(function () {
            params[$(this).attr('name')] = $(this).val();
        });
        summary_toolbar.find('select[name]').each(function () {
            params[$(this).attr('name')] = $(this).val();
        });
        return params;
    }
    $('#language_select').on('change', function(){
        summary_table.bootstrapTable('refresh', {silent: true});
    });
</script>
{js href="__JS__/refresh_in_table.js" /}