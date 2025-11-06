{include file="../../csgoj/view/public/js_exceljs" /}
{include file="../../csgoj/view/public/base_csg_switch" /}
{include file="../../csgoj/view/public/js_rank"}
{js href="__STATIC__/csgoj/contest/award.js" /}

<div class="admin-page-header">
    <div class="admin-page-header-left">
        <div class="admin-page-header-icon">
            <i class="bi bi-trophy"></i>
        </div>
        <h1 class="admin-page-header-title">
            <div class="admin-page-header-title-main">
                获奖名单
            </div>
            <div class="admin-page-header-title-right">
                <a href="__CPC__/contest/contest?cid={$contest['contest_id']}" class="admin-page-header-id">
                    <i class="bi bi-hash"></i> {$contest['contest_id']}
                </a>
                <span class="en-text">
                    Award List
                </span>
            </div>
        </h1>
    </div>
    <div class="admin-page-header-right">
        <button class="btn btn-success btn-sm" id="export_award_csv_btn">
            <span class="cn-text"><i class="bi bi-file-earmark-text me-1"></i>
            导出CSV</span><span class="en-text">Export CSV</span>
        </button>
        <button class="btn btn-primary btn-sm" id="export_award_xlsx_btn">
            <span class="cn-text"><i class="bi bi-file-earmark-excel me-1"></i>
            导出Excel</span><span class="en-text">Export Excel</span>
        </button>
    </div>
</div>

<div class="container">
    <div class="alert alert-danger alert-dismissible fade show" role="alert" style="padding: 0.75rem 1rem; margin-bottom: 1rem;">
        <div class="d-flex align-items-start">
            <i class="bi bi-exclamation-triangle-fill me-2 mt-1"></i>
            <div class="flex-grow-1">
                <strong>务必确认：</strong>
                <span class="text-danger">1. 比赛已结束</span> | 
                <span class="text-danger">2. <a href="/{$module}/contest/status?cid={$contest['contest_id']}" class="text-decoration-none"><strong>评测队列</strong></a>已完成</span> | 
                <span class="text-danger">3. <strong class="text-info" style="cursor: pointer;" onclick="window.location.reload()">刷新本页</strong>获取最新数据</span>
                <div class="en-text small mt-1">Please confirm: Contest ended, judging completed, refresh for latest data</div>
            </div>
        </div>
    </div>

<div class="card mb-3">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">
                <span class="cn-text"><i class="bi bi-info-circle me-2"></i>
                获奖规则说明</span><span class="en-text">Award Rules</span>
            </h5>
            <button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#awardRules" aria-expanded="false">
                <i class="bi bi-chevron-down"></i>
            </button>
        </div>
    </div>
    <div class="collapse" id="awardRules">
        <div class="card-body">
            <p class="mb-2">当正式参赛女队（3名队员皆为女生）数目大于等于3时，可以设置最佳女队奖，排名最高且获得铜奖或以上奖项的正式参赛女队获得。</p>
            <p class="mb-2">可以设置顽强拼搏奖，未获得金奖、银奖或铜奖的正式队伍中最晚解出题目的1或2支参赛队获得顽强拼搏奖。</p>
            <p class="mb-0">默认发奖模式：金奖按比例向上取整，金银按比例向上取整后减金奖个数，金银铜按比例向上取整后减去金银个数。</p>
        </div>
    </div>
</div>

    <div id="award_toolbar">
        <div class="d-flex align-items-center gap-2" role="form">
            <div class="csg-switch csg-switch-md">
                <input type="checkbox" id="switch_one_two_three" name="one_two_three" class="csg-switch-input"
                       data-csg-text-on="一二三" data-csg-text-off="金银铜" 
                       data-csg-text-on-en="One Two Three" data-csg-text-off-en="Gold Silver Bronze"
                       title="点击切换显示方式">
            </div>
            <div class="csg-switch csg-switch-md">
                <input type="checkbox" id="switch_with_star_team" name="with_star_team" class="csg-switch-input"
                       data-csg-text-on="包含打星" data-csg-text-off="不包含打星"
                       data-csg-text-on-en="Include Star" data-csg-text-off-en="Exclude Star"
                       title="点击切换打星队伍处理方式">
            </div>
            <div class="csg-switch csg-switch-md">
                <input type="checkbox" id="switch_all_team_based" name="all_team_based" class="csg-switch-input"
                       data-csg-text-on="总数为基数" data-csg-text-off="过题为基数"
                       data-csg-text-on-en="Total Count" data-csg-text-off-en="Solved Count"
                       title="点击切换基数计算方式">
            </div>
        </div>
    </div>

<div id="award_table_div">
    <table id="award_table" 
           data-toggle="table"
           data-pagination="false"
           data-side-pagination="client"
           data-sort-name="rank_order"
           data-sort-order="asc"
           data-classes="table table-hover table-striped"
           data-show-refresh="false"
           data-show-columns="true"
           data-search="true"
           data-search-on-enter-key="true"
           data-maintain-meta-data="true"
           data-toolbar="#award_toolbar">
        <thead>
            <tr>
                <th data-field="rank_order" data-align="center" data-sortable="true" data-width="60" data-formatter="FormatterAwardRank">排名<span class="en-text">Rank</span></th>
                <th data-field="award" data-align="center" data-sortable="false" data-width="100" data-formatter="FormatterAward">获奖<span class="en-text">Award</span></th>
                <th data-field="name" data-align="left" data-sortable="true" data-formatter="FormatterAwardTeamName">队名<span class="en-text">Team Name</span></th>
                <th data-field="tkind" data-align="center" data-sortable="false" data-width="50" data-formatter="FormatterAwardTkind">类型<span class="en-text">Type</span></th>
                <th data-field="solved" data-align="center" data-sortable="true" data-width="60" data-formatter="FormatterAwardSolved">解题<span class="en-text">Solved</span></th>
                <th data-field="penalty" data-align="center" data-sortable="true" data-width="80" data-formatter="FormatterAwardPenalty">罚时<span class="en-text">Penalty</span></th>
                <th data-field="school" data-align="left" data-sortable="true" data-formatter="FormatterAwardSchool">学校<span class="en-text">School</span></th>
                <th data-field="members" data-align="left" data-sortable="false" data-formatter="FormatterAwardMembers">选手<span class="en-text">Members</span></th>
                <th data-field="coach" data-align="left" data-sortable="false" data-width="80" data-formatter="FormatterAwardCoach">教练<span class="en-text">Coach</span></th>
                <th data-field="team_id" data-align="left" data-sortable="true" data-width="80" data-formatter="FormatterAwardTeamId">ID<span class="en-text">ID</span></th>
            </tr>
        </thead>
    </table>
</div>

<script type="text/javascript">
// RankSystem 配置信息
window.RANK_CONFIG = {
    key: 'award_<?php echo $contest['contest_id']; ?>',
    cid_list: '<?php echo $contest['contest_id']; ?>',
    api_url: '/<?php echo $module; ?>/contest/contest_data_ajax',
    team_photo_url: '/upload/contest_attach/<?php echo $contest_attach ?? ""; ?>/team_photo',
    school_badge_url: '/static/image/school_badge',
    region_flag_url: '/static/image/region_flag',
    rank_mode: 'team',
    flg_rank_cache: false
};

// 获奖系统配置信息
window.AWARD_CONFIG = {
    module: "<?php echo $module; ?>",
    cid: "<?php echo $contest['contest_id']; ?>",
    contest_title: "<?php echo $contest['title']; ?>",
    flg_rank_cache: false
};

// 初始化获奖系统
new AwardSystem('award_container', window.AWARD_CONFIG);
</script>
</div>
