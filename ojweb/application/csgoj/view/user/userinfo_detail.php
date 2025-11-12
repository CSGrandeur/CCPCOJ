<div class="user-info-stats" id="info_left">
    <div class="row g-3">
        <div class="col-md-6 col-lg-4">
            <div class="stat-card">
                <div class="stat-label">排名<span class="en-text">Rank</span></div>
                <div class="stat-value">{$rank}</div>
            </div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="stat-card">
                <div class="stat-label">通过<span class="en-text">Solved</span></div>
                <div class="stat-value text-success">{$solved}</div>
            </div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="stat-card">
                <div class="stat-label">提交<span class="en-text">Submissions</span></div>
                <div class="stat-value">{$submit}</div>
            </div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="stat-card">
                <div class="stat-label">学校<span class="en-text">School</span></div>
                <div class="stat-value">{$baseinfo['school']|htmlspecialchars}</div>
            </div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="stat-card">
                <div class="stat-label">邮箱<span class="en-text">Email</span></div>
                <div class="stat-value"><?php echo htmlspecialchars(str_replace('@', '#', $baseinfo['email'])); ?></div>
            </div>
        </div>
    </div>
</div>