
{css file="__STATIC__/csgoj/contest/rank.css" /}
<!-- 榜单容器 -->
<div id="rank-container"></div>

<script>
    const timeStamp = $('#current_time_div').attr('time_stamp');
    let rank_time_diff = 0;
    if (timeStamp) {
        rank_time_diff = new Date(timeStamp * 1000).getTime() - new Date().getTime();
    }
    // 配置信息 - 滚榜页面专用配置
    window.RANK_CONFIG = {
        key: '<?php echo $contest['contest_id']; ?>_roll',
        cid_list: '<?php echo $contest['contest_id']; ?>',
        api_url: '/<?php echo $module; ?>/contest/contest_data_ajax',
        team_photo_url: '/upload/contest_attach/<?php echo $contest['attach'] ?? ""; ?>/team_photo',
        school_badge_url: '/static/image/school_badge',
        region_flag_url: '/static/image/region_flag',
        rank_mode: 'roll',                                    // 强制滚榜模式
        flg_show_page_contest_title: false,
        backend_time_diff: rank_time_diff || 0,
        flg_show_time_progress: false,                        // 隐藏时间进度条
        flg_show_controls_toolbar: false,                    // 隐藏按钮功能区
        flg_show_export_offline_roll: true                   // 显示导出离线滚榜按钮（服务器端默认显示）
    };
</script>
{include file="../../csgoj/view/public/js_zip" /}
{include file="../../csgoj/view/public/js_rank" /}
{js href="__STATIC__/csgoj/contest/rank_roll.js" /}
<script>
    // 初始化滚榜系统
    // RankRollSystem 继承自 RankSystem，构造函数会自动调用 Init()
    // OriInit() 会在数据加载完成后自动初始化滚榜状态并渲染
    const rollSystem = new RankRollSystem('rank-container', window.RANK_CONFIG);
    
    // 将 rollSystem 保存到全局，方便调试
    window.rollSystem = rollSystem;
</script>

