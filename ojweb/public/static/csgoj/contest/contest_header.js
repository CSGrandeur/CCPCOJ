/**
 * Contest Header JavaScript文件
 * 包含比赛头部时间显示、状态管理、管理员功能等
 */

// 全局变量
var contestTimeDiff = 0;

/**
 * 格式化时间数字，小于10的数字前面补0
 */
function Str0(a) {
    if (a < 10) return "0" + a;
    else return a;
}

/**
 * 比赛时钟显示函数
 * @returns {boolean} 是否执行了状态更新
 */
function ContestClock() {
    var h, m, s, n, y, mon, d;
    var x = new Date(new Date().getTime() + contestTimeDiff);
    y = x.getFullYear();
    mon = Str0(x.getMonth() + 1);
    d = Str0(x.getDate());
    h = Str0(x.getHours());
    m = Str0(x.getMinutes());
    s = Str0(x.getSeconds());
    n = y + '-' + mon + '-' + d + ' ' + h + ':' + m + ':' + s;
    $('#current_time_div').text(n);
    setTimeout(ContestClock, 1000);
    // 每逢整30秒检查比赛状态
    if (s == 0 || s == 30) {
        UpdateContestStatus();
        return true; // 返回标记表示执行了状态更新
    }
    return false; // 返回标记表示未执行状态更新
}

/**
 * 计算比赛状态
 */
function CalculateContestStatus() {
    const config = window.contestHeaderConfig || {};
    if (!config.start_time || !config.end_time) {
        return { status: -1, rankFrozen: false };
    }
    
    const now = new Date(new Date().getTime() + contestTimeDiff);
    const startTime = new Date(config.start_time);
    const endTime = new Date(config.end_time);
    const frozenMinute = parseInt(config.frozen_minute) || 0;
    const frozenAfter = parseInt(config.frozen_after) || 0;
    
    // 计算封榜时间
    const closeRankTime = new Date(endTime.getTime() - frozenMinute * 60 * 1000);
    const frozenEndTime = new Date(endTime.getTime() + frozenAfter * 60 * 1000);
    
    let status = -1; // 未开始
    let rankFrozen = false;
    
    if (now < startTime) {
        status = -1; // 未开始
    } else if (now < endTime) {
        status = 1; // 进行中
    } else {
        status = 2; // 已结束
    }
    
    // 检查是否在封榜时间段
    if (now > closeRankTime && now < frozenEndTime) {
        rankFrozen = true;
    }
    
    // 管理员不封榜
    if (config.is_contest_admin === 'true' || config.proctor_admin === 'true') {
        rankFrozen = false;
    }
    
    return { status, rankFrozen };
}

/**
 * 更新比赛状态显示
 */
function UpdateContestStatus() {
    const { status, rankFrozen } = CalculateContestStatus();
    const config = window.contestHeaderConfig || {};
    
    // 更新比赛状态
    const $contestStatus = $('#contest_status_display');
    if ($contestStatus.length > 0) {
        let statusHtml = '';
        switch (status) {
            case -1:
                statusHtml = '<span class="text-primary">未开始<span class="en-text">Not Started</span></span>';
                break;
            case 1:
                statusHtml = '<span class="text-success">进行中<span class="en-text">Running</span></span>';
                break;
            case 2:
                statusHtml = '<span class="text-secondary">已结束<span class="en-text">Ended</span></span>';
                break;
        }
        $contestStatus.html(statusHtml);
    }
    
    // 更新榜单状态
    const $rankStatus = $('#rank_status_display');
    if ($rankStatus.length > 0) {
        let rankHtml = '';
        if (rankFrozen) {
            rankHtml = '<span class="text-purple">封榜<span class="en-text">Rank Frozen</span></span>';
        } else {
            rankHtml = '<span class="text-success">实时<span class="en-text">Running</span></span>';
        }
        $rankStatus.html(rankHtml);
    }
    
    // 更新当前时间颜色
    const $currentTime = $('#current_time_div');
    $currentTime.removeClass('text-primary text-success text-secondary text-purple');
    
    if (rankFrozen) {
        $currentTime.addClass('text-purple');
    } else if (status == -1) {
        $currentTime.addClass('text-primary');
    } else if (status == 1) {
        $currentTime.addClass('text-success');
    } else {
        $currentTime.addClass('text-secondary');
    }
}

/**
 * 获取提问数量
 */
function GetTopicNum() {
    if (document.visibilityState !== 'visible') {
        return;
    }
    csg.store("topic_cache_flg", true, 25000);
    let tmp_module = window.contestHeaderConfig?.module || 'csgoj';
    let tmp_cid = window.contestHeaderConfig?.contest_id;
    let action_page = window.contestHeaderConfig?.action || '';
    
    if (!tmp_cid) {
        console.warn('Contest ID not found in contestHeaderConfig');
        return;
    }
    
    $.get(`/${tmp_module}/contest/topic_num_ajax?cid=${tmp_cid}`, function(rep) {
        if (rep.code == 1) {
            const topic_num_key = `topic_num#cid${tmp_cid}`;
            const reply_num_key = `topic_reply#cid${tmp_cid}`;
            const topic_num_cache = csg.store(topic_num_key);
            const reply_num_cache = csg.store(reply_num_key);
            const flg_new_topic = topic_num_cache === null && rep.data?.count || topic_num_cache !== null && rep.data?.count && rep.data.count > topic_num_cache;
            const flg_new_reply = reply_num_cache === null && rep.data?.reply_sum || reply_num_cache !== null && rep.data?.reply_sum && rep.data.reply_sum > reply_num_cache;
            if (flg_new_topic || flg_new_reply) {
                let tmp_title = flg_new_topic ? '新提问 (New Topic)' : '';
                if(tmp_title && flg_new_reply) {
                    tmp_title += " & "
                }
                tmp_title += flg_new_reply ? '新回复 (New Reply)' : '';
                $('#topic_num').html(`<strong class="badge bg-danger ms-1" title="${tmp_title}">${rep.data.count}</strong>`);
            } else {
                $('#topic_num').text(rep.data?.count ? `(${rep.data.count})` : '');
            }
            if(action_page == 'topic_list') {
                // 确保管理员查看了 topic_list 页面，消除高亮显示（更新提问/回复数）
                if (rep.data?.count) {
                    csg.store(topic_num_key, rep.data.count);
                }
                if(rep.data?.reply_sum) {
                    csg.store(reply_num_key, rep.data.reply_sum);
                }
            }                
        }
    });
}

/**
 * 初始化比赛头部功能
 */
function InitContestHeader() {
    // 初始化时间差
    const timeStamp = $('#current_time_div').attr('time_stamp');
    if (timeStamp) {
        contestTimeDiff = new Date(timeStamp * 1000).getTime() - new Date().getTime();
    }
    
    // 启动时钟（使用setInterval确保稳定的1秒间隔）
    var statusUpdated = ContestClock(); // 立即执行一次，获取是否已更新状态
    
    // 如果时钟函数没有更新状态，则手动更新一次
    if (!statusUpdated) {
        UpdateContestStatus();
    }
    setTimeout(ContestClock, 1000);
        
    // 获取提问数量
    const config = window.contestHeaderConfig || {};
    if (config.is_contest_admin || config.proctor_admin || !csg.store("topic_cache_flg")) {
        GetTopicNum();        
    }
    setInterval(GetTopicNum, config.is_contest_admin || config.proctor_admin ? 30000 : 60000);
}

// 等待DOM加载完成后初始化
$(document).ready(function() {
    InitContestHeader();
});
