
{css file="__STATIC__/csgoj/contest/rank.css" /}
<!-- 榜单容器 -->
<div id="rank-container"></div>

<script>
    const timeStamp = $('#current_time_div').attr('time_stamp');
    let rank_time_diff = 0;
    if (timeStamp) {
        rank_time_diff = new Date(timeStamp * 1000).getTime() - new Date().getTime();
    }
    // 配置信息
    window.RANK_CONFIG = {
        key: '<?php echo $contest['contest_id']; ?>',
        cid_list: '<?php echo $contest['contest_id']; ?>',
        api_url: '/<?php echo $module; ?>/contest/contest_data_ajax',
        team_photo_url: '/upload/contest_attach/<?php echo $contest_attach ?? ""; ?>/team_photo',
        school_badge_url: '/static/image/school_badge',
        region_flag_url: '/static/image/region_flag',
        rank_mode: '<?php echo $rank_mode ?? ""; ?>',
        backend_time_diff: rank_time_diff || 0,
        flg_show_page_contest_title: false,
        flg_show_fullscreen_contest_title: true,
        flg_rank_cache: <?php echo $isContestAdmin ? 'false' : 'true'; ?>,
    };
</script>

{include file="../../csgoj/view/public/js_rank"}
<script>
    // 初始化榜单系统
    RankSystemInit('rank-container', window.RANK_CONFIG);
</script>