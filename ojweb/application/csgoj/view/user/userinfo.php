<div class="user-profile-header">
    <div class="section-header">
        <div class="user-avatar">
            <i class="bi bi-person-circle"></i>
        </div>
        <h1 class="user-title ">{$baseinfo['user_id']}<span class="en-text">{$baseinfo['nick']|htmlspecialchars}</span></h1>
    </div>
    <hr class="my-4" />

    <div class="user-basic-info">
        <!-- 左侧：用户基本信息 -->
        <div class="user-info-left">
            <div class="user-avatar-section">
                <div class="user-details">
                    
                <div class="info-item">
                            <span class="info-label contact-label">学校<span class="en-text">School</span></span>
                            <span class="info-value">{$baseinfo['school']|htmlspecialchars}</span>
                        </div>
                            <div class="info-item">
                            <span class="info-label contact-label">邮箱<span class="en-text">Email</span></span>
                            <span class="info-value"><?php echo htmlspecialchars(str_replace('@', '#', $baseinfo['email'])); ?></span>
                        </div>
                </div>


            </div>
        </div>

        <!-- 中间：统计信息 -->
        <div class="user-info-center">
            <div class="user-stats-compact info-group">
                <div class="info-item">
                    <span class="info-label">排名<span class="en-text">Rank</span></span>
                    <span class="info-value stat-value">{$rank}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">通过<span class="en-text">Solved</span></span>
                    <span class="info-value stat-value text-success">{$solved}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">提交<span class="en-text">Submit</span></span>
                    <span class="info-value stat-value">{$submit}</span>
                </div>
            </div>
        </div>

        <!-- 右侧：时间和联系信息 -->
        <div class="user-info-right">
            <div class="user-timeline info-group">
                <div class="info-item">
                    <span class="info-label timeline-label">注册时间<span class="en-text">Registration Time</span></span>
                    <span class="info-value">{$baseinfo['reg_time']}</span>
                </div>
                <div class="info-item">
                    <span class="info-label timeline-label">最后登录<span class="en-text">Last Login</span></span>
                    <span class="info-value">{$baseinfo['accesstime']}</span>
                </div>
            </div>
        </div>
    </div>
</div>
<div id="userinfo_div">
    <div id="userinfo_left">
        <?php $problem_oneline = 10; ?>
        {include file="../../csgoj/view/user/userinfo_solved" /}
        {include file="../../csgoj/view/user/userinfo_tried" /}
    </div>
    <div id="userinfo_right">
        {if isset($loginlog) }
        <div class="login-log-section">
            <h4 class="section-title">登录日志<span class="en-text">Login Log</span></h4>
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>IP地址<span class="en-text">IP</span></th>
                            <th>时间<span class="en-text">Time</span></th>
                            <th>状态<span class="en-text">Success</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach($loginlog as $log)}
                        <tr>
                            <td><code>{$log['ip']}</code></td>
                            <td>{$log['time']}</td>
                            <td>
                                <?php if ($log['success'] == '1'): ?>
                                    <span class="text-success fw-bold">成功<span class="en-text">Yes</span></span>
                                <?php else: ?>
                                    <span class="text-danger fw-bold">失败<span class="en-text">No</span></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>
        </div>
        {/if}
    </div>
</div>
<input id="page_info" type="hidden" user_volume="{$baseinfo['volume']}">

<script>
    let page_info;
    let user_volume;
</script>
<style type="text/css">
    /* 用户信息页面样式 */
    .user-profile-header {
        background: white;
        border: 1px solid #e9ecef;
        padding: 1rem 1.5rem;
        border-radius: 6px;
        margin-bottom: 1rem;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }

    .user-title {
        color: #495057;
        font-weight: 600;
        margin-bottom: 0.5rem;
        font-size: 1.5rem;
    }

    .user-basic-info {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 2rem;
        align-items: start;
    }

    .user-info-left {
        display: flex;
        flex-direction: column;
    }

    .user-avatar-section {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .user-avatar {
        font-size: 2.5rem;
        color: #6c757d;
    }

    .user-details {
        flex: 1;
    }

    .user-id {
        color: #495057;
        font-size: 1.5rem;
        font-weight: 600;
        margin: 0;
    }

    .user-nick {
        color: #6c757d;
        font-size: 1rem;
        margin-top: 0.25rem;
    }

    .user-info-center {
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .user-info-right {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }


    /* 通用信息项样式 */
    .info-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .info-label {
        color: #6c757d;
        font-size: 0.8rem;
        min-width: 60px;
    }
    
    .info-value {
        color: #495057;
        font-weight: 500;
        font-size: 0.9rem;
        min-width: 70px;
    }
    
    /* 信息组容器样式 */
    .info-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    
    /* 特定信息组样式 */
    .user-stats-compact {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .user-contact-info {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid #e9ecef;
    }
    
    /* 特定标签宽度 */
    .timeline-label {
        min-width: 80px;
    }
    
    .contact-label {
        min-width: 70px;
    }
    
    /* 特定值样式 */
    .stat-value {
        font-weight: 600;
    }

    /* 统计卡片样式 */
    .user-info-stats {
        margin-bottom: 1rem;
    }

    .stat-card {
        background: white;
        border: 1px solid #e9ecef;
        border-radius: 6px;
        padding: 0.75rem;
        text-align: center;
        transition: all 0.3s ease;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }

    .stat-card:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .stat-label {
        color: #6c757d;
        font-size: 0.8rem;
        margin-bottom: 0.25rem;
    }

    .stat-value {
        font-size: 1.25rem;
        font-weight: 600;
        color: #495057;
    }

    /* 题目区域样式 */
    .problem-section {
        background: white;
        border: 1px solid #e9ecef;
        border-radius: 6px;
        padding: 1rem;
        margin-bottom: 1rem;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }

    .section-header {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.75rem;
    }

    .section-title {
        color: #495057;
        font-size: 1.1rem;
        font-weight: 600;
        margin: 0;
    }

    .problem-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 0.25rem;
        align-items: center;
    }

    .problem-link {
        display: inline-block;
        padding: 0.2rem 0.5rem;
        background: #e3f2fd;
        color: #1976d2;
        text-decoration: none;
        border-radius: 12px;
        font-size: 0.8rem;
        font-weight: 500;
        transition: all 0.3s ease;
        border: 1px solid #bbdefb;
    }

    .problem-link:hover {
        background: #1976d2;
        color: white;
        transform: translateY(-1px);
    }

    .problem-link.tried {
        background: #fff3e0;
        color: #f57c00;
        border-color: #ffcc02;
    }

    .problem-link.tried:hover {
        background: #f57c00;
        color: white;
    }

    /* 登录日志样式 */
    .login-log-section {
        background: white;
        border: 1px solid #e9ecef;
        border-radius: 6px;
        padding: 1rem;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }

    /* 布局调整 */
    #userinfo_div {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 2rem;
        margin-top: 2rem;
    }

    #userinfo_left {
        min-width: 0;
    }

    #userinfo_right {
        min-width: 0;
    }

    @media (max-width: 950px) {
        .user-basic-info {
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        .user-avatar-section {
            justify-content: center;
        }

        .user-info-center {
            justify-content: flex-start;
        }

        .user-info-right {
            gap: 0.75rem;
        }

        .info-label {
            font-size: 0.75rem;
            min-width: 70px;
        }

        .info-value {
            font-size: 0.85rem;
        }

        .user-contact-info {
            margin-top: 0.75rem;
            padding-top: 0.75rem;
        }

        #userinfo_div {
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        .user-profile-header {
            padding: 1rem;
        }

        .user-id {
            font-size: 1.25rem;
        }
    }
</style>