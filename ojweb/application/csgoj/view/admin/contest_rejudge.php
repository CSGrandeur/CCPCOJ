

<script type="text/javascript">
    // 比赛重判配置
    window.rejudgeConfig = {
        module: "{$module}",
        contest_id: "{$contest['contest_id']}",
        submit_url: "/{$module}/admin/contest_rejudge_ajax?cid={$contest['contest_id']}"
    };
</script>

{include file="../../admin/view/problem/problem_rejudge_page" /}