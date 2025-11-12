<!-- 复用开放题目集模板，添加比赛定制化逻辑 -->
{include file="../../csgoj/view/problemset/problem" /}

<!-- 比赛专用逻辑 -->
<input type="hidden" id="contest_status" value="{$contestStatus}">
<script type="text/javascript">
    $('.disabled_problem_submit_button').on('click', function(){
        var contestStatus = $('#contest_status').val();
        if(contestStatus == -1)
            alertify.error('Contest not started!');
        else if(contestStatus == 2)
            alertify.error('Contest ended!');
        else
            alertify.error($(this).attr('info_str'));
    });
</script>