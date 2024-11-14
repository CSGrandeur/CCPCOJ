<div class="cteam_info_balloon_container">
{foreach name="problemIdMap['num2color']" item="item" key="key"}
    {php}
        $pid_abc = array_key_exists($key, $problemIdMap['num2abc']) ? $problemIdMap['num2abc'][$key] : '-'; 
    {/php}
    <a href="/{$module}/contest/problem?cid={$contest['contest_id']}&pid={$pid_abc}">
        <div class="cteam_info_balloon cteam_info_outline" data-letter="{$pid_abc}" pnum="$key" style="--balloon-color: #{$item};"></div>
    </a>
{/foreach}
</div>
<div class="cteam_info_score_container">
    
</div>
{js href="__STATIC__/csgoj/rank_common.js" /}
<script>
    $(document).ready(() => {
        var module = "<?php echo $module; ?>";
        var cid = "<?php echo $contest['contest_id']; ?>";
        $.get(`/${module}/contest/team_score_now_ajax?cid=${cid}`, function (rep) {
            if (rep.code === 1) {
                let ret = rep.data;
                let team_data = ret?.team_data;
                console.log(ret);
            } else {
                console.error(rep);
            }
        });
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
</style>