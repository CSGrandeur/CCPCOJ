<div class="admin-page-header">
    <div class="admin-page-header-left">
        <div class="admin-page-header-icon">
            <i class="bi bi-people"></i>
        </div>
        <h1 class="admin-page-header-title">
            <div class="admin-page-header-title-main">
                队伍信息
            </div>
            <div class="admin-page-header-title-right">
                <a href="__CPC__/contest/contest?cid={$contest['contest_id']}" class="admin-page-header-id">
                    <i class="bi bi-hash"></i> {$contest['contest_id']}
                </a>
                <span class="en-text">
                    Team Information
                </span>
            </div>
        </h1>
    </div>
    <div class="admin-page-header-right">
        <a href="__CPC__/contest/contest?cid={$contest['contest_id']}" class="btn btn-outline-primary btn-sm">
            <span class="cn-text"><i class="bi bi-arrow-left me-1"></i>
            返回比赛</span><span class="en-text">Back to Contest</span>
        </a>
    </div>
</div>

<div class="container">
    <div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    基本信息<span class="en-text text-muted">Basic Information</span>
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    <!-- 队伍基本信息 -->
                    <div class="col-12">
                        <div class="info-section">
                            <h6 class="info-section-title">
                                <span class="cn-text"><i class="bi bi-people me-2"></i>
                                队伍基本信息</span><span class="en-text">Team Basic Information</span>
                            </h6>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <div class="info-item">
                                        <span class="info-label">队伍ID<span class="en-text">Team ID</span></span>
                                        <span class="info-value">{$teaminfo['team_id']}</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-item">
                                        <span class="info-label">学校/组织<span class="en-text">School/Organization</span></span>
                                        <span class="info-value">{$teaminfo['school']}</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-item">
                                        <span class="info-label">队伍名称<span class="en-text">Team Name</span></span>
                                        <span class="info-value">{$teaminfo['name']}</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-item">
                                        <span class="info-label">副语言队名<span class="en-text">Secondary Language Name</span></span>
                                        <span class="info-value">{$teaminfo['name_en']}</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-item">
                                        <span class="info-label">国家/地区<span class="en-text">Country/Region</span></span>
                                        <span class="info-value">{$teaminfo['region']}</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-item">
                                        <span class="info-label">机房<span class="en-text">Room</span></span>
                                        <span class="info-value">{$teaminfo['room']}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 人员信息 -->
                    <div class="col-12">
                        <div class="info-section">
                            <h6 class="info-section-title">
                                <span class="cn-text"><i class="bi bi-person-lines-fill me-2"></i>
                                人员信息</span><span class="en-text">Personnel Information</span>
                            </h6>
                            <div class="row g-2">
                                <div class="col-12">
                                    <div class="info-item">
                                        <span class="info-label">队员<span class="en-text">Members</span></span>
                                        <span class="info-value">{$teaminfo['tmember']}</span>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="info-item">
                                        <span class="info-label">教练<span class="en-text">Coach</span></span>
                                        <span class="info-value">{$teaminfo['coach']}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    队伍类型<span class="en-text text-muted">Team Type</span>
                </h5>
            </div>
            <div class="card-body">
                {php}
                $teamTypes = [
                    0 => ['cn' => '正式队伍', 'en' => 'Regular Team'],
                    1 => ['cn' => '女队', 'en' => 'Girls Team'],
                    2 => ['cn' => '打星队伍', 'en' => 'Star Team'],
                ];
                $currentType = $teamTypes[$teaminfo['tkind']] ?? $teamTypes[0];
                {/php}
                <div class="d-flex align-items-center">
                    <span class="badge bg-primary me-2">
                        {$currentType['cn']}
                    </span>
                    <span class="text-muted en-text">{$currentType['en']}</span>
                </div>
            </div>
        </div>
        
        {if $teaminfo['privilege']}
        <div class="card mt-3">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    特殊权限<span class="en-text text-muted">Special Privileges</span>
                </h5>
            </div>
            <div class="card-body">
                {php}
                $privilegeTypes = [
                    'admin' => ['cn' => '管理员', 'en' => 'Administrator'],
                    'printer' => ['cn' => '打印员', 'en' => 'Printer'],
                    'balloon_manager' => ['cn' => '气球管理员', 'en' => 'Balloon Manager'],
                    'balloon_sender' => ['cn' => '气球发送员', 'en' => 'Balloon Sender'],
                    'watcher' => ['cn' => '观察员', 'en' => 'Watcher']
                ];
                $currentPrivilege = $privilegeTypes[$teaminfo['privilege']] ?? ['cn' => '未知', 'en' => 'Unknown'];
                {/php}
                <div class="d-flex align-items-center">
                    <span class="badge bg-warning me-2">
                        {$currentPrivilege['cn']}
                    </span>
                    <span class="text-muted en-text">{$currentPrivilege['en']}</span>
                </div>
            </div>
        </div>
        {/if}
    </div>
</div>
</div>

<style type="text/css">
/* 信息展示样式优化 - 紧凑版 */
.info-section {
    margin-bottom: 1rem;
    padding: 0.75rem;
    background-color: #f8f9fa;
    border-radius: 0.375rem;
    border-left: 3px solid #0d6efd;
}

.info-section:last-child {
    margin-bottom: 0;
}

.info-section-title {
    color: #495057;
    font-weight: 600;
    margin-bottom: 0.75rem;
    padding-bottom: 0.375rem;
    border-bottom: 1px solid #dee2e6;
    font-size: 0.95rem;
}

.info-section-title i {
    color: #0d6efd;
}

.info-item {
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
    padding: 0.5rem;
    background-color: white;
    border-radius: 0.25rem;
    border: 1px solid #e9ecef;
    transition: all 0.2s ease;
}

.info-item:hover {
    border-color: #0d6efd;
    box-shadow: 0 1px 3px rgba(13, 110, 253, 0.1);
}

.info-label {
    font-size: 0.8rem;
    font-weight: 500;
    color: #6c757d;
    line-height: 1.1;
}

.info-label .en-text {
    font-size: 0.7rem;
    color: #adb5bd;
    display: block;
    margin-top: 0.0625rem;
}

.info-value {
    font-size: 0.9rem;
    font-weight: 500;
    color: #212529;
    word-break: break-word;
}

.info-value a {
    color: #0d6efd;
    text-decoration: none;
}

.info-value a:hover {
    text-decoration: underline;
}

/* 响应式优化 */
@media (max-width: 768px) {
    .info-section {
        padding: 0.5rem;
    }
    
    .info-item {
        padding: 0.375rem;
    }
    
    .info-label {
        font-size: 0.75rem;
    }
    
    .info-value {
        font-size: 0.85rem;
    }
}
</style>