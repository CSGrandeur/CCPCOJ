{js href="__STATIC__/csgoj/rank_common.js" /}
<script src="/static/ojtool/js/rank_base.js"></script>
<script src="/static/ojtool/js/newrank_rank_base.js"></script>
{if $action == 'rank'}
<script src="/static/ojtool/js/newrank_rank.js"></script>
{else /}
<script src="/static/ojtool/js/newrank_schoolrank.js"></script>
{/if}
<div style="display:flex; justify-content: space-between;">
    <h1 id="page_header"><span id="page_header_main"></span><span id="page_header_sub">{if $action != 'rank'} - 学校排名{/if}</span></h1>
    <div class="btn-group input-group" role="group" style="width:600px; height:20px;" >
        <select class="exam_info_form form-select" name="with_star_team" id="with_star_team" aria-label="open or close">
            {if $action == 'rank'}
                <option selected value="0">打星不排名</option>
            {/if}
            <option value="1">不含打星</option>
            <option value="2">打星参与排名</option>
        </select>
        {if $action == 'rank'}
        <button id="summary_button" class="btn btn-warning" style="width:40px;">统计</button>
        {/if}
        <a href="#" id="alink_team" class="btn btn{if $action != 'rank'}outline{/if}-primary" style="width:80px;">队伍排名</a>
        <a href="#" id="alink_school" class="btn btn{if $action == 'rank'}-outline{/if}-primary" style="width:80px;">学校排名</a>
    </div>
</div>
{if $action != 'rank'}
<h4>每校Top <span class="text-danger" id="top_team_span">1</span> 队伍（合并）计入</h4>
{/if}


<div id="rankroll_div">
    <div id="rank_header_div">
        <div class="h_td h_rank">排名</div>
        <div class="h_td h_logo">图标</div>
        <div class="h_td h_solve">题数</div>
        <div class="h_td h_time">罚时</div>
        <div class="h_td h_team_content">{if $action == 'rank'}队伍{else /}学校{/if}</div>
    </div>
    <div class="grid" id="rank_grid_div">
            
    </div>
</div>

<div id="loading_div" class='overlay'>
    <div id="loading_spinner" class="spinner-border">&nbsp;初始化中...</div>
</div>
<div id="overlay_timing" style="display: none;"></div>
{if $action == 'rank'}
<div class="modal fade" id="summary_modal_div" tabindex="-1" role="dialog" aria-labelledby="summary_modal_div_label" aria-hidden="true">
    <div class="modal-dialog modal-summary" >
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><span id="summary_modal_div_label_span">统计数据</span> &nbsp;&nbsp;</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"  aria-label="Close"></button>
            </div>
            <div class="modal-body" id="summary_modal_div_content">
                <div id="summary_div">
                </div>
            </div>
        </div>
    </div>
</div>
{/if}