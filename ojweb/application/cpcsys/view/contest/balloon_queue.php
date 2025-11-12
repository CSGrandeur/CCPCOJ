{css file="__STATIC__/csgoj/contest/balloon_manager.css" /}
{include file="../../csgoj/view/public/js_rank"}
{include file="../../csgoj/view/public/base_select" /}
{include file="../../csgoj/view/public/base_csg_switch" /}
{js href="__STATIC__/lodop/LodopFuncs.js" /}
{js href="__STATIC__/csgoj/contest/balloon_manager.js" /}
{js href="__STATIC__/csgoj/contest/balloon_print.js" /}

<!-- 气球队列页面 -->
<div class="container-fluid mt-3">
    <!-- <div class="balloon-title-compact">
        <div class="balloon-title-text">气球总览<en-text>Balloon Overview</en-text></div>
    </div> -->
    <h2 class="page-title balloon-title-text">气球队列<en-text>Balloon Queue</en-text></h2>
    
    <!-- 加载提示 -->
    <div id="balloon-queue-loading" class="text-center py-5" style="display: none;">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">加载中...</span>
        </div>
        <div class="mt-2">加载中...<en-text>Loading...</en-text></div>
    </div>
    
    <!-- 配送员标签页 -->
    {if $balloonSender && !$balloonManager && !$isContestAdmin}
    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" 
                    id="tab-queue" 
                    type="button" 
                    data-bs-toggle="tab"
                    data-bs-target="#tab-pane-queue"
                    aria-controls="tab-pane-queue"
                    aria-selected="true">
                气球队列<en-text>Balloon Queue</en-text>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" 
                    id="tab-my-balloons" 
                    type="button" 
                    data-bs-toggle="tab"
                    data-bs-target="#tab-pane-my-balloons"
                    aria-controls="tab-pane-my-balloons"
                    aria-selected="false">
                我的气球<en-text>My Balloons</en-text>
            </button>
        </li>
    </ul>
    {/if}
    
    <!-- 自动打印小票管理区域 -->
    {if $balloonManager || $isContestAdmin}
    <div class="balloon-print-manager card mb-3">
        <div class="card-header">
            <h5 class="mb-0 bilingual-inline">自动打印小票<en-text>Auto Print Tickets</en-text></h5>
        </div>
        <div class="card-body">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <!-- 自动打印开关 -->
                <div class="toolbar-group">
                    <div class="toolbar-btn-group d-flex align-items-center gap-2">
                        <div class="csg-switch">
                            <input type="checkbox" 
                                   class="csg-switch-input" 
                                   id="balloon-auto-print-box"
                                   data-csg-size="md"
                                   data-csg-theme="primary"
                                   data-csg-animate="true"
                                   data-csg-text-on="自动"
                                   data-csg-text-on-en="Auto"
                                   data-csg-text-off="手动"
                                   data-csg-text-off-en="Manual">
                        </div>
                        <span class="text-info" id="balloon-print-countdown">(<strong id="balloon-print-countdown-text">10</strong>s)</span>
                    </div>
                </div>
                
                <div class="toolbar-group">
                    <span class="toolbar-label-inline"><span>纸张尺寸</span><span class="toolbar-label en-text">Page Size</span></span>
                    <select class="form-select" id="balloon-print-page-size" name="balloon-print-page-size">
                        <option value="57x30">57mm x 30mm (小票)</option>
                        <option value="57x50">57mm x 50mm (小票)</option>
                        <option value="58x80">58mm x 80mm (小票)</option>
                        <option value="58x100">58mm x 100mm (小票)</option>
                        <option value="76x130">76mm x 130mm (小票)</option>
                        <option value="80x60">80mm x 60mm (小票)</option>
                        <option value="80x80">80mm x 80mm (小票)</option>
                        <option value="80x100">80mm x 100mm (小票)</option>
                        <option value="80x120">80mm x 120mm (小票)</option>
                        <option value="100x150">100mm x 150mm</option>
                        <option value="148x210">148mm x 210mm (A5)</option>
                        <option value="210x297">210mm x 297mm (A4)</option>
                        <option value="custom">自定义 Custom</option>
                    </select>
                </div>
                
                <!-- 自定义尺寸输入（默认隐藏） -->
                <div class="toolbar-group" id="balloon-print-custom-size-group" style="display: none;">
                    <span class="toolbar-label-inline"><span>宽度</span><span class="toolbar-label en-text">Width</span></span>
                    <input type="number" class="form-control toolbar-input" id="balloon-print-custom-width" placeholder="mm" min="10" max="500" value="58" style="width: 80px;">
                </div>
                <div class="toolbar-group" id="balloon-print-custom-size-group2" style="display: none;">
                    <span class="toolbar-label-inline"><span>高度</span><span class="toolbar-label en-text">Height</span></span>
                    <input type="number" class="form-control toolbar-input" id="balloon-print-custom-height" placeholder="mm" min="10" max="500" value="80" style="width: 80px;">
                </div>
                
                <!-- 每页数量 -->
                <div class="toolbar-group">
                    <span class="toolbar-label-inline"><span>每页数量</span><span class="toolbar-label en-text">Per Page</span></span>
                    <input type="number" class="form-control toolbar-input" id="balloon-print-per-page" placeholder="1" min="1" max="1" value="1" style="width: 80px;">
                    <small class="text-muted" id="balloon-print-max-hint" style="font-size: 0.75rem;">(最大: <span id="balloon-print-max-count">1</span>)</small>
                </div>
            </div>
        </div>
    </div>
    {/if}
    
    <!-- 统计头部 -->
    <div class="balloon-queue-stats mb-3">
        <div class="balloon-global-stats" id="balloon-queue-stats">
            <div class="balloon-stat-item" data-status="0" title-cn="未发气球" title-en="Not Sent">
                <span class="balloon-stat-label">未处理<en-text>Not Sent</en-text></span>
                <span class="balloon-stat-value" id="balloon-stat-value-0">0</span>
            </div>
            <div class="balloon-stat-item" data-status="10" title-cn="已通知" title-en="Printed/Issued">
                <span class="balloon-stat-label">已通知<en-text>Printed</en-text></span>
                <span class="balloon-stat-value" id="balloon-stat-value-10">0</span>
            </div>
            <div class="balloon-stat-item" data-status="20" title-cn="已分配" title-en="Assigned">
                <span class="balloon-stat-label">已分配<en-text>Assigned</en-text></span>
                <span class="balloon-stat-value" id="balloon-stat-value-20">0</span>
            </div>
            <div class="balloon-stat-item" data-status="30" title-cn="已发放" title-en="Delivered">
                <span class="balloon-stat-label">已发放<en-text>Delivered</en-text></span>
                <span class="balloon-stat-value" id="balloon-stat-value-30">0</span>
            </div>
        </div>
    </div>
    
    <!-- 筛选器工具栏（仅管理员显示） -->
    {if $balloonManager || $isContestAdmin}
    <div id="balloon-queue-toolbar" class="table-toolbar mb-3">
        <div class="balloon-filter-container">
            <div class="balloon-filter-row">
                <div class="balloon-filter-group">
                    <span class="balloon-filter-label"><span>配送员</span><span class="en-text">Balloon Sender</span></span>
                    <select class="multiple-select" id="filter-sender" name="filter-sender">
                        <option value="">全部 All</option>
                    </select>
                </div>
                <div class="balloon-filter-group">
                    <span class="balloon-filter-label"><span>房间/区域</span><span class="en-text">Room/Area</span></span>
                    <select class="multiple-select" id="filter-rooms" name="filter-rooms" multiple>
                        <!-- room筛选器将在这里动态生成 -->
                    </select>
                </div>
                <div class="balloon-filter-group">
                    <span class="balloon-filter-label"><span>学校</span><span class="en-text">School</span></span>
                    <select class="multiple-select" id="filter-schools" name="filter-schools" multiple>
                        <!-- school筛选器将在这里动态生成 -->
                    </select>
                </div>
                <div class="balloon-filter-group">
                    <span class="balloon-filter-label"><span>题号</span><span class="en-text">Problem</span></span>
                    <select class="multiple-select" id="filter-problems" name="filter-problems" multiple>
                        <!-- problem筛选器将在这里动态生成 -->
                    </select>
                </div>
                <div class="balloon-filter-group">
                    <span class="balloon-filter-label"><span>搜索</span><span class="en-text">Search</span></span>
                    <input type="text" 
                           class="form-control balloon-filter-input" 
                           id="filter-search" 
                           name="filter-search"
                           placeholder="队伍ID/队名"
                           title="队伍ID/队名 Team ID/Name">
                </div>
                <div class="balloon-filter-group">
                    <button type="button" 
                            class="btn btn-outline-secondary btn-sm" 
                            id="balloon-filter-clear"
                            title="清空所有筛选条件 Clear all filters">
                        <i class="bi bi-x-circle"></i> 清空<en-text>Clear</en-text>
                    </button>
                </div>
            </div>
        </div>
    </div>
    {/if}
    
    <!-- 表格容器 -->
    <div class="card">
        <div class="card-body p-0">
            <table id="balloon-queue-table" 
                    data-toggle="table"
                    data-pagination="true"
                    data-page-list="[10, 25, 50{if $balloonManager || $isContestAdmin}, 100{/if}]"
                    data-page-size="25"
                    data-side-pagination="client"
                    data-search="false"
                    data-classes="table table-bordered table-striped"
                    data-pagination-v-align="bottom"
                    data-pagination-h-align="left"
                    data-pagination-detail-h-align="right"
                    data-row-style="RowFormatterBalloonQueue"
                    {if $balloonManager || $isContestAdmin}data-toolbar="#balloon-queue-toolbar"{/if}>
                <thead>
                    <tr>
                        <th data-field="idx" data-align="center" data-valign="middle" data-sortable="true" data-width="100" data-formatter="FormatterIdx">Idx<span class="en-text">Idx</span></th>
                        {if $balloonManager || $isContestAdmin}
                        <th data-field="school" data-align="left" data-valign="middle" data-sortable="true">学校<span class="en-text">School</span></th>
                        <th data-field="team_name" data-align="left" data-valign="middle" data-sortable="true">队名<span class="en-text">Team Name</span></th>
                        <th data-field="room" data-align="center" data-valign="middle" data-sortable="true" data-width="120" data-formatter="FormatterBalloonRoom">房间<span class="en-text">Room</span></th>
                        {/if}
                        <th data-field="team_id" data-align="center" data-valign="middle" data-sortable="true" data-width="100" data-formatter="FormatterBalloonTeamId">队伍ID<span class="en-text">Team ID</span></th>
                        <th data-field="problem" data-align="center" data-valign="middle" data-sortable="true" data-width="80" data-formatter="FormatterBalloonProblem">题号<span class="en-text">Problem</span></th>
                        {if $balloonManager || $isContestAdmin}
                        <th data-field="first_blood" data-align="center" data-valign="middle" data-sortable="false" data-width="100" data-formatter="FormatterBalloonFirstBlood">首答<span class="en-text">First Blood</span></th>
                        {/if}
                        <th data-field="bst" data-align="center" data-valign="middle" data-sortable="true" data-width="80" data-formatter="FormatterBalloonStatus">状态<span class="en-text">Status</span></th>
                        {if $balloonManager || $isContestAdmin}
                        <th data-field="sender" data-align="center" data-valign="middle" data-sortable="true" data-width="120" data-formatter="FormatterBalloonSender">配送员<span class="en-text">Sender</span></th>
                        {/if}
                        
                        <th data-field="in_date" data-align="center" data-valign="middle" data-sortable="true" data-width="120" data-formatter="FormatterTime">时间<span class="en-text">Time</span></th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
