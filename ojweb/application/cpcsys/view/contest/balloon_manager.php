
{css file="__STATIC__/csgoj/contest/rank.css" /}
{css file="__STATIC__/csgoj/contest/balloon_manager.css" /}
{include file="../../csgoj/view/public/base_csg_switch" /}

<!-- 气球管理头部 -->
<div class="balloon-header-section">
    <div class="container-fluid">
        <div class="balloon-header-row">
            <div class="balloon-title-compact">
                <div class="balloon-title-text">气球全览<en-text>Balloon Overview</en-text></div>
            </div>
            <div class="balloon-controls-group">
                <div class="csg-switch">
                    <input type="checkbox" 
                           class="csg-switch-input" 
                           id="balloon-auto-refresh-switch"
                           data-csg-size="md"
                           data-csg-theme="primary"
                           data-csg-animate="true"
                           data-csg-text-on="自动刷新"
                           data-csg-text-on-en="Auto Refresh"
                           data-csg-text-off="手动刷新"
                           data-csg-text-off-en="Manual Refresh">
                </div>
                <span class="balloon-refresh-countdown" id="balloon-refresh-countdown" style="display: none;">
                    <span id="balloon-countdown-text">10</span>
                </span>
            </div>
            <div class="balloon-global-stats" id="balloon-global-stats">
                <!-- 统计信息将在这里动态更新 -->
            </div>
        </div>
    </div>
</div>

<!-- 榜单容器 -->
<div id="balloon-container"></div>

<script>
    const timeStamp = $('#current_time_div').attr('time_stamp');
    let rank_time_diff = 0;
    if (timeStamp) {
        rank_time_diff = new Date(timeStamp * 1000).getTime() - new Date().getTime();
    }
    // 配置信息
    window.RANK_CONFIG = {
        key: 'balloon_<?php echo $contest['contest_id']; ?>',
        cid_list: '<?php echo $contest['contest_id']; ?>',
        api_url: '/cpcsys/contest/balloon_data_ajax',
        school_badge_url: '/static/image/school_badge',
        region_flag_url: '/static/image/region_flag',
        backend_time_diff: rank_time_diff || 0,
        flg_show_page_contest_title: false,
        flg_show_fullscreen_contest_title: false,
        flg_rank_cache: false,
        flg_show_time_progress: false,
        flg_show_controls_toolbar: false,
    };
</script>
{js href="__STATIC__/js/csg_anim.js" /}
{js href="__STATIC__/csgoj/contest/rank_tool.js" /}
{js href="__STATIC__/csgoj/contest/rank.js" /}
{js href="__STATIC__/csgoj/contest/balloon_manager.js" /}
<script>
    // 初始化气球管理系统
    const balloonSystem = new BalloonManagerSystem('balloon-container', window.RANK_CONFIG);
    
    // 将 balloonSystem 保存到全局，方便调试
    window.balloonSystem = balloonSystem;
</script>

