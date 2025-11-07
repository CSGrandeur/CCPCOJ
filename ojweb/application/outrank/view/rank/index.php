{css file="__STATIC__/csgoj/contest/rank.css" /}
<!-- 比赛标题 -->
<div class="container-fluid px-4 py-3 mb-3">
    <h1 class="display-5 fw-bold text-dark mb-0">{$outrank.title|default=''}</h1>
</div>
<!-- 榜单容器 -->
<div id="rank-container"></div>

<!-- 时间戳容器（用于获取后端时间） -->
<div id="current_time_div" time_stamp="<?php echo $timeStamp; ?>" style="display: none;"></div>

<script>
    // 获取时间戳（使用原生 JavaScript，避免依赖 jQuery）
    const timeStampElement = document.getElementById('current_time_div');
    const timeStamp = timeStampElement ? timeStampElement.getAttribute('time_stamp') : null;
    let rank_time_diff = 0;
    if (timeStamp) {
        rank_time_diff = new Date(timeStamp * 1000).getTime() - new Date().getTime();
    }
    // 配置信息
    window.RANK_CONFIG = {
        key: 'outrank_<?php echo $outrank_uuid; ?>',
        cid_list: '<?php echo $outrank_uuid; ?>', // 不重要，随便填
        api_url: '/upload/outrank_attach/<?php echo $outrank_uuid; ?>/rank.json',
        team_photo_url: '/upload/contest_attach/<?php echo $outrank_uuid; ?>/team_photo',
        school_badge_url: '/static/image/school_badge',
        region_flag_url: '/static/image/region_flag',
        rank_mode: 'team', // 固定为 team 模式
        backend_time_diff: rank_time_diff || 0,
        flg_show_page_contest_title: false,
        flg_show_fullscreen_contest_title: true,
        flg_rank_cache: true,
        // 外榜特殊配置
        cache_duration: 60 * 1000, // 60秒缓存
        request_t_param: true, // 启用 t 参数（60秒更新一次）
    };
</script>

{include file="../../csgoj/view/public/js_rank"}
<script>
    // 初始化榜单系统（使用外榜专用子类）
    OutrankRankSystemInit('rank-container', window.RANK_CONFIG);
</script>