<script>
    // 配置信息（需要包含父类RankSystem所需的所有配置）
    window.RANK_CONFIG = {
        key: 'balloon_queue_<?php echo $contest['contest_id']; ?>',
        cid_list: '<?php echo $contest['contest_id']; ?>',
        api_url: '/cpcsys/contest/balloon_data_ajax',
        school_badge_url: '/static/image/school_badge',
        region_flag_url: '/static/image/region_flag',
        backend_time_diff: 0,
        flg_show_page_contest_title: false,
        flg_show_fullscreen_contest_title: false,
        flg_rank_cache: false,
        flg_show_time_progress: false,
        flg_show_controls_toolbar: false,
    };
    
    window.BALLOON_QUEUE_CONFIG = {
        is_balloon_manager: <?php echo ($balloonManager || $isContestAdmin) ? 'true' : 'false'; ?>,
        is_balloon_sender: <?php echo $balloonSender ? 'true' : 'false'; ?>,
        current_user: <?php echo $contest_user ? "'" . addslashes($contest_user) . "'" : 'null'; ?>,
        team_room: <?php echo isset($teaminfo['room']) && $teaminfo['room'] ? "'" . addslashes($teaminfo['room']) . "'" : 'null'; ?>,
        change_status_url: '/cpcsys/contest/balloon_change_status_ajax'
    };
</script>
<!-- Lodop 对象初始化（用于小票打印） -->
<object id="LODOP_OB" classid="clsid:2105C259-1E0C-4534-8141-A753534CB4CA" width=0 height=0>
    <embed id="LODOP_EM" type="application/x-print-lodop" width=0 height=0></embed>
</object>
<script>
    // 初始化气球队列系统（external mode，不依赖容器）
    const queueSystem = new BalloonQueueSystem(null, window.BALLOON_QUEUE_CONFIG);
    window.balloonQueueSystem = queueSystem;
</script>

<style>
/* bootstrap5强制设置了 td 的背景色导致 tr 背景色不生效，所以这里覆盖继承 */
#balloon-queue-table td {
  background-color: inherit !important;
  color: inherit !important;
}
</style>