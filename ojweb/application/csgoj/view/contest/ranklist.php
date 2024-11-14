{include file="../../csgoj/view/contest/rank_header" /}
<div id="ranklist_table_div">
    <div class="frozen_mask">Frozen</div>
    <table
            id="ranklist_table"
            data-toggle="table"
            data-side-pagination="client"
            data-method="post"
            data-classes="table table-bordered table-hover table-striped"
            data-show-refresh="true"
            data-silent-sort="false"
            data-buttons-align="left"
            data-toolbar-align="left"
            data-sort-stable="true"
            data-show-export="true"
            data-toolbar="#ranklist_toobar"
            data-export-types="['excel', 'json', 'png']"
            data-export-options='{"fileName":"{$contest[\"contest_id\"]}-{$contest[\"title\"]}-Ranklist"}'
    >
        <thead>
        <tr>
            <th data-field="rank" data-align="center" data-valign="middle"  data-sortable="false" data-sorter="rankSorter" data-width="60" data-formatter="FormatterRank" data-cell-style="rankCellStyle">Rank</th>
            {if $OJ_STATUS != 'exp' }
            <th data-field="school" data-align="left" data-valign="middle"  data-sortable="false" data-width="64" data-formatter="FormatterSchoolLogo" >Logo</th>
            {/if}
            <th data-field="nick" data-align="left" data-valign="middle"  data-sortable="false" data-formatter="FormatterIdName" >Name</th>
            {if $module == 'cpcsys' }
            <th data-field="tkind" data-align="left" data-valign="middle"  data-sortable="false" data-width="10"  data-formatter="FormatterTkind"></th>
            {/if}
            <th data-field="solved" data-align="center" data-valign="middle"  data-sortable="false" data-width="60">Solved</th>
            <th data-field="penalty" data-align="center" data-valign="middle"  data-sortable="false" data-width="100" data-formatter="FormatterPenalty">Penalty</th>
            {foreach($problemIdMap['abc2id'] as $apid=>$pid)}
            <th data-field="{$apid}" data-align="center" data-valign="middle"  data-sortable="false" data-width="90" data-cell-style="acCellStyle" data-formatter="FormatterRankPro">
                <div style="display:flex; flex-direction: column;">
                    <a href="problem?cid={$contest['contest_id']}&pid={$apid}">{$apid}</a>
                    <span class="rank_header_pro_summary" apid={$apid} title="通过队伍 / 尝试队伍">- / -</span>
                </div>
            </th>
            {/foreach}
            {if $module == 'cpcsys' }
            <th data-field="tmember" data-align="left" data-valign="middle"  data-sortable="false" data-cell-style="memberCellStyle">Member</th>
            <th data-field="coach" data-align="left" data-valign="middle"  data-sortable="false" data-width="50" data-cell-style="coachCellStyle">Coach</th>
            {/if}
        </tr>
        </thead>
    </table>
</div>

<input type="hidden" id="rank_config" url="ranklist_ajax?cid={$contest['contest_id']}" use_cache="{$use_cache}" >

{include file="../../csgoj/view/contest/rank_footer" /}