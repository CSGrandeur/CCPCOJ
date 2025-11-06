<div class="admin-page-header">
    <div class="admin-page-header-left">
        <div class="admin-page-header-icon">
            <i class="bi bi-shield-check"></i>
        </div>
        <h1 class="admin-page-header-title">
            <div class="admin-page-header-title-main">
                IP检查
            </div>
            <div class="admin-page-header-title-right">
                <a href="__CPC__/contest/contest?cid={$contest['contest_id']}" class="admin-page-header-id">
                    <i class="bi bi-hash"></i> {$contest['contest_id']}
                </a>
                <span class="en-text">
                    IP Check
                </span>
            </div>
        </h1>
    </div>
    <div class="admin-page-header-right">
        <button type="button" class="btn btn-outline-primary btn-sm" onclick="RefreshResults()">
            <span class="cn-text"><i class="bi bi-arrow-clockwise me-1"></i>
            刷新</span><span class="en-text">Refresh</span>
        </button>
    </div>
</div>

<div class="container">
    <!-- IP检查内容 -->
    <div class="row ipcheck_div">
    {for start="0" end="2"}
    <?php
    if($i == 0)
    {
        $colorType = 'danger';
        $checktype = '单用户多IP登录';
        $checktypeEn = 'Single user login with different IPs';
    }
    else
    {
        $colorType = 'warning';
        $checktype = '多用户同IP登录';
        $checktypeEn = 'Multiple users login with same IP';
    }
    ?>
    <div class="col-lg-6 col-md-6 mb-3">
        <div class="card border-{$colorType}">
            <div class="card-header bg-{$colorType} text-white">
                <h5 class="card-title mb-0">
                    <span class="cn-text"><i class="bi bi-shield-exclamation me-2"></i>
                    {$checktype}
                    </span><span class="en-text">{$checktypeEn}</span>
                </h5>
            </div>
            <div class="card-body p-0" id="checktype{$i}" style="overflow-y: auto; max-height: 400px;">
                <!-- 数据将通过 JavaScript 动态加载 -->
            </div>
        </div>
    </div>
    {/for}
</div>
</div>
<input type="hidden" name="cid" id="contest_id_input" value="{$contest['contest_id']}" >

<script type="text/javascript">
    let checktype0 = $('#checktype0');
    let checktype1 = $('#checktype1');
    let cid = $('#contest_id_input').val();
    function RefreshResults()
    {
        $.get(
            'ipcheck_ajax',
            {
                'cid': cid
            },
            function(ret)
            {
                if(ret['code'] == 1)
                {
                    let data = ret['data'];
                    let userIps = data['userIps'];
                    let ipUsers = data['ipUsers'];
                    
                    // 处理单用户多IP登录
                    checktype0.empty();
                    let appendContent = "<div class='table-responsive'><table class='table table-hover table-sm'>";
                    for(let key in userIps)
                    {
                        appendContent += "<thead class='table-light'><tr><th class='fw-bold'>" + key + "</th><th class='fw-bold'>" +
                            userIps[key]['name'] + "</th></tr></thead>" +
                            "<tbody>";
                        for(let ipith in userIps[key]['ips'])
                        {
                            let ip = userIps[key]['ips'][ipith];
                            appendContent += "<tr><td><code>" + ip['ip'] + "</code></td><td class='text-muted small'>" + ip['time'] + "</td></tr>";
                        }
                        appendContent += "</tbody>";
                    }
                    appendContent += "</table></div>";
                    checktype0.append(appendContent);

                    // 处理多用户同IP登录
                    checktype1.empty();
                    appendContent = "<div class='table-responsive'><table class='table table-hover table-sm'>";
                    for(let key in ipUsers)
                    {
                        appendContent += "<thead class='table-light'><tr><th class='fw-bold'>" + key + "</th><th class='fw-bold'>登录时间<span class='en-text'>Login Time</span></th></tr></thead><tbody>";
                        for(let userith in ipUsers[key])
                        {
                            let user = ipUsers[key][userith];
                            appendContent += "<tr><td><strong>" + user['team_id'] + "</strong>: " + user['name'] + "</td><td class='text-muted small'>" + user['time'] + "</td></tr>";
                        }
                        appendContent += "</tbody>";
                    }
                    appendContent += "</table></div>";
                    checktype1.append(appendContent);
                }
                else
                {
                    alerty.error('加载IP检查数据失败', ret['msg']);
                }
            },
            'json'
        );
    }

    $(document).ready(function() {
        RefreshResults();
    });

</script>

<style type="text/css">
    .ipcheck_div .card-body {
        overflow-y: auto;
        max-height: 400px;
    }
    
    .ipcheck_div .table-responsive {
        border: none;
    }
    
    .ipcheck_div .table th {
        border-top: none;
        font-size: 0.9rem;
    }
    
    .ipcheck_div .table td {
        font-size: 0.85rem;
        vertical-align: middle;
    }
    
    .ipcheck_div code {
        font-size: 0.8rem;
        background-color: #f8f9fa;
        padding: 0.2rem 0.4rem;
        border-radius: 0.25rem;
    }
</style>