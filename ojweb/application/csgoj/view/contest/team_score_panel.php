<div class="cteam_info_balloon_container">
    {foreach name="problemIdMap['num2color']" item="item" key="key"}
    {php}
    $pid_abc = array_key_exists($key, $problemIdMap['num2abc']) ? $problemIdMap['num2abc'][$key] : '-';
    {/php}
    <a class="a_noline" href="/{$module}/contest/problem?cid={$contest['contest_id']}&pid={$pid_abc}">
        <div
            class="cteam_info_balloon cteam_info_outline"
            data-letter="{$pid_abc}"
            pnum="$key" id="cteam_score_pro_{$pid_abc}"
            style="--balloon-color: #{$item};">
        </div>
    </a>
    {/foreach}
</div>
<div class="cteam_info_score_container">
    <div class="glyphicon glyphicon-ok-circle" title="当前解出 / Solved"> <span id="cteam_info_score_solved">-</span></div>
    <div class="glyphicon glyphicon-remove" title="当前尝试 / Tried"> <span id="cteam_info_score_tried">-</span></div>

    <div id="cteam_info_valid_before_frozen">
        <div class="glyphicon glyphicon-sort" title="当前名次 / Rank"> <span id="cteam_info_score_rank">-</span></div>
        <div class="glyphicon glyphicon-stats" title="当前奖区 / The current prize zone for the rank"> <span id="cteam_info_score_award"></span></div>
        <div class="cteam_info_frozen_mask">❄</div>
    </div>
</div>
{js href="__STATIC__/csgoj/rank_common.js" /}
<script>
    function SetCteamScore(ret) {
        let team_data = ret?.team_data;
        $('#cteam_info_score_solved').val(ret.solved);
        let rank_val = ret.rank;
        if (ret.star_team) {
            rank_val = `<span title="按最接近的正式队排名计算 / Based on the nearest formal team">${rank_val}*</span>`
        }
        $('#cteam_info_score_solved').text(ret.solved);
        $('#cteam_info_score_tried').text(ret.tried);
        $('#cteam_info_score_rank').html(rank_val);
        let award_rank = GetAwardRank(ret.total_valid_cnt, ret.ratio_gold, ret.ratio_silver, ret.ratio_bronze);
        let rank_gold = award_rank[0];
        let rank_silver = award_rank[1];
        let rank_bronze = award_rank[2];
        let temp_award = '';
        const temp_award_star = ret.star_team ? '*' : '';
        const title_addition = ret.star_team ? '按最接近的正式队排名计算 / Based on the nearest formal team' : '';
        if (ret.rank == '-') {
            temp_award = '-';
        } else if (ret.rank <= rank_gold) {
            temp_award = `<span class="award_span_gold" title="金 / Gold ${title_addition}">金${temp_award_star}</span>`;
        } else if (ret.rank <= rank_silver) {
            temp_award = `<span class="award_span_silver" title="银 / Silver ${title_addition}">银${temp_award_star}</span>`;
        } else if (ret.rank <= rank_bronze) {
            temp_award = `<span class="award_span_bronze" title="铜 / Bronze ${title_addition}">铜${temp_award_star}</span>`;
        } else if (ret.solved > 0) {
            temp_award = `<span class="award_span_iron" title="铁 / Iron ${title_addition}">铁/iron${temp_award_star}</span>`;
        } else {
            temp_award = `<span >-</span>`;
        }
        ret.pro_res.forEach(pro => {
            let pro_dom = $(`#cteam_score_pro_${pro?.pid_abc}`)
            if(pro_dom.length == 0) {
                return;
            }
            pro_dom.attr('class', 'cteam_info_balloon');
            if(pro.pst >= 2) {
                // ac
                pro_dom.addClass('cteam_info_solved');
                pro_dom.attr('title', `${pro.pid_abc}: Solved`);
            } else if(pro.pst == 1) {
                pro_dom.addClass('cteam_info_tried');
                pro_dom.attr('title', `${pro.pid_abc}: Tried`);
            } else {
                pro_dom.addClass('cteam_info_outline');
                pro_dom.attr('title', `${pro.pid_abc}`);
            }

        });
        $('#cteam_info_score_award').html(temp_award);
        if(ret.rank_frozen) {
            $('.cteam_info_frozen_mask').show();
            $('#cteam_info_valid_before_frozen').attr('title', '封榜 / Frozen');
        }
    }
    function UpdateCteamScore() {
        var module = "<?php echo $module; ?>";
        var cid = "<?php echo $contest['contest_id']; ?>";
        var team_id = "<?php echo $login_teaminfo['team_id']; ?>"
        let cteam_score_cache = csg.store(`cteam_score_cache#${cid}_${team_id}`)
        if(cteam_score_cache) {
            SetCteamScore(cteam_score_cache);
        } else {
            $.get(`/${module}/contest/team_score_now_ajax?cid=${cid}`, function(rep) {
                if (rep.code === 1) {
                    SetCteamScore(rep.data);
                    csg.store(`cteam_score_cache#${cid}_${team_id}`, rep.data, 60 * 1000)
                } else {
                    console.error(rep);
                }
            });

        }
    }
    $(document).ready(() => {
        UpdateCteamScore();
        function scheduleUpdate() {
            if (document.visibilityState === 'visible') {
                UpdateCteamScore();
            }
        }
        setInterval(scheduleUpdate, 60000);
    });
</script>
<style>
    .cteam_info_balloon_container {
        display: flex;
        flex-wrap: wrap;
        width: 100%;
    }

    .cteam_info_balloon {
        width: 16px;
        height: 16px;
        border-radius: 50%;
        position: relative;
        display: flex;
        justify-content: center;
        align-items: center;
        font-size: 10px;
        color: white;
        text-shadow: -0.5px -0.5px 0 #111, 0.5px -0.5px 0 #111, -0.5px 0.5px 0 #111, 0.5px 0.5px 0 #111;
        margin: 1px;
    }

    .cteam_info_balloon::after {
        content: attr(data-letter);
    }

    .cteam_info_solved {
        background-color: var(--balloon-color, red);
    }

    .cteam_info_tried {
        background: linear-gradient(45deg, var(--balloon-color, blue) 50%, transparent 50%);
        border: 1px solid var(--balloon-color, blue);
    }

    .cteam_info_outline {
        background-color: transparent;
        border: 1px solid var(--balloon-color, green);
    }

    .cteam_info_score_container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        width: 100%;
        height: 24px;
        white-space: nowrap;
    }

    .cteam_info_score_container>div {
        align-items: center;
        white-space: nowrap;
        flex-grow: 1;
    }
    #cteam_info_valid_before_frozen {
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: relative;
    }
    .cteam_info_frozen_mask {
        position: absolute;
        top: 0;
        left: -10px;
        right: -10px;
        top: -3px;
        bottom: -10px;
        background: rgba(128, 128, 128, 0.2);
        color: skyblue;
        font-size: 18px;
        border-radius: 5px;
        display: flex;
        justify-content: center;
        align-items: center;
        text-align: center;
        z-index: 1;
        pointer-events: none;
        display: none;
        backdrop-filter: blur(1px);
        -webkit-backdrop-filter: blur(1px);
    }
</style>